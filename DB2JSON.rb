#!/usr/bin/env ruby
# usage: ruby DB2JSON.rb X_Y

require 'sqlite3'
require 'json'
require 'fileutils'

VERSION = (ARGV[0] || 'x_y').downcase  # 例: 'x_y'
DB_PATH = File.expand_path('new_pokedex.db', __dir__)
OUT_DIR = File.join(__dir__, 'pokedex', VERSION)
FileUtils.mkdir_p(OUT_DIR)

db = SQLite3::Database.new(DB_PATH)
db.results_as_hash = true

# 図鑑リストを取得
pokedex_names = db.execute(<<~SQL, [VERSION]).map { |r| r['pokedex'] }.uniq
  SELECT pokedex
    FROM local_pokedex
   WHERE version = ?
SQL

json_root = {
  'update'       => Time.now.strftime('%Y%m%d'),
  'game_version' => VERSION,
  'pokedex'      => {}
}

pokedex_names.each do |pokedex_name|
  # その図鑑に載っているポケモンを図鑑番号順に取得
  pokemons = db.execute(<<~SQL, [VERSION, pokedex_name])
    SELECT no, globalNo
      FROM local_pokedex
     WHERE version = ? AND pokedex = ?
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
         WHERE version = ?
           AND globalNo = ?
      SQL

      status_arr = forms.map do |f|
        key = [f['globalNo'], f['form'], f['region'],
               f['mega_evolution'], f['gigantamax']]

        type    = db.get_first_row(<<~SQL, [*key, VERSION])
          SELECT type1, type2
            FROM local_pokedex_type
           WHERE globalNo    = ?
             AND form        = ?
             AND region      = ?
             AND mega_evolution = ?
             AND gigantamax  = ?
             AND version     = ?
        SQL

        ability = db.get_first_row(<<~SQL, [*key, VERSION])
          SELECT ability1, ability2, dream_ability
            FROM local_pokedex_ability
           WHERE globalNo    = ?
             AND form        = ?
             AND region      = ?
             AND mega_evolution = ?
             AND gigantamax  = ?
             AND version     = ?
        SQL

        stats   = db.get_first_row(<<~SQL, [*key, VERSION]) || {}
           SELECT hp, attack, defense, special_attack,
                  special_defense, speed
             FROM local_pokedex_status
            WHERE globalNo    = ?
             AND form        = ?
             AND region      = ?
             AND mega_evolution = ?
             AND gigantamax  = ?
             AND version     = ?
         SQL

        # 対応するバージョン名を自動取得 (例: x_y → ["x","y"])
        desc_versions = VERSION.split('_')
        descriptions = {}
        desc_versions.each do |ver|
          descriptions[ver] = db.get_first_value(<<~SQL, [*key, ver])
            SELECT description
              FROM local_pokedex_description
             WHERE globalNo    = ?
               AND form        = ?
               AND region      = ?
               AND mega_evolution = ?
               AND gigantamax  = ?
               AND version     = ?
               AND language    = 'jpn'
          SQL
        end

        {
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

      { 'no' => no, 'globalNo' => global_no, 'status' => status_arr }
    end
end

File.write(File.join(OUT_DIR, "#{VERSION}.json"),
           JSON.pretty_generate(json_root), mode: 'w:UTF-8')
puts "→ #{OUT_DIR}/#{VERSION}.json を生成しました"