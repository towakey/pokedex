#!/usr/bin/env ruby
# usage: ruby DB2JSON.rb X_Y

require 'sqlite3'
require 'json'
require 'fileutils'

# 引数(例: "LegendsArceus" / "Legends_Arceus" / "legends_arceus")を
# データベース用のスネークケース(例: "legends_arceus")に正規化するメソッド
def normalize_version_name(name)
  return '' if name.nil?
  return name.downcase if name.include?('_')
  # CamelCase → snake_case
  # name.gsub(/([a-z])([A-Z])/, '\\1_\\2').downcase
  name.gsub(/([a-z])([A-Z])/, '\\1\\2').downcase
end

RAW_VERSION = ARGV[0] || 'LegendsArceus'
VERSION     = normalize_version_name(RAW_VERSION)
puts VERSION
DB_PATH = File.expand_path('pokedex.db', __dir__)
OUT_DIR = File.join(__dir__, 'pokedex', VERSION)
FileUtils.mkdir_p(OUT_DIR)

# オプション: 第二引数にデータ取得元バージョンを指定出来る
# 例) ruby DB2JSON.rb firered_leafgreen ruby_sapphire_emerald
#     → 出力ファイルは firered_leafgreen.json だが、種族値などは
#        ruby_sapphire_emerald から優先取得し、無い場合 firered_leafgreen を使う
SOURCE_VERSION = (ARGV[1] || VERSION).downcase
PRIORITY_VERSIONS = [SOURCE_VERSION, VERSION].uniq

db = SQLite3::Database.new(DB_PATH)
db.results_as_hash = true

# 図鑑リストを取得
pokedex_names = db.execute(<<~SQL, [VERSION]).map { |r| r['pokedex'] }.uniq
  SELECT pokedex
    FROM local_pokedex
   WHERE version = ? COLLATE NOCASE
SQL

json_root = {
  'update'       => Time.now.strftime('%Y%m%d'),
  'game_version' => VERSION,
  'pokedex'      => {}
}

# テーブルごとに参照元バージョンを上書きできる設定
# 例: 'firered_leafgreen' で種族値などを 'ruby_sapphire_emerald' から取る
FALLBACK_BY_DB = {
  type: {
    'firered_leafgreen' => 'ruby_sapphire_emerald'
  },
  ability: {
    'firered_leafgreen' => 'ruby_sapphire_emerald'
  },
  status: {
    'firered_leafgreen' => 'ruby_sapphire_emerald'
  },
  description: {
    'firered_leafgreen' => 'ruby_sapphire_emerald'
  }
}

# 汎用ヘルパー: 優先バージョン順で最初にヒットした行を返す
def fetch_row_with_fallback(db, table_key, base_key, sql, specific_versions = nil)
  versions = specific_versions || [FALLBACK_BY_DB.dig(table_key, VERSION), VERSION].compact.uniq
  versions.each do |v|
    row = db.get_first_row(sql, base_key + [v])
    return row if row
  end
  nil
end

def generate_pokemon_id(no, region, spare1, spare2, gigantamax, mega_evolution, form, mf, out_of_index, shiny)
  if region == 'アローラのすがた' then
    region_value = '01'
  elsif region == 'ガラルのすがた' then
    region_value = '02'
  elsif region == 'ヒスイのすがた' then
    region_value = '03'
  elsif region == 'パルデアのすがた' then
    region_value = '04'
  else
    region_value = '00'
  end
  
  if gigantamax.to_s.empty? then
    gigantamax_value = '0'
  else
    gigantamax_value = '1'
  end
  
  if mega_evolution.to_s.empty? then
    mega_evolution_value = '0'
  else
    mega_evolution_value = '1'
  end

  if form.to_s.empty? then
    form_value = '00'
  else
    form_value = '01'
  end

  # spare1 = '0'
  # spare2 = '0'
  # mf = '0'
  # out_of_index = '000'
  # shiny = '0'

  no.to_s.rjust(4, '0') + "_" + region_value.to_s.rjust(2, '0') + spare1 + spare2 + gigantamax_value + mega_evolution_value + form_value + "_" + mf.to_s + "_" + out_of_index.to_s.rjust(3, '0') + "_" + shiny.to_s
end

pokedex_names.each do |pokedex_name|
  # その図鑑に載っているポケモンを図鑑番号順に取得
  pokemons = db.execute(<<~SQL, [VERSION, pokedex_name])
    SELECT no, globalNo
      FROM local_pokedex
     WHERE version = ? COLLATE NOCASE AND pokedex = ?
  GROUP BY no, globalNo
  ORDER BY CAST(no AS INTEGER)
SQL

  json_root['pokedex'][pokedex_name] =
    pokemons.map do |row|
      no        = row['no']
      global_no = row['globalNo']

      # 形態ごとの status をまとめる
      forms = db.execute(<<~SQL, [VERSION, global_no])
        SELECT *
          FROM local_pokedex
         WHERE version = ? COLLATE NOCASE
           AND globalNo = ?
      SQL

      status_arr = forms.map do |f|
        key = [f['globalNo'], f['form'], f['region'],
               f['mega_evolution'], f['gigantamax']]

        type    = fetch_row_with_fallback(db, :type, key, <<~SQL)
             SELECT type1, type2
               FROM local_pokedex_type
              WHERE globalNo    = ?
                AND form        = ?
                AND region      = ?
                AND mega_evolution = ?
                AND gigantamax  = ?
                AND version     = ? COLLATE NOCASE
           SQL

        ability = fetch_row_with_fallback(db, :ability, key, <<~SQL)
             SELECT ability1, ability2, dream_ability
               FROM local_pokedex_ability
              WHERE globalNo    = ?
                AND form        = ?
                AND region      = ?
                AND mega_evolution = ?
                AND gigantamax  = ?
                AND version     = ? COLLATE NOCASE
           SQL

        stats = fetch_row_with_fallback(db, :status, key, <<~SQL) || {}
             SELECT hp, attack, defense, special_attack,
                    special_defense, speed
               FROM local_pokedex_status
              WHERE globalNo    = ?
                AND form        = ?
                AND region      = ?
                AND mega_evolution = ?
                AND gigantamax  = ?
                AND version     = ? COLLATE NOCASE
           SQL

        # 対応するバージョン名を自動取得 (例: x_y → ["x","y"])
        desc_versions = VERSION.split('_')
        descriptions = {}
        desc_versions.each do |ver|
          versions_list = [ver, FALLBACK_BY_DB.dig(:description, VERSION)].compact.uniq

          sql = <<~SQL
            SELECT description
              FROM local_pokedex_description
             WHERE globalNo    = ?
               AND form        = ?
               AND region      = ?
               AND mega_evolution = ?
               AND gigantamax  = ?
               AND version     = ? COLLATE NOCASE
               AND language    = 'jpn'
          SQL

          desc_row = fetch_row_with_fallback(db, :description, key, sql, versions_list)

          descriptions[ver] = desc_row ? desc_row['description'] : ''
        end

        {
          'id'          => generate_pokemon_id(row['globalNo'], f['region'], '0', '0', f['gigantamax'], f['mega_evolution'], f['form'], '0', '000', '0'),
          'form'          => f['form'],
          'region'        => f['region'],
          'mega_evolution'=> f['mega_evolution'],
          'gigantamax'    => f['gigantamax'],
          'type1'         => type&.[]('type1') || '',
          'type2'         => type&.[]('type2') || '',
          'hp'            => stats['hp'].to_i,
          'attack'        => stats['attack'].to_i,
          'defense'       => stats['defense'].to_i,
          'special_attack'=> stats['special_attack'].to_i,
          'special_defense'=> stats['special_defense'].to_i,
          'speed'         => stats['speed'].to_i,
          'ability1'      => ability&.[]('ability1') || '',
          'ability2'      => ability&.[]('ability2') || '',
          'dream_ability' => ability&.[]('dream_ability') || '',
          'description'   => descriptions.transform_values { |v| v.to_s }
        }
      end

      id = generate_pokemon_id(row['globalNo'], row['region'], '0', '0', row['gigantamax'], row['mega_evolution'], row['form'], '0', '000', '0')
      { 'id' => id, 'no' => no, 'globalNo' => global_no, 'status' => status_arr }
    end
end

File.write(File.join(OUT_DIR, "#{VERSION}.json"),
           JSON.pretty_generate(json_root), mode: 'w:UTF-8')
puts "→ #{OUT_DIR}/#{VERSION}.json を生成しました"