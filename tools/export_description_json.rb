#!/usr/bin/env ruby
# export_description_json.rb
# pokedex.dbのlocal_pokedex_descriptionテーブルから description.json を生成するスクリプト

require 'sqlite3'
require 'json'
require 'time'

ROOT_DIR = File.expand_path('..', __dir__)
DB_PATH = File.join(ROOT_DIR, 'pokedex.db')
OUT_PATH = File.join(ROOT_DIR, 'description.json')

if __FILE__ == $0
  db = SQLite3::Database.new(DB_PATH)
  
  # バージョン配列（CreateDB.rbと同じ）
  version_array = [
    "red",
    "green", 
    "blue",
    "pikachu",
    "pokemonpinball",
    "pokemonstadium",
    "gold",
    "silver",
    "crystal",
    "ruby",
    "sapphire",
    "emerald",
    "firered",
    "leafgreen",
    "diamond",
    "pearl",
    "platinum",
    "heartgold",
    "soulsilver",
    "black",
    "black_kanji",
    "white",
    "white_kanji",
    "black2",
    "black2_kanji",
    "white2",
    "white2_kanji",
    "x",
    "x_kanji",
    "y",
    "y_kanji",
    "omegaruby",
    "omegaruby_kanji",
    "alphasapphire",
    "alphasapphire_kanji",
    "sun",
    "sun_kanji",
    "moon",
    "moon_kanji",
    "ultrasun",
    "ultrasun_kanji",
    "ultramoon",
    "ultramoon_kanji",
    "letsgopikachu",
    "letsgopikachu_kanji",
    "letsgoeevee",
    "letsgoeevee_kanji",
    "sword",
    "sword_kanji",
    "shield",
    "shield_kanji",
    "newpokemonsnap",
    "brilliantdiamond",
    "brilliantdiamond_kanji",
    "shiningpearl",
    "shiningpearl_kanji",
    "legendsarceus_h",
    "legendsarceus",
    "scarlet_h",
    "scarlet",
    "violet_h",
    "violet",
    "legendsza"
  ]
  
  puts "データベースからデータを取得中..."
  
  # ポケモンごとにまとめるためのハッシュ
  pokemon_data = {}
  
  # ユニークなポケモンのIDリストを取得（globalNo, idでソート）
  unique_pokemon_sql = <<-SQL
    SELECT DISTINCT id, globalNo, form, region, mega_evolution, gigantamax
    FROM local_pokedex_description
    ORDER BY CAST(globalNo AS INTEGER) ASC, id ASC
  SQL
  
  unique_pokemon = db.execute(unique_pokemon_sql)
  
  unique_pokemon.each do |row|
    id, global_no, form, region, mega_evolution, gigantamax = row
    
    # ポケモンデータの基本構造を作成
    pokemon_key = id
    pokemon_data[pokemon_key] = {
      "id" => id,
      "globalNo" => global_no,
      "form" => form || "",
      "region" => region || "",
      "mega_evolution" => mega_evolution || "",
      "gigantamax" => gigantamax || ""
    }
    
    # 各バージョンの説明データを初期化
    version_array.each do |version|
      pokemon_data[pokemon_key][version] = ""
    end
  end
  
  # 図鑑説明データを取得
  description_sql = <<-SQL
    SELECT id, ver, description
    FROM local_pokedex_description
    WHERE language = 'jpn'
    ORDER BY CAST(globalNo AS INTEGER) ASC, id ASC
  SQL
  
  descriptions = db.execute(description_sql)
  
  descriptions.each do |row|
    id, ver, description = row
    
    if pokemon_data[id] && version_array.include?(ver) && description && !description.empty?
      pokemon_data[id][ver] = description
    end
  end
  
  # JSONデータ構造を構築（IDをキーとしたオブジェクト形式）
  description_object = {}
  pokemon_data.each do |id, data|
    # idフィールドを除外してオブジェクトに追加
    description_object[id] = data.reject { |key, value| key == "id" }
  end
  
  output_data = {
    "update" => Time.now.strftime("%Y%m%d%H%M"),
    "description" => description_object
  }
  
  # JSON出力
  puts "description.jsonファイルを生成中..."
  
  File.open(OUT_PATH, 'w:UTF-8') do |file|
    file.write(JSON.pretty_generate(output_data, indent: '  '))
  end
  
  puts "description.jsonが正常に生成されました"
  puts "総ポケモン数: #{pokemon_data.size}"
  
  # 統計情報を表示
  version_counts = {}
  version_array.each do |version|
    count = pokemon_data.values.count { |p| !p[version].nil? && !p[version].empty? }
    version_counts[version] = count if count > 0
  end
  
  puts "\nバージョン別説明データ数:"
  version_counts.each do |version, count|
    puts "  #{version}: #{count}"
  end
  
  db.close
end
