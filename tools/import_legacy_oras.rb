#!/usr/bin/env ruby
# import_legacy_oras.rb
# 旧フォーマットの OmegaRuby_AlphaSapphire.json を読み込み、
# CreateDB.rb が構築する local_pokedex 系テーブルに挿入する。
#
# 旧フォーマット:
#   pokedex["ホウエン図鑑"] = [
#     { "no": "1", "globalNo": "252", "status": [ { "form": "", ... "description": {...} } ] }
#   ]
#
# 新フォーマット (CreateDB.rb が期待):
#   pokedex["ホウエン図鑑"] = {
#     "1" => { "0252_00000000_0_000_0" => { "form": "", ... } }
#   }
#
# このスクリプトは旧フォーマットを変換して同じDBテーブルに挿入する。
# 既存データ (version = 'OmegaRuby_AlphaSapphire') があれば先に削除する。

require 'sqlite3'
require 'json'

ROOT_DIR    = File.expand_path('..', __dir__)
POKEDEX_DIR = File.join(ROOT_DIR, 'pokedex')
CONFIG_DIR  = File.join(ROOT_DIR, 'config')

GAME_VERSION  = 'OmegaRuby_AlphaSapphire'
POKEDEX_NAMES = ['ホウエン図鑑']

JSON_PATH = File.join(POKEDEX_DIR, GAME_VERSION, "#{GAME_VERSION}.json")
DB_PATH   = File.join(ROOT_DIR, 'pokedex.db')

# ---------------------------------------------------------------------------
# ID 生成（DB2JSON.rb の generate_pokemon_id ラムダと同一ロジック）
# ---------------------------------------------------------------------------
begin
  config_content = File.read(File.join(CONFIG_DIR, 'pokedex_config.json'))
  config = JSON.parse(config_content)
  REGION_MAPPING    = config['region_mapping']    || {}
  DEFAULT_REGION_VALUE = config['default_region_value'] || '00'
rescue => e
  STDERR.puts "pokedex_config.json の読み込みに失敗: #{e.message}"
  exit 1
end

def generate_pokemon_id(no, region, gigantamax, mega_evolution, form_seq)
  region_value = REGION_MAPPING[region.to_s] || DEFAULT_REGION_VALUE

  gigantamax_value   = gigantamax.to_s.empty?    ? '0' : '1'
  mega_evolution_value = mega_evolution.to_s.empty? ? '0' : '1'

  form_value = form_seq.to_s.rjust(2, '0')

  spare1 = '0'
  spare2 = '0'
  mf     = '0'
  out_of_index = '000'
  shiny  = '0'

  no.to_s.rjust(4, '0') +
    '_' + region_value.to_s.rjust(2, '0') +
    spare1 + spare2 +
    gigantamax_value + mega_evolution_value + form_value +
    '_' + mf +
    '_' + out_of_index +
    '_' + shiny
end

# ---------------------------------------------------------------------------
# タイプ順序補正（CreateDB.rb と同一）
# ---------------------------------------------------------------------------
TYPE_ORDER = %w[
  ノーマル ほのお みず でんき くさ こおり かくとう どく じめん
  ひこう エスパー むし いわ ゴースト ドラゴン あく はがね フェアリー
].freeze

def ordered_types(type1, type2)
  return [type1, type2] if type1.to_s.empty? || type2.to_s.empty? || type1 == type2
  idx1 = TYPE_ORDER.index(type1)
  idx2 = TYPE_ORDER.index(type2)
  return [type1, type2] unless idx1 && idx2
  idx1 > idx2 ? [type2, type1] : [type1, type2]
end

# ---------------------------------------------------------------------------
# JSON 読み込み
# ---------------------------------------------------------------------------
unless File.exist?(JSON_PATH)
  STDERR.puts "ファイルが見つかりません: #{JSON_PATH}"
  exit 1
end

begin
  raw = JSON.parse(File.read(JSON_PATH, encoding: 'UTF-8'))
rescue JSON::ParserError => e
  STDERR.puts "JSON パースエラー: #{e.message}"
  exit 1
end

# 旧フォーマット確認
pokedex_root = raw['pokedex']
sample = pokedex_root.values.first
unless sample.is_a?(Array)
  STDERR.puts "このJSONは既に新フォーマットのようです（配列ではありません）。処理を中止します。"
  exit 1
end

# ---------------------------------------------------------------------------
# DB 接続
# ---------------------------------------------------------------------------
db = SQLite3::Database.new(DB_PATH)
db.results_as_hash = false

# テーブル存在確認
tables = db.execute("SELECT name FROM sqlite_master WHERE type='table'").flatten
required = %w[local_pokedex local_pokedex_type local_pokedex_ability
              local_pokedex_status local_pokedex_description]
missing = required - tables
unless missing.empty?
  STDERR.puts "必要なテーブルが存在しません: #{missing.join(', ')}"
  STDERR.puts "先に CreateDB.rb を実行してテーブルを作成してください。"
  exit 1
end

puts "既存データ (version = '#{GAME_VERSION}') を削除します..."
db.transaction do
  required.each do |tbl|
    db.execute("DELETE FROM #{tbl} WHERE version = ?", [GAME_VERSION])
  end
end
puts "削除完了。"

# ---------------------------------------------------------------------------
# データ挿入
# ---------------------------------------------------------------------------
insert_count = 0

db.transaction do
  POKEDEX_NAMES.each do |pokedex_name|
    pokedex_array = pokedex_root[pokedex_name]
    unless pokedex_array
      puts "  図鑑 '#{pokedex_name}' が見つかりません。スキップ。"
      next
    end

    puts "  図鑑: #{pokedex_name} (#{pokedex_array.size} 件)"

    pokedex_array.each do |pokemon|
      no        = pokemon['no'].to_s.rjust(4, '0')
      global_no = pokemon['globalNo'].to_s.rjust(4, '0')
      statuses  = pokemon['status'] || []

      statuses.each_with_index do |form, idx|
        form_str       = form['form'].to_s
        region_str     = form['region'].to_s
        mega_evo_str   = form['mega_evolution'].to_s
        gigantamax_str = form['gigantamax'].to_s

        form_id = generate_pokemon_id(
          pokemon['globalNo'],
          region_str,
          gigantamax_str,
          mega_evo_str,
          idx
        )

        # --- local_pokedex ---
        db.execute(
          'INSERT INTO local_pokedex (id, no, globalNo, form, region, mega_evolution, gigantamax, version, pokedex) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
          [form_id, no, global_no, form_str, region_str, mega_evo_str, gigantamax_str, GAME_VERSION, pokedex_name]
        )

        # --- local_pokedex_type ---
        t1, t2 = ordered_types(form['type1'].to_s, form['type2'].to_s)
        db.execute(
          'INSERT OR IGNORE INTO local_pokedex_type (id, globalNo, form, region, mega_evolution, gigantamax, version, type1, type2) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
          [form_id, global_no, form_str, region_str, mega_evo_str, gigantamax_str, GAME_VERSION, t1, t2]
        )

        # --- local_pokedex_ability ---
        db.execute(
          'INSERT INTO local_pokedex_ability (id, globalNo, form, region, mega_evolution, gigantamax, version, ability1, ability2, dream_ability) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
          [form_id, global_no, form_str, region_str, mega_evo_str, gigantamax_str, GAME_VERSION,
           form['ability1'].to_s, form['ability2'].to_s, form['dream_ability'].to_s]
        )

        # --- local_pokedex_status ---
        db.execute(
          'INSERT INTO local_pokedex_status (id, globalNo, form, region, mega_evolution, gigantamax, version, hp, attack, defense, special_attack, special_defense, speed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
          [form_id, global_no, form_str, region_str, mega_evo_str, gigantamax_str, GAME_VERSION,
           form['hp'].to_i, form['attack'].to_i, form['defense'].to_i,
           form['special_attack'].to_i, form['special_defense'].to_i, form['speed'].to_i]
        )

        # --- local_pokedex_description ---
        (form['description'] || {}).each do |ver_key, description|
          next if description.to_s.empty?
          db.execute(
            'INSERT OR IGNORE INTO local_pokedex_description
               (id, globalNo, form, region, mega_evolution, gigantamax, version, ver, pokedex, language, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [form_id, global_no, form_str, region_str, mega_evo_str, gigantamax_str,
             GAME_VERSION, ver_key, pokedex_name, 'jpn', description]
          )
        end

        insert_count += 1
      end
    end
  end
end

puts "挿入完了: #{insert_count} フォーム"
db.close
puts "処理が完了しました。"
