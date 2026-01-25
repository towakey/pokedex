# frozen_string_literal: true

require 'csv'
require 'sqlite3'

ROOT_DIR = File.expand_path('..', __dir__)
DEFAULT_DEX_PATH = File.join(ROOT_DIR, 'data', 'spreadsheet', 'dex.csv')
DEFAULT_MAP_PATH = File.join(ROOT_DIR, 'data', 'spreadsheet', 'map.csv')
DEFAULT_DB_PATH = File.join(ROOT_DIR, 'pokedex.db')

dex_path = ARGV[0] || DEFAULT_DEX_PATH
map_path = ARGV[1] || DEFAULT_MAP_PATH
db_path = ARGV[2] || DEFAULT_DB_PATH

LANGUAGE_MAPPING = {
  'JPN' => 'jpn',
  'ENG' => 'eng',
  'FRA' => 'fra',
  'ITA' => 'ita',
  'GER' => 'ger',
  'ES-ES' => 'es-es',
  'ES-LA' => 'es-la',
  'KOR' => 'kor',
  'CHS' => 'chs',
  'CHT' => 'cht',
  'JPN(かな)' => 'jpn_kana',
  'JPN(漢字)' => 'jpn_kanji'
}.freeze

VERID_COLUMNS = (1..11).map { |index| format('verID%02d', index) }.freeze


def read_csv_with_normalized_newlines(path)
  content = File.binread(path)
  normalized = content.gsub("\r\n".b, "\n").gsub("\r".b, "\n")
  if normalized.start_with?("\xEF\xBB\xBF".b)
    normalized = normalized.byteslice(3..)
  end
  normalized = normalized.force_encoding(Encoding::UTF_8)
  CSV.parse(normalized, headers: true)
end


def build_verid_group_map(path)
  mapping = Hash.new { |hash, key| hash[key] = {} }
  csv_table = read_csv_with_normalized_newlines(path)

  csv_table.each_with_index do |row, index|
    row_number = index + 2
    id = row['ID']&.strip
    next if id.nil? || id.empty?

    ver_ids = VERID_COLUMNS.filter_map do |column|
      value = row[column]
      value = value.nil? ? '' : value.to_s.strip
      value.empty? ? nil : value
    end

    next if ver_ids.empty?

    combined = ver_ids.join(',')
    ver_ids.each do |ver_id|
      existing = mapping[id][ver_id]
      if existing && existing != combined
        warn "Warning: duplicate mapping for #{id}/#{ver_id} at row #{row_number}"
      end
      mapping[id][ver_id] = combined
    end
  end

  mapping
end


def table_exists?(db, table)
  !db.get_first_value("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", table).nil?
end


def table_columns(db, table)
  db.execute("PRAGMA table_info(#{table})").map { |row| row[1] }
end


def with_retry(label, retries: 5, base_sleep: 0.2)
  attempts = 0
  begin
    yield
  rescue SQLite3::BusyException => e
    attempts += 1
    if attempts <= retries
      sleep(base_sleep * attempts)
      retry
    end
    warn "SQLite busy after #{attempts} attempts during #{label}: #{e.message}"
    raise
  end
end


def ensure_pokedex_description_dex_table(db)
  db.execute(<<~SQL)
    CREATE TABLE IF NOT EXISTS pokedex_description_dex (
      id TEXT,
      globalNo TEXT,
      verID TEXT,
      language TEXT,
      dex TEXT
    )
  SQL
end


def ensure_pokedex_description_map_table(db)
  db.execute(<<~SQL)
    CREATE TABLE IF NOT EXISTS pokedex_description_map (
      id TEXT,
      globalNo TEXT,
      verID TEXT,
      language TEXT,
      dex TEXT
    )
  SQL
end


def ensure_pokedex_dex_map_table(db)
  db.execute('DROP TABLE IF EXISTS pokedex_dex_map')
  db.execute(<<~SQL)
    CREATE TABLE IF NOT EXISTS pokedex_dex_map (
      id TEXT,
      globalNo TEXT,
      verID TEXT,
      language TEXT,
      dex TEXT
    )
  SQL
end


def migrate_pokedex_map_to_dex_map(db)
  unless table_exists?(db, 'pokedex_description_map')
    return { migrated: false, rows: 0, reason: 'pokedex_description_map table not found' }
  end

  columns = table_columns(db, 'pokedex_description_map')
  unless columns.include?('language') && columns.include?('dex')
    return { migrated: false, rows: 0, reason: 'pokedex_description_map missing language/dex columns' }
  end

  legacy_rows = db.get_first_value(<<~SQL)
    SELECT COUNT(*) FROM pokedex_description_map
    WHERE (language IS NOT NULL AND TRIM(language) <> '')
       OR (dex IS NOT NULL AND TRIM(dex) <> '')
  SQL
  if legacy_rows.to_i.zero?
    return { migrated: false, rows: 0, reason: 'pokedex_description_map has no legacy language/dex data' }
  end

  db.execute('DELETE FROM pokedex_dex_map')
  db.execute(<<~SQL)
    INSERT INTO pokedex_dex_map (id, globalNo, verID, language, dex)
    SELECT id, globalNo, verID, language, dex FROM pokedex_description_map
  SQL

  count = db.get_first_value('SELECT COUNT(*) FROM pokedex_dex_map')
  { migrated: true, rows: count, reason: nil }
end


def rebuild_pokedex_dex_map_from_description(db)
  db.execute('DELETE FROM pokedex_dex_map')
  db.execute(<<~SQL)
    INSERT INTO pokedex_dex_map (id, globalNo, verID, language, dex)
    SELECT
      m.id,
      m.globalNo,
      m.verID,
      d.language,
      d.dex
    FROM pokedex_description_map m
    INNER JOIN pokedex_description_dex d
      ON d.id = m.id
      AND d.globalNo = m.globalNo
      AND d.verID = CASE
        WHEN instr(m.verID, ',') > 0 THEN substr(m.verID, 1, instr(m.verID, ',') - 1)
        ELSE m.verID
      END
    WHERE d.dex IS NOT NULL AND TRIM(d.dex) <> ''
  SQL

  count = db.get_first_value('SELECT COUNT(*) FROM pokedex_dex_map')
  { rows: count }
end


def import_pokedex_description_dex(db, dex_path)
  stats = {
    rows: 0,
    inserted: 0,
    skipped_empty_dex: 0
  }

  header_set = nil
  active_mapping = LANGUAGE_MAPPING
  insert_sql = 'INSERT INTO pokedex_description_dex (id, globalNo, verID, language, dex) VALUES (?, ?, ?, ?, ?)'
  csv_table = read_csv_with_normalized_newlines(dex_path)

  stmt = db.prepare(insert_sql)
  begin
    csv_table.each do |row|
      stats[:rows] += 1

      if header_set.nil?
        header_set = row.headers.compact
        active_mapping = LANGUAGE_MAPPING.select { |csv_col, _| header_set.include?(csv_col) }
        missing = LANGUAGE_MAPPING.keys - active_mapping.keys
        warn "Warning: missing columns in dex.csv: #{missing.join(', ')}" unless missing.empty?
      end

      id = row['ID']&.strip
      ver_id = row['verID']&.strip
      next if id.nil? || id.empty? || ver_id.nil? || ver_id.empty?

      global_no = id[0, 4]
      row_inserted = 0
      active_mapping.each do |csv_col, language|
        dex_text = row[csv_col]
        next if dex_text.nil? || dex_text.to_s.strip.empty?

        stmt.execute(id, global_no, ver_id, language, dex_text)
        row_inserted += 1
      end

      stats[:inserted] += row_inserted
      stats[:skipped_empty_dex] += 1 if row_inserted.zero?
    end
  ensure
    stmt.close
  end

  stats
end


def import_pokedex_description_map(db, map_path)
  stats = {
    rows: 0,
    inserted: 0,
    skipped_empty_verid: 0
  }
  insert_sql = 'INSERT INTO pokedex_description_map (id, globalNo, verID, language, dex) VALUES (?, ?, ?, ?, ?)'
  csv_table = read_csv_with_normalized_newlines(map_path)

  stmt = db.prepare(insert_sql)
  begin
    csv_table.each do |row|
      stats[:rows] += 1

      id = row['ID']&.strip
      next if id.nil? || id.empty?

      ver_ids = VERID_COLUMNS.filter_map do |column|
        value = row[column]
        value = value.nil? ? '' : value.to_s.strip
        value.empty? ? nil : value
      end

      if ver_ids.empty?
        stats[:skipped_empty_verid] += 1
        next
      end

      combined = ver_ids.join(',')
      global_no = id[0, 4]
      stmt.execute(id, global_no, combined, nil, nil)
      stats[:inserted] += 1
    end
  ensure
    stmt.close
  end

  stats
end

unless File.exist?(dex_path)
  STDERR.puts "dex.csv not found: #{dex_path}"
  exit 1
end

unless File.exist?(map_path)
  STDERR.puts "map.csv not found: #{map_path}"
  exit 1
end

unless File.exist?(db_path)
  STDERR.puts "pokedex.db not found: #{db_path}"
  exit 1
end

puts 'Opening database...'
db = SQLite3::Database.new(db_path)
db.busy_timeout = 5000

ensure_pokedex_description_dex_table(db)
ensure_pokedex_description_map_table(db)
ensure_pokedex_dex_map_table(db)

puts 'Migrating existing pokedex_description_map -> pokedex_dex_map...'
migration = nil
with_retry('migration') do
  db.transaction do
    migration = migrate_pokedex_map_to_dex_map(db)
  end
end
if migration[:migrated]
  puts "Migrated rows: #{migration[:rows]}"
else
  warn "Skipped migration: #{migration[:reason]}"
end

puts 'Clearing pokedex_description_dex table...'
with_retry('clear pokedex_description_dex') do
  db.execute('DELETE FROM pokedex_description_dex')
end
puts 'Clearing pokedex_description_map table...'
with_retry('clear pokedex_description_map') do
  db.execute('DELETE FROM pokedex_description_map')
end

stats_dex = nil
stats_map = nil
puts 'Importing pokedex_description_dex rows (dex.csv)...'
with_retry('import pokedex_description_dex') do
  db.transaction do
    stats_dex = import_pokedex_description_dex(db, dex_path)
  end
end

puts 'Importing pokedex_description_map rows (map.csv)...'
with_retry('import pokedex_description_map') do
  db.transaction do
    stats_map = import_pokedex_description_map(db, map_path)
  end
end

unless migration[:migrated]
  rebuild = nil
  puts 'Rebuilding pokedex_dex_map from pokedex_description_* tables...'
  with_retry('rebuild pokedex_dex_map') do
    db.transaction do
      rebuild = rebuild_pokedex_dex_map_from_description(db)
    end
  end
  puts "Rebuilt rows: #{rebuild[:rows]}"
end

puts 'Done.'
puts "[pokedex_description_dex] Rows processed: #{stats_dex[:rows]}"
puts "[pokedex_description_dex] Inserted rows: #{stats_dex[:inserted]}"
puts "[pokedex_description_dex] Skipped (empty dex rows): #{stats_dex[:skipped_empty_dex]}"
puts "[pokedex_description_map] Rows processed: #{stats_map[:rows]}"
puts "[pokedex_description_map] Inserted rows: #{stats_map[:inserted]}"
puts "[pokedex_description_map] Skipped (empty verID rows): #{stats_map[:skipped_empty_verid]}"

count_dex = db.get_first_value('SELECT COUNT(*) FROM pokedex_description_dex')
count_map = db.get_first_value('SELECT COUNT(*) FROM pokedex_description_map')
count_dex_map = db.get_first_value('SELECT COUNT(*) FROM pokedex_dex_map')
puts "pokedex_description_dex count: #{count_dex}"
puts "pokedex_description_map count: #{count_map}"
puts "pokedex_dex_map count: #{count_dex_map}"

db.close
