#!/usr/bin/env ruby
# usage: ruby DB2JSON.rb

require 'sqlite3'
require 'json'
require 'fileutils'
require 'set'

ROOT_DIR = File.expand_path('..', __dir__)

# 処理対象のバージョンリスト
# 形式: [メインバージョン, 参照元バージョン(省略可)]
TARGET_VERSIONS = [
  # ['Red_Green_Blue_Pikachu'],
  # ['Gold_Silver_Crystal'],
  # ['Ruby_Sapphire_Emerald'],
  # ['Firered_Leafgreen'],
  # ['Diamond_Pearl_Platinum'],
  # ['HeartGold_SoulSilver'],
  # ['Black_White'],
  # ['Black2_White2'],
  # ['X_Y'],
  # ['OmegaRuby_AlphaSapphire'],
  # ['Sun_Moon'],
  # ['UltraSun_UltraMoon'],
  # ['Let'sGoPikachu'],
  # ['Let'sGoEevee'],
  # ['Sword_Shield'],
  # ['LegendsArceus'],
  # ['BrilliantDiamond_ShiningPearl'],
  # ['Scarlet_Violet'],
  ['LegendsZA'],
  # ['PokémonGo'],
  # ['PokémonPinball'],
  # ['PokémonRanger'],
  # ['PokémonStadium'],
  # ['PokémonStadium2'],
  # ['New_Pokemon_Snap'],
]

target_versions = if ARGV.any?
  ARGV.map { |version| [version] }
else
  TARGET_VERSIONS
end

# 引数(例: "LegendsArceus" / "Legends_Arceus" / "legends_arceus")を
# データベース用のスネークケース(例: "legends_arceus")に正規化するメソッド
def normalize_version_name(name)
  return '' if name.nil?
  return name.downcase if name.include?('_')
  # CamelCase → snake_case
  # name.gsub(/([a-z])([A-Z])/, '\\1_\\2').downcase
  name.gsub(/([a-z])([A-Z])/, '\\1\\2').downcase
end

DB_PATH = File.join(ROOT_DIR, 'pokedex.db')

# 設定をJSONファイルから読み込み
begin
  content = File.read(File.join(ROOT_DIR, 'config', 'pokedex_config.json'))
  config = JSON.parse(content)
  REGION_MAPPING = config["region_mapping"]
  DEFAULT_REGION_VALUE = config["default_region_value"]
  VERSION_MAPPING = config["version_mapping"]
rescue JSON::ParserError => e
  STDERR.puts "pokedex_config.json のパースに失敗しました: #{e.message}"
  exit 1
rescue Errno::ENOENT => e
  STDERR.puts "pokedex_config.json が見つかりません: #{e.message}"
  exit 1
end

# TARGET_VERSIONSの各バージョンを処理
target_versions.each do |version_config|
  raw_version = version_config[0]
  version = normalize_version_name(raw_version)
  source_version = version_config[1] ? normalize_version_name(version_config[1]) : version
  priority_versions = [source_version, version].uniq
  
  puts "処理中: #{version}"
  
  out_dir = File.join(ROOT_DIR, 'pokedex', version)
  FileUtils.mkdir_p(out_dir)

  db = SQLite3::Database.new(DB_PATH)
  db.results_as_hash = true

  # 図鑑リストを取得
  pokedex_names = db.execute(<<~SQL, [version]).map { |r| r['pokedex'] }.uniq
    SELECT pokedex
      FROM local_pokedex
     WHERE version = ? COLLATE NOCASE
  SQL

  json_root = {
    'update'       => Time.now.strftime('%Y%m%d'),
    'game_version' => version,
    'pokedex'      => {}
  }

  version_verid_map = VERSION_MAPPING.each_with_object(Hash.new { |hash, key| hash[key] = {} }) do |(ver_id, info), memo|
    version_key = info['version']
    name_eng = info['name_eng']
    next if version_key.nil? || name_eng.nil?

    memo[version_key][name_eng] = ver_id
  end

  # テーブルごとに参照元バージョンを上書きできる設定
  # 例: 'firered_leafgreen' で種族値などを 'ruby_sapphire_emerald' から取る
  fallback_by_db = {
    # type: {
    #   'firered_leafgreen' => ['ruby_sapphire_emerald', 'emerald']
    # },
    # ability: {
    #   'firered_leafgreen' => ['ruby_sapphire_emerald', 'emerald']
    # },
    # status: {
    #   'firered_leafgreen' => ['ruby_sapphire_emerald', 'emerald']
    # },
    # description: {
    #   'firered_leafgreen' => ['ruby_sapphire_emerald', 'emerald']
    # }

    type: {
      'legendsza' => ['scarlet_violet', 'sword_shield', 'ultrasun_ultramoon', 'x_y']
    },
    # ability: {
    #   'firered_leafgreen' => ['ruby_sapphire_emerald', 'emerald']
    # },
    status: {
      'legendsza' => ['scarlet_violet', 'sword_shield', 'ultrasun_ultramoon', 'x_y']
    },
    # description: {
    #   'firered_leafgreen' => ['ruby_sapphire_emerald', 'emerald']
    # }
  }

  # 汎用ヘルパー: 優先バージョン順で最初にヒットした行を返す
  fetch_row_with_fallback = lambda do |db, table_key, base_key, sql, specific_versions = nil|
    # specific_versionsが指定されている場合はそれを使用し、
    # そうでなければfallback_by_dbから取得した配列を使用
    if specific_versions
      versions = specific_versions
    else
      fallback_versions = fallback_by_db.dig(table_key, version)
      if fallback_versions.is_a?(Array)
        versions = fallback_versions + [version]
      else
        versions = [fallback_versions, version].compact
      end
    end
    
    # デバッグ情報
    if table_key == :status
      puts "Status fallback: #{table_key}, versions: #{versions}"
      puts "  base_key: #{base_key}"
      puts "  SQL: #{sql.strip}"
    end
    versions.each do |v|
      if table_key == :status
        puts "  Trying version: #{v}"
        puts "  Params: #{base_key + [v]}"
      end
      row = db.get_first_row(sql, base_key + [v])
      if table_key == :status && row
        puts "  Found status in version: #{v}, hp: #{row['hp']}"
      end
      return row if row
    end
    nil
  end

  generate_pokemon_id = lambda do |no, region, spare1, spare2, gigantamax, mega_evolution, form, mf, out_of_index, shiny|
    region_value = REGION_MAPPING[region] || DEFAULT_REGION_VALUE
    
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

    if form.to_s.empty?
      form_value = '00'
    elsif form.to_s =~ /^\d+$/
      # If form is purely numeric (e.g., sequence index), use it directly with zero padding
      form_value = form.to_i.to_s.rjust(2, '0')
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
    pokemons = db.execute(<<~SQL, [version, pokedex_name])
      SELECT id, no, globalNo
        FROM local_pokedex
       WHERE version = ? COLLATE NOCASE AND pokedex = ?
    ORDER BY CAST(no AS INTEGER), id
    SQL

    # noをキーとした連想配列を作成
    json_root['pokedex'][pokedex_name] = {}
    
    # noでグループ化して処理
    pokemons.group_by { |p| p['no'] }.each do |no, pokemon_list|
      # 同じnoのポケモンリストからフォーム情報を取得
      forms = []
      pokemon_list.each do |pokemon|
        # 各ポケモンIDに対してフォーム情報を取得
        form_data = db.execute(<<~SQL, [version, pokedex_name, pokemon['globalNo'], pokemon['id']])
          SELECT *
            FROM local_pokedex
           WHERE version = ? COLLATE NOCASE
             AND pokedex = ?
             AND globalNo = ?
             AND id = ?
        SQL
        forms.concat(form_data)
      end
      
      # 重複を除去
      forms = forms.uniq { |f| [f['id'], f['form'], f['region'], f['mega_evolution'], f['gigantamax']] }

      # noをキーとしたハッシュを初期化（存在しない場合のみ）
      json_root['pokedex'][pokedex_name][no] ||= {}

      forms.each_with_index do |f, idx|
        # フォームごとの連番 (01, 02, ...) を生成
        form_seq = (idx).to_s.rjust(2, '0')

        key = [f['globalNo'], f['form'], f['region'],
               f['mega_evolution'], f['gigantamax']]

        type    = fetch_row_with_fallback.call(db, :type, key, <<~SQL)
             SELECT type1, type2
               FROM local_pokedex_type
              WHERE globalNo    = ?
                AND (form = ? OR form IS NULL OR form = '')
                AND (region = ? OR region IS NULL OR region = '')
                AND (mega_evolution = ? OR mega_evolution IS NULL OR mega_evolution = '')
                AND (gigantamax = ? OR gigantamax IS NULL OR gigantamax = '')
                AND version     = ? COLLATE NOCASE
           SQL

        ability = fetch_row_with_fallback.call(db, :ability, key, <<~SQL)
             SELECT ability1, ability2, dream_ability
               FROM local_pokedex_ability
              WHERE globalNo    = ?
                AND (form = ? OR form IS NULL OR form = '')
                AND (region = ? OR region IS NULL OR region = '')
                AND (mega_evolution = ? OR mega_evolution IS NULL OR mega_evolution = '')
                AND (gigantamax = ? OR gigantamax IS NULL OR gigantamax = '')
                AND version     = ? COLLATE NOCASE
           SQL

        stats = fetch_row_with_fallback.call(db, :status, key, <<~SQL) || {}
             SELECT hp, attack, defense, special_attack,
                    special_defense, speed
               FROM local_pokedex_status
              WHERE globalNo    = ?
                AND (form = ? OR form IS NULL OR form = '')
                AND (region = ? OR region IS NULL OR region = '')
                AND (mega_evolution = ? OR mega_evolution IS NULL OR mega_evolution = '')
                AND (gigantamax = ? OR gigantamax IS NULL OR gigantamax = '')
                AND version     = ? COLLATE NOCASE
           SQL

        # 対応するバージョン名を自動取得 (例: x_y → ["x","y"])
        desc_versions = version.split('_')
        descriptions = {}
        desc_versions.each do |ver|
          sql = <<~SQL
            SELECT dex
              FROM pokedex_description_dex
             WHERE globalNo = ?
               AND verID = ?
               AND language = 'jpn'
          SQL
          ver_id = version_verid_map.dig(version, ver)
          desc_row = ver_id ? db.get_first_row(sql, [f['globalNo'], ver_id]) : nil

          descriptions[ver] = desc_row ? desc_row['dex'] : ''
        end

        # フォームデータをハッシュとして作成
        form_data = {
          'id'            => f['id'],
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
        
        # IDをキーとしてハッシュ形式でフォームデータを格納
        json_root['pokedex'][pokedex_name][no][f['id']] = form_data
      end
    end
  end

  File.write(File.join(out_dir, "#{version}.json"),
             JSON.pretty_generate(json_root), mode: 'w:UTF-8')
  puts "→ #{out_dir}/#{version}.json を生成しました"
  
  db.close
end

puts "全てのバージョンの処理が完了しました"