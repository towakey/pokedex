# frozen_string_literal: true

require 'csv'
require 'sqlite3'

ROOT_DIR = File.expand_path('..', __dir__)
DEX_ALL_PATH = File.join(ROOT_DIR, 'data', 'spreadsheet', 'dex_all.csv')
DEX_MAP_PATH = File.join(ROOT_DIR, 'data', 'spreadsheet', 'dex_map.csv')
DB_PATH = File.join(ROOT_DIR, 'pokedex.db')

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


def ensure_pokedex_map_table(db)
  db.execute(<<~SQL)
    CREATE TABLE IF NOT EXISTS pokedex_map (
      id TEXT,
      globalNo TEXT,
      verID TEXT,
      language TEXT,
      dex TEXT
    )
  SQL
end


def import_pokedex_map(db, dex_all_path, verid_map)
  stats = {
    rows: 0,
    inserted: 0,
    skipped_missing_map: 0,
    skipped_empty_dex: 0
  }

  header_set = nil
  active_mapping = LANGUAGE_MAPPING
  insert_sql = 'INSERT INTO pokedex_map (id, globalNo, verID, language, dex) VALUES (?, ?, ?, ?, ?)'
  csv_table = read_csv_with_normalized_newlines(dex_all_path)

  stmt = db.prepare(insert_sql)
  begin
    csv_table.each do |row|
      stats[:rows] += 1

      if header_set.nil?
        header_set = row.headers.compact
        active_mapping = LANGUAGE_MAPPING.select { |csv_col, _| header_set.include?(csv_col) }
        missing = LANGUAGE_MAPPING.keys - active_mapping.keys
        warn "Warning: missing columns in dex_all.csv: #{missing.join(', ')}" unless missing.empty?
      end

      id = row['ID']&.strip
      ver_id = row['verID']&.strip
      next if id.nil? || id.empty? || ver_id.nil? || ver_id.empty?

      combined = verid_map.dig(id, ver_id)
      unless combined && !combined.empty?
        stats[:skipped_missing_map] += 1
        next
      end

      global_no = id[0, 4]
      row_inserted = 0
      active_mapping.each do |csv_col, language|
        dex_text = row[csv_col]
        next if dex_text.nil? || dex_text.to_s.strip.empty?

        stmt.execute(id, global_no, combined, language, dex_text)
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

unless File.exist?(DEX_ALL_PATH)
  STDERR.puts "dex_all.csv not found: #{DEX_ALL_PATH}"
  exit 1
end

unless File.exist?(DEX_MAP_PATH)
  STDERR.puts "dex_map.csv not found: #{DEX_MAP_PATH}"
  exit 1
end

unless File.exist?(DB_PATH)
  STDERR.puts "pokedex.db not found: #{DB_PATH}"
  exit 1
end

puts 'Building verID group map...'
verid_map = build_verid_group_map(DEX_MAP_PATH)
puts "Loaded verID group map entries: #{verid_map.values.sum(&:size)}"

puts 'Opening database...'
db = SQLite3::Database.new(DB_PATH)

ensure_pokedex_map_table(db)
puts 'Clearing pokedex_map table...'
db.execute('DELETE FROM pokedex_map')

stats = nil
puts 'Importing pokedex_map rows...'
db.transaction do
  stats = import_pokedex_map(db, DEX_ALL_PATH, verid_map)
end

puts 'Done.'
puts "Rows processed: #{stats[:rows]}"
puts "Inserted rows: #{stats[:inserted]}"
puts "Skipped (missing verID map): #{stats[:skipped_missing_map]}"
puts "Skipped (empty dex rows): #{stats[:skipped_empty_dex]}"

count = db.get_first_value('SELECT COUNT(*) FROM pokedex_map')
puts "pokedex_map count: #{count}"

db.close
