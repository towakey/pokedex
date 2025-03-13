require 'sqlite3'
require 'json'

class DataImporter
  def initialize(db_path = 'pokedex.db')
    @db_path = db_path
    @sqlite = SQLite3::Database.open(@db_path)
    create_version_table
    @pokedex_name = {}
    @pokedex_name["kanto"] = "カントー図鑑"
    @pokedex_name["johto"] = "ジョウト図鑑"
    @pokedex_name["hoenn"] = "ホウエン図鑑"
    @pokedex_name["sinnoh"] = "シンオウ図鑑"
    @pokedex_name["unova_bw"] = "イッシュ図鑑"
    @pokedex_name["unova_b2w2"] = "イッシュ図鑑"
    @pokedex_name["central_kalos"] = "セントラルカロス図鑑"
    @pokedex_name["coast_kalos"] = "コーストカロス図鑑"
    @pokedex_name["mountain_kalos"] = "マウンテンカロス図鑑"
    @pokedex_name["alola_sm"] = "アローラ図鑑"
    @pokedex_name["alola_usum"] = "アローラ図鑑"
    @pokedex_name["galar"] = "ガラル図鑑"
    @pokedex_name["crown_tundra"] = "カンムリ雪原図鑑"
    @pokedex_name["isle_of_armor"] = "ヨロイ島図鑑"
    @pokedex_name["hisui"] = "ヒスイ図鑑"
    @pokedex_name["paldea"] = "パルデア図鑑"
    @pokedex_name["kitakami"] = "キタカミ図鑑"
    @pokedex_name["blueberry"] = "ブルーベリー図鑑"

    @version_name = {}
    @version_name["kanto"] = ["red", "green", "blue", "pikachu"]
    @version_name["johto"] = ["gold", "silver", "crystal"]
    @version_name["hoenn"] = ["ruby", "sapphire", "emerald"]
    @version_name["sinnoh"] = ["diamond", "pearl", "platinum"]
    @version_name["unova_bw"] = ["black", "white"]
    @version_name["unova_b2w2"] = ["black2", "white2"]
    @version_name["central_kalos"] = ["x", "y"]
    @version_name["coast_kalos"] = ["x", "y"]
    @version_name["mountain_kalos"] = ["x", "y"]
    @version_name["alola_sm"] = ["sun", "moon"]
    @version_name["alola_usum"] = ["ultra_sun", "ultra_moon"]
    @version_name["galar"] = ["sword", "shield"]
    @version_name["crown_tundra"] = ["sword", "shield"]
    @version_name["isle_of_armor"] = ["sword", "shield"]
    @version_name["hisui"] = ["legends_arceus"]
    @version_name["paldea"] = ["scarlet", "violet"]
    @version_name["kitakami"] = ["scarlet", "violet"]
    @version_name["blueberry"] = ["scarlet", "violet"]
  end

  def import(json_path, table_name, area)
    @table_name = table_name
    @data = load_json(json_path)
    @area = area
    
    @sqlite.transaction
    begin
      create_tables(@table_name, @area)
      import_data
      record_version(@table_name, @data["update"])
      @sqlite.commit
    rescue SQLite3::Exception => e
      @sqlite.rollback
      raise e
    end
  end

  private

  def load_json(json_path)
    JSON.parse(File.read(json_path))
  end

  def escape_sql_string(str)
    return '' if str.nil?
    str.to_s.gsub("'", "''")
  end

  def create_tables(table_name, area)
    case area
    when 'global'
      create_global_table(table_name)
    when 'local'
      create_local_table(table_name)
    else
      raise ArgumentError, "Unknown area: #{area}"
    end
  end

  def create_global_table(table_name)
    # オーバーライドして実装
    raise NotImplementedError, "Subclass must implement create_global_table"
  end

  def create_local_table(table_name)
    sql = <<SQL
DROP TABLE IF EXISTS #{table_name};
SQL
    @sqlite.execute(sql)

    # バージョン固有のカラムを生成
    version_columns = @version_name[table_name].map do |version|
      "#{version} TEXT"
    end.join(",\n    ")

    sql = <<SQL
CREATE TABLE #{table_name} (
    no TEXT,
    globalNo TEXT,
    form TEXT,
    type1 TEXT,
    type2 TEXT,
    hp INTEGER,
    attack INTEGER,
    defense INTEGER,
    special_attack INTEGER,
    special_defense INTEGER,
    speed INTEGER,
    ability1 TEXT,
    ability2 TEXT,
    dream_ability TEXT,
    #{version_columns}
);
SQL
    @sqlite.execute(sql)
  end

  def create_version_table
    sql = <<SQL
CREATE TABLE IF NOT EXISTS version (
    table_name TEXT,
    update_date TEXT,
    created_at TEXT
);
SQL
    @sqlite.execute(sql)
  end

  def import_data
    # オーバーライドして実装
    raise NotImplementedError, "Subclass must implement import_data"
  end

  def record_version(table_name, update_date)
    update_date = update_date || Time.now.to_s
    sql = <<SQL
INSERT INTO version (table_name, update_date, created_at)
VALUES (
    '#{escape_sql_string(table_name)}',
    '#{escape_sql_string(update_date)}',
    '#{escape_sql_string(Time.now.to_s)}'
);
SQL
    @sqlite.execute(sql)
  end
end

class PokedexImporter < DataImporter
  private

  def create_global_table(table_name)
    sql = <<SQL
DROP TABLE IF EXISTS #{@table_name};
SQL
    @sqlite.execute(sql)

    sql = <<SQL
CREATE TABLE #{@table_name} (
    no TEXT,
    form TEXT,
    region TEXT,
    mega_evolution TEXT,
    gigantamax TEXT,
    jpn TEXT,
    eng TEXT,
    ger TEXT,
    fra TEXT,
    kor TEXT,
    chs TEXT,
    cht TEXT,
    classification TEXT,
    height TEXT,
    weight TEXT
);
SQL
    @sqlite.execute(sql)
  end

  def create_local_table(table_name)
    sql = <<SQL
DROP TABLE IF EXISTS #{@table_name};
SQL
    @sqlite.execute(sql)

    # バージョン固有のカラムを生成
    version_columns = @version_name[table_name].map do |version|
      "#{version} TEXT"
    end.join(",\n    ")

    sql = <<SQL
CREATE TABLE #{@table_name} (
    no TEXT,
    globalNo TEXT,
    form TEXT,
    region TEXT,
    mega_evolution TEXT,
    gigantamax TEXT,
    type1 TEXT,
    type2 TEXT,
    hp INTEGER,
    attack INTEGER,
    defense INTEGER,
    special_attack INTEGER,
    special_defense INTEGER,
    speed INTEGER,
    ability1 TEXT,
    ability2 TEXT,
    dream_ability TEXT,
    #{version_columns}
);
SQL
    @sqlite.execute(sql)
  end

  def import_data
    case @area
    when 'global'
      import_global_data
    when 'local'
      import_local_data
    else
      raise ArgumentError, "Unknown area: #{@area}"
    end
  end

  def import_global_data
    @data["pokedex"].each do |pokemon|
      import_base_form(pokemon)
      import_region_forms(pokemon)
      import_mega_evolutions(pokemon)
      import_gigantamax_forms(pokemon)
    end
  end

  def import_local_data
    @data["pokedex"][@pokedex_name[@table_name]].each do |pokemon|
      # puts "importing : #{pokemon}"
      import_local_pokemon(pokemon)
    end
  end

  def import_local_pokemon(pokemon)
    pokemon["status"].each do |form|
      # バージョン固有のカラム値を生成
      version_values = @version_name[@table_name].map do |version|
        "'#{escape_sql_string(form["description"][version] || "")}'"
      end.join(",\n    ")

      sql = <<SQL
INSERT INTO #{@table_name}
VALUES (
    '#{pokemon['no']}',
    '#{pokemon['globalNo']}',
    '#{escape_sql_string(form['form'])}',
    '#{escape_sql_string(form['region'])}',
    '#{escape_sql_string(form['mega_evolution'])}',
    '#{escape_sql_string(form['gigantamax'])}',
    '#{escape_sql_string(form['type1'])}',
    '#{escape_sql_string(form['type2'])}',
    #{form['hp']},
    #{form['attack']},
    #{form['defense']},
    #{form['special_attack']},
    #{form['special_defense']},
    #{form['speed']},
    '#{escape_sql_string(form['ability1'])}',
    '#{escape_sql_string(form['ability2'])}',
    '#{escape_sql_string(form['dream_ability'])}',
    #{version_values}
);
SQL
      @sqlite.execute(sql)
    end
  end

  def import_base_form(pokemon)
    return unless pokemon["form"]
    pokemon["form"].each do |form|
      sql = <<SQL
INSERT INTO #{@table_name}
VALUES (
    '#{pokemon['no']}',
    '#{escape_sql_string(form['form'])}',
    '#{escape_sql_string(form['region'])}',
    '#{escape_sql_string(form['mega_evolution'])}',
    '#{escape_sql_string(form['gigantamax'])}',
    '#{escape_sql_string((form['name'] && form['name']['jpn']) || pokemon['name']['jpn'])}',
    '#{escape_sql_string((form['name'] && form['name']['eng']) || pokemon['name']['eng'])}',
    '#{escape_sql_string((form['name'] && form['name']['ger']) || pokemon['name']['ger'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['fra']) || pokemon['name']['fra'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['kor']) || pokemon['name']['kor'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['chs']) || pokemon['name']['chs'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['cht']) || pokemon['name']['cht'] || '')}',
    '#{escape_sql_string(form['classification'])}',
    '#{escape_sql_string(form['height'])}',
    '#{escape_sql_string(form['weight'])}'
);
SQL
      @sqlite.execute(sql)
    end
  end

  def import_region_forms(pokemon)
    return unless pokemon["region_form"]
    pokemon["region_form"].each do |form|
      sql = <<SQL
INSERT INTO #{@table_name}
VALUES (
    '#{pokemon['no']}',
    '#{escape_sql_string(form['form'])}',
    '#{escape_sql_string(form['region'])}',
    '#{escape_sql_string(form['mega_evolution'])}',
    '#{escape_sql_string(form['gigantamax'])}',
    '#{escape_sql_string(pokemon['name']['jpn'])}',
    '#{escape_sql_string(pokemon['name']['eng'])}',
    '#{escape_sql_string(pokemon['name']['ger'])}',
    '#{escape_sql_string(pokemon['name']['fra'])}',
    '#{escape_sql_string(pokemon['name']['kor'])}',
    '#{escape_sql_string(pokemon['name']['chs'])}',
    '#{escape_sql_string(pokemon['name']['cht'])}',
    '#{escape_sql_string(form['classification'])}',
    '#{escape_sql_string(form['height'])}',
    '#{escape_sql_string(form['weight'])}'
);
SQL
      @sqlite.execute(sql)
    end
  end

  def import_mega_evolutions(pokemon)
    return unless pokemon["mega_evolution"]
    pokemon["mega_evolution"].each do |form|
      sql = <<SQL
INSERT INTO #{@table_name}
VALUES (
    '#{pokemon['no']}',
    '#{escape_sql_string(form['form'])}',
    '#{escape_sql_string(form['region'])}',
    '#{escape_sql_string(form['mega_evolution'])}',
    '#{escape_sql_string(form['gigantamax'])}',
    '#{escape_sql_string((form['name'] && form['name']['jpn']) || pokemon['name']['jpn'])}',
    '#{escape_sql_string((form['name'] && form['name']['eng']) || pokemon['name']['eng'])}',
    '#{escape_sql_string((form['name'] && form['name']['ger']) || pokemon['name']['ger'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['fra']) || pokemon['name']['fra'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['kor']) || pokemon['name']['kor'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['chs']) || pokemon['name']['chs'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['cht']) || pokemon['name']['cht'] || '')}',
    '#{escape_sql_string(form['classification'])}',
    '#{escape_sql_string(form['height'])}',
    '#{escape_sql_string(form['weight'])}'
);
SQL
      @sqlite.execute(sql)
    end
  end

  def import_gigantamax_forms(pokemon)
    return unless pokemon["gigantamax"]
    pokemon["gigantamax"].each do |form|
      sql = <<SQL
INSERT INTO #{@table_name}
VALUES (
    '#{pokemon['no']}',
    '#{escape_sql_string(form['form'])}',
    '#{escape_sql_string(form['region'])}',
    '#{escape_sql_string(form['mega_evolution'])}',
    '#{escape_sql_string(form['gigantamax'])}',
    '#{escape_sql_string((form['name'] && form['name']['jpn']) || pokemon['name']['jpn'])}',
    '#{escape_sql_string((form['name'] && form['name']['eng']) || pokemon['name']['eng'])}',
    '#{escape_sql_string((form['name'] && form['name']['ger']) || pokemon['name']['ger'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['fra']) || pokemon['name']['fra'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['kor']) || pokemon['name']['kor'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['chs']) || pokemon['name']['chs'] || '')}',
    '#{escape_sql_string((form['name'] && form['name']['cht']) || pokemon['name']['cht'] || '')}',
    '#{escape_sql_string(form['classification'])}',
    '#{escape_sql_string(form['height'])}',
    '#{escape_sql_string(form['weight'])}'
);
SQL
      @sqlite.execute(sql)
    end
  end
end

class WazaImporter < DataImporter
  private

  def create_global_table(table_name)
    @sqlite.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS waza (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        region TEXT,               -- 地方名（kanto, johto等）
        pokedex_no TEXT,          -- 地方図鑑No
        global_no TEXT,           -- 全国図鑑No
        form TEXT,                -- フォーム名
        learn_type TEXT,          -- 習得方法（initial:初期技, remember:思い出し, evolution:進化時, level:レベル技, machine:わざマシン）
        level INTEGER,            -- レベル（レベル技の場合のみ）
        waza_name TEXT,           -- 技名
        UNIQUE(region, pokedex_no, global_no, form, learn_type, level, waza_name)
      )
    SQL
  end

  def import_data
    # puts @data['game_version']
    # region = File.basename(File.dirname(@data['game_version']))
    region = @data['game_version']
    
    @data['waza'].each do |pokedex_name, pokedex_data|
      puts "Importing waza data for #{pokedex_name}..."
      region = pokedex_name
      pokedex_data.each do |pokedex_no, pokemon_data|
        global_no = pokemon_data['globalNo']
        
        # フォーム別のデータを処理
        forms = pokemon_data.keys - ['globalNo']
        forms.each do |form|
          waza_data = pokemon_data[form]
          
          # 初期技
          insert_waza(region, pokedex_no, global_no, form, 'initial', nil, waza_data[''])
          
          # 思い出し技
          insert_waza(region, pokedex_no, global_no, form, 'remember', nil, waza_data['思い出し'])
          
          # 進化時
          insert_waza(region, pokedex_no, global_no, form, 'evolution', nil, waza_data['進化時'])
          
          # レベル技
          waza_data.each do |key, moves|
            next unless key.match?(/^\d+$/)
            insert_waza(region, pokedex_no, global_no, form, 'level', key.to_i, moves)
          end
          
          # わざマシン
          insert_waza(region, pokedex_no, global_no, form, 'machine', nil, waza_data['わざマシン']) if waza_data['わざマシン']
        end
      end
      # バージョン情報を記録
      record_version("waza_#{region}", @data["update"])
    end

  end

  private

  def insert_waza(region, pokedex_no, global_no, form, learn_type, level, moves)
    return if moves.nil? || moves.empty?
    
    moves.each do |waza_name|
      @sqlite.execute(<<-SQL, [region, pokedex_no, global_no, form, learn_type, level, waza_name])
        INSERT OR IGNORE INTO waza 
        (region, pokedex_no, global_no, form, learn_type, level, waza_name)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      SQL
    end
  rescue SQLite3::Exception => e
    puts "Error occurred while inserting waza: #{e.message}"
    puts "Data: #{[region, pokedex_no, global_no, form, learn_type, level, waza_name].inspect}"
  end
end

# スクリプトの実行
if __FILE__ == $0
  pokedex = PokedexImporter.new
  pokedex.import('./pokedex/pokedex.json', 'pokedex', 'global')
  pokedex.import('./pokedex/Red_Green_Blue_Yellow/Red_Green_Blue_Yellow.json', 'kanto', 'local')
  pokedex.import('./pokedex/Gold_Silver_Crystal/Gold_Silver_Crystal.json', 'johto', 'local')
  pokedex.import('./pokedex/Ruby_Sapphire_Emerald/Ruby_Sapphire_Emerald.json', 'hoenn', 'local')
  pokedex.import('./pokedex/Diamond_Pearl_Platinum/Diamond_Pearl_Platinum.json', 'sinnoh', 'local')
  pokedex.import('./pokedex/Black_White/Black_White.json', 'unova_bw', 'local')
  pokedex.import('./pokedex/Black2_White2/Black2_White2.json', 'unova_b2w2', 'local')
  pokedex.import('./pokedex/X_Y/X_Y.json', 'central_kalos', 'local')
  pokedex.import('./pokedex/X_Y/X_Y.json', 'coast_kalos', 'local')
  pokedex.import('./pokedex/X_Y/X_Y.json', 'mountain_kalos', 'local')
  pokedex.import('./pokedex/Sun_Moon/Sun_Moon.json', 'alola_sm', 'local')
  pokedex.import('./pokedex/UltraSun_UltraMoon/UltraSun_UltraMoon.json', 'alola_usum', 'local')
  pokedex.import('./pokedex/Sword_Shield/Sword_Shield.json', 'galar', 'local')
  pokedex.import('./pokedex/Sword_Shield/Sword_Shield.json', 'crown_tundra', 'local')
  pokedex.import('./pokedex/Sword_Shield/Sword_Shield.json', 'isle_of_armor', 'local')
  pokedex.import('./pokedex/LegendsArceus/LegendsArceus.json', 'hisui', 'local')
  pokedex.import('./pokedex/Scarlet_Violet/Scarlet_Violet.json', 'paldea', 'local')
  pokedex.import('./pokedex/Scarlet_Violet/Scarlet_Violet.json', 'kitakami', 'local')
  pokedex.import('./pokedex/Scarlet_Violet/Scarlet_Violet.json', 'blueberry', 'local')

  # Dir.glob("pokedex/*").each do |dir|
  #   region = File.basename(dir)
  #   next unless File.directory?(dir)
    
  #   pokedex_file = "#{dir}/pokedex.json"
  #   if File.exist?(pokedex_file)
  #     puts "Importing #{pokedex_file}..."
  #     importer.import(pokedex_file, region, 'local')
  #   end
  # end

  # 技データのインポート
  waza = WazaImporter.new
  Dir.glob("pokedex/*/waza.json").each do |file_path|
    puts "Importing #{file_path}..."
    waza.import(file_path, 'waza', 'global')
  end
end