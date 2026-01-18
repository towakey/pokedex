#!/usr/bin/env ruby
# DB2JSON_for_map.rb
# pokedex.dbのpokedex_mapテーブルからglobalNo -> id -> verID -> 言語キー構造のJSONを生成するスクリプト

require 'sqlite3'
require 'json'
require 'time'

ROOT_DIR = File.expand_path('..', __dir__)
DB_PATH = File.join(ROOT_DIR, 'pokedex.db')
OUT_PATH = File.join(ROOT_DIR, 'description_map.json')

if __FILE__ == $0
  db = SQLite3::Database.new(DB_PATH)
  
  # 対象言語キーの配列
  language_keys = ['jpn', 'eng', 'fra', 'ita', 'ger', 'spa', 'kor', 'chs', 'cht']
  
  puts "データベースからデータを取得中..."
  
  # globalNo -> id -> verID -> 言語キー構造のハッシュ
  pokemon_data = {}
  
  # pokedex_dex_map、pokedex、pokedex_descriptionを結合してデータを取得
  # dm.verIDはカンマ区切りのverIDリスト（例: "01_01,03_10,08_00"）
  # pd.idは正しい形式のID（例: "0001_00000000_0_000_0"）
  # d.verIDは個別のverID（例: "01_01"）
  map_data_sql = <<-SQL
    SELECT DISTINCT
      dm.globalNo, 
      pd.id, 
      dm.verID, 
      d.language, 
      d.dex
    FROM pokedex_dex_map dm
    INNER JOIN pokedex pd ON (
      dm.globalNo = pd.globalNo
      AND dm.id = REPLACE(pd.id, '_0_000_0', '')  -- pokedexのIDから_0_000_0を削除
    )
    INNER JOIN pokedex_description d ON (
      dm.globalNo = d.globalNo 
      AND dm.id = d.id 
      AND (
        -- dm.verIDにd.verIDが含まれている場合（カンマ区切り対応）
        dm.verID = d.verID
        OR dm.verID LIKE d.verID || ',%'
        OR dm.verID LIKE '%,' || d.verID
        OR dm.verID LIKE '%,' || d.verID || ',%'
      )
    )
    WHERE d.language IN (#{language_keys.map { |lang| "'#{lang}'" }.join(', ')})
    ORDER BY CAST(dm.globalNo AS INTEGER) ASC, pd.id ASC, dm.verID ASC, d.language ASC
  SQL
  
  map_data = db.execute(map_data_sql)
  
  map_data.each do |row|
    global_no, id, ver_id, language, dex = row
    
    # globalNoの階層を初期化
    pokemon_data[global_no] ||= {}
    
    # idの階層を初期化
    pokemon_data[global_no][id] ||= {}
    
    # verIDの階層を初期化（指定された順序で言語キーを初期化）
    unless pokemon_data[global_no][id][ver_id]
      pokemon_data[global_no][id][ver_id] = {}
      # 言語キーを指定された順番で初期化
      language_keys.each { |lang| pokemon_data[global_no][id][ver_id][lang] = "" }
    end
    
    # 言語キーと値を設定
    if dex && !dex.empty?
      pokemon_data[global_no][id][ver_id][language] = dex
    else
      pokemon_data[global_no][id][ver_id][language] = ""
    end
  end
  
  # 出力用データ構造を構築
  output_data = {
    "update" => Time.now.strftime("%Y%m%d%H%M"),
    "data" => pokemon_data
  }
  
  # JSON出力
  puts "description_map.jsonファイルを生成中..."
  
  File.open(OUT_PATH, 'w:UTF-8') do |file|
    file.write(JSON.pretty_generate(output_data, indent: '  '))
  end
  
  puts "description_map.jsonが正常に生成されました"
  puts "総globalNo数: #{pokemon_data.size}"
  
  # 統計情報を表示
  total_ids = pokemon_data.values.sum { |ids| ids.size }
  total_versions = pokemon_data.values.sum { |ids| ids.values.sum { |versions| versions.size } }
  total_entries = pokemon_data.values.sum do |ids|
    ids.values.sum do |versions|
      versions.values.sum { |langs| langs.size }
    end
  end
  
  puts "総ID数: #{total_ids}"
  puts "総バージョン数: #{total_versions}"  
  puts "総エントリ数: #{total_entries}"
  
  # 言語別統計
  puts "\n言語別データ数:"
  language_keys.each do |lang|
    count = pokemon_data.values.sum do |ids|
      ids.values.sum do |versions|
        versions.values.count { |langs| langs[lang] && !langs[lang].empty? }
      end
    end
    puts "  #{lang}: #{count}" if count > 0
  end
  
  db.close
end
