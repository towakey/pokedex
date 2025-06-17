# CreateDB.rb
require 'sqlite3'
require 'json'

if __FILE__ == $0
  db = SQLite3::Database.new('pokedex.db')
  begin
    db.transaction
    
    puts "load for pokedex.json"
    # load for pokedex.json
    # table:pokedex
    # Drop tables
    db.execute("DROP TABLE IF EXISTS pokedex")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS pokedex (
        id TEXT,
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        height TEXT,
        weight TEXT
      )
    SQL

    # table:pokedex_name
    # Drop tables
    db.execute("DROP TABLE IF EXISTS pokedex_name")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS pokedex_name (
        id TEXT,
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        language TEXT,
        name TEXT
      )
    SQL

    # table:pokedex_classification
    # Drop tables
    db.execute("DROP TABLE IF EXISTS pokedex_classification")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS pokedex_classification (
        id TEXT,
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        language TEXT,
        classification TEXT
      )
    SQL

    # JSONパースエラー時にファイル内容を表示しないようにする
    begin
      content = File.read("./pokedex/pokedex.json")
      pokedex_json = JSON.parse(content)
    rescue JSON::ParserError => e
      STDERR.puts "pokedex.json のパースに失敗しました: #{e.message}"
      exit 1
    end

    pokedex_json["pokedex"].each do |pokemon|
      pokemon["form"].each do |form|
        db.execute(
          "INSERT INTO pokedex (id, globalNo, form, region, mega_evolution, gigantamax, height, weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
          [
            form['id'],
            pokemon['globalNo'].to_s.rjust(4, '0'),
            form['form'],
            form['region'],
            form['mega_evolution'],
            form['gigantamax'],
            form['height'],
            form['weight']
          ]
        )

        db.execute(
          "INSERT INTO pokedex_classification (id, globalNo, form, region, mega_evolution, gigantamax, language, classification) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
          [
            form['id'],
            pokemon['globalNo'].to_s.rjust(4, '0'),
            form['form'],
            form['region'],
            form['mega_evolution'],
            form['gigantamax'],
            # form['language'],
            'jpn',                  # 現時点では日本語のみ
            form['classification']['jpn']
          ]
        )

        if form['name'] then
          form['name'].each do |language, name|
            db.execute(
              "INSERT INTO pokedex_name (id, globalNo, form, region, mega_evolution, gigantamax, language, name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
              [
                form['id'],
                pokemon['globalNo'].to_s.rjust(4, '0'),
                form['form'],
                form['region'],
                form['mega_evolution'],
                form['gigantamax'],
                language,
                name
              ]
            )
          end
        else
          pokemon['name'].each do |language, name|
            db.execute(
              "INSERT INTO pokedex_name (id, globalNo, form, region, mega_evolution, gigantamax, language, name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
              [
                form['id'],
                pokemon['globalNo'].to_s.rjust(4, '0'),
                form['form'],
                form['region'],
                form['mega_evolution'],
                form['gigantamax'],
                language,
                name
              ]
            )
          end
        end
      end
    end

    # -----------------------------------------------------
    puts "load for local pokedex"
    # load for local pokedex
    # table:local_pokedex
    # Drop tables
    db.execute("DROP TABLE IF EXISTS local_pokedex")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS local_pokedex (
        id TEXT,
        no TEXT,
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        version TEXT,
        pokedex TEXT
      )
    SQL

    # table:local_pokedex_type
    # Drop tables
    db.execute("DROP TABLE IF EXISTS local_pokedex_type")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS local_pokedex_type (
        id TEXT,
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        version TEXT,
        type1 TEXT,
        type2 TEXT
      )
    SQL

    # table:local_pokedex_ability
    # Drop tables
    db.execute("DROP TABLE IF EXISTS local_pokedex_ability")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS local_pokedex_ability (
        id TEXT,
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        version TEXT,
        ability1 TEXT,
        ability2 TEXT,
        dream_ability TEXT
      )
    SQL

    # table:local_pokedex_status
    # Drop tables
    db.execute("DROP TABLE IF EXISTS local_pokedex_status")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS local_pokedex_status (
        id TEXT,
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        version TEXT,
        hp INTEGER,
        attack INTEGER,
        defense INTEGER,
        special_attack INTEGER,
        special_defense INTEGER,
        speed INTEGER
      )
    SQL

    # table:local_pokedex_description
    # Drop tables
    db.execute("DROP TABLE IF EXISTS local_pokedex_description")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS local_pokedex_description (
        id TEXT,
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        version TEXT,
        language TEXT,
        description TEXT
      )
    SQL

    local_pokedex_array = {}
    local_pokedex_array["red_green_blue_pikachu"] = ["カントー図鑑"]
    local_pokedex_array["gold_silver_crystal"] = ["ジョウト図鑑"]
    local_pokedex_array["ruby_sapphire_emerald"] = ["ホウエン図鑑"]
    local_pokedex_array["firered_leafgreen"] = ["カントー図鑑"]
    local_pokedex_array["diamond_pearl_platinum"] = ["シンオウ図鑑"]
    local_pokedex_array["heartgold_soulsilver"] = ["ジョウト図鑑"]
    local_pokedex_array["black_white"] = ["イッシュ図鑑"]
    local_pokedex_array["black2_white2"] = ["イッシュ図鑑"]
    local_pokedex_array["x_y"] = ["セントラルカロス図鑑", "コーストカロス図鑑", "マウンテンカロス図鑑"]
    local_pokedex_array["sun_moon"] = ["アローラ図鑑"]
    local_pokedex_array["UltraSun_UltraMoon"] = ["アローラ図鑑"]
    local_pokedex_array["sword_shield"] = ["ガラル図鑑", "カンムリ雪原図鑑", "ヨロイ島図鑑"]
    local_pokedex_array["LegendsArceus"] = ["ヒスイ図鑑"]
    local_pokedex_array["Scarlet_Violet"] = ["パルデア図鑑", "キタカミ図鑑", "ブルーベリー図鑑"]

    local_pokedex_array.each do |game_version, local_pokedex|
      # JSONパースエラー時にファイル内容を表示しないようにする
      puts "load for #{game_version}.json"
      begin
        content = File.read("./pokedex/#{game_version}/#{game_version}.json")
        pokedex_json = JSON.parse(content)
      rescue JSON::ParserError => e
        STDERR.puts "#{game_version}.json のパースに失敗しました: #{e.message}"
        exit 1
      end

      local_pokedex.each do |pokedex_name|  
        pokedex_json["pokedex"][pokedex_name].each do |pokemon|
          pokemon["status"].each do |form|
            db.execute(
              "INSERT INTO local_pokedex (id, no, globalNo, form, region, mega_evolution, gigantamax, version, pokedex) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
              [
                form['id'],
                pokemon['no'].to_s.rjust(4, '0'),
                pokemon['globalNo'].to_s.rjust(4, '0'),
                form['form'],
                form['region'],
                form['mega_evolution'],
                form['gigantamax'],
                game_version,
                pokedex_name
              ]
            )

            db.execute(
              "INSERT INTO local_pokedex_type (id, globalNo, form, region, mega_evolution, gigantamax, version, type1, type2)
              SELECT ?,?,?,?,?,?,?,?,?
              WHERE NOT EXISTS (
                SELECT 1 FROM local_pokedex_type
                WHERE id = ? AND globalNo = ? AND form = ? AND region = ? AND mega_evolution = ? AND gigantamax = ? AND version = ?
              )",
              [
                form['id'],
                pokemon['globalNo'].to_s.rjust(4, '0'),
                form['form'],
                form['region'],
                form['mega_evolution'],
                form['gigantamax'],
                game_version,
                if form['type1'] && form['type2'] && form['type1'] != form['type2']
                  # タイプの順序を定義
                  type_order = [
                    'ノーマル', 'ほのお', 'みず', 'でんき', 'くさ', 'こおり', 'かくとう', 'どく', 'じめん', 
                    'ひこう', 'エスパー', 'むし', 'いわ', 'ゴースト', 'ドラゴン', 'あく', 'はがね', 'フェアリー'
                  ]
                  type1 = form['type1']
                  type2 = form['type2']
                  
                  # タイプが存在するかチェック
                  idx1 = type_order.index(type1)
                  idx2 = type_order.index(type2)
                  
                  # 両方のタイプが存在する場合のみ順序を入れ替え
                  if idx1 && idx2
                    idx1 > idx2 ? [type2, type1] : [type1, type2]
                  else
                    # いずれかのタイプが存在しない場合はそのままの順序で返す
                    [form['type1'], form['type2']]
                  end
                else
                  [form['type1'], form['type2']]
                end,
                # 重複チェック用のパラメータ
                form['id'],
                pokemon['globalNo'].to_s.rjust(4, '0'),
                form['form'],
                form['region'],
                form['mega_evolution'],
                form['gigantamax'],
                game_version
              ].flatten
            )

            db.execute(
              "INSERT INTO local_pokedex_ability (id, globalNo, form, region, mega_evolution, gigantamax, version, ability1, ability2, dream_ability) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
              [
                form['id'],
                pokemon['globalNo'].to_s.rjust(4, '0'),
                form['form'],
                form['region'],
                form['mega_evolution'],
                form['gigantamax'],
                game_version,
                form['ability1'],
                form['ability2'],
                form['dream_ability']
              ]
            )

            db.execute(
              "INSERT INTO local_pokedex_status (id, globalNo, form, region, mega_evolution, gigantamax, version, hp, attack, defense, special_attack, special_defense, speed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
              [
                form['id'],
                pokemon['globalNo'].to_s.rjust(4, '0'),
                form['form'],
                form['region'],
                form['mega_evolution'],
                form['gigantamax'],
                game_version,
                form['hp'],
                form['attack'],
                form['defense'],
                form['special_attack'],
                form['special_defense'],
                form['speed']
              ]
            )

            form['description'].each do |language, description|
              if description == "" or description == nil then
                next
              end
              db.execute(
                "INSERT INTO local_pokedex_description (id, globalNo, form, region, mega_evolution, gigantamax, version, language, description)
                SELECT ?,?,?,?,?,?,?,?,? 
                WHERE NOT EXISTS ( 
                  SELECT 1 FROM local_pokedex_description 
                  WHERE id = ? AND globalNo = ? AND form = ? AND region = ? AND mega_evolution = ? AND gigantamax = ? AND version = ? AND language = ? 
                )",
                [
                  form['id'],
                  pokemon['globalNo'].to_s.rjust(4, '0'),
                  form['form'],
                  form['region'],
                  form['mega_evolution'],
                  form['gigantamax'],
                  language,
                  'jpn',
                  description,
                  # 重複チェック用のパラメータ
                  form['id'],
                  pokemon['globalNo'].to_s.rjust(4, '0'),
                  form['form'],
                  form['region'],
                  form['mega_evolution'],
                  form['gigantamax'],
                  language,
                  'jpn'
                ]
              )
            end
          end
        end
      end
    end

    # -----------------------------------------------------
    puts "load for local waza list"
    # load for local waza list
    # table:local_waza
    # Drop tables
    db.execute("DROP TABLE IF EXISTS local_waza")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS local_waza (
        waza TEXT,
        version TEXT,
        type TEXT,
        category TEXT,
        pp TEXT,
        power TEXT,
        accuracy TEXT
      )
    SQL

    # table:local_waza_language
    # Drop tables
    db.execute("DROP TABLE IF EXISTS local_waza_language")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS local_waza_language (
        waza TEXT,
        version TEXT,
        language TEXT,
        name TEXT,
        description TEXT
      )
    SQL

    local_waza_array = {}
    local_waza_array["red_green_blue_pikachu"] = ["カントー図鑑"]
    local_waza_array["gold_silver_crystal"] = ["ジョウト図鑑"]
    local_waza_array["ruby_sapphire_emerald"] = ["ホウエン図鑑"]
    local_waza_array["diamond_pearl_platinum"] = ["シンオウ図鑑"]
    local_waza_array["black_white"] = ["イッシュ図鑑"]
    local_waza_array["black2_white2"] = ["イッシュ図鑑"]
    local_waza_array["x_y"] = ["セントラルカロス図鑑", "コーストカロス図鑑", "マウンテンカロス図鑑"]
    local_waza_array["sun_moon"] = ["アローラ図鑑"]
    local_waza_array["UltraSun_UltraMoon"] = ["アローラ図鑑"]
    local_waza_array["sword_shield"] = ["ガラル図鑑", "カンムリ雪原図鑑", "ヨロイ島図鑑"]
    local_waza_array["LegendsArceus"] = ["ヒスイ図鑑"]
    local_waza_array["Scarlet_Violet"] = ["パルデア図鑑", "キタカミ図鑑", "ブルーベリー図鑑"]

    local_waza_array.each do |game_version, local_waza|
      # JSONパースエラー時にファイル内容を表示しないようにする
      begin
        content = File.read("./pokedex/#{game_version}/waza_list.json")
        waza_json = JSON.parse(content)
      rescue JSON::ParserError => e
        STDERR.puts "waza_list.json のパースに失敗しました: #{e.message}"
        exit 1
      end

      version_key = waza_json['game_version']
      waza_json["waza_list"][version_key].each do |waza_name, waza_data|
        db.execute(
          "INSERT INTO local_waza (waza, version, type, category, pp, power, accuracy) VALUES (?, ?, ?, ?, ?, ?, ?)",
          [
            waza_name,
            game_version,
            waza_data['type'],
            waza_data['category'],
            waza_data['pp'],
            waza_data['power'],
            waza_data['accuracy'],
          ]
        )

        db.execute(
          "INSERT INTO local_waza_language (waza, version, language, name, description) VALUES (?, ?, ?, ?, ?)",
          [
            waza_name,
            game_version,
            'jpn',
            waza_name,
            waza_data['description'],
          ]
        )

      end
    end

    # -----------------------------------------------------
    puts "load for local waza machine"
    # table:local_waza_machine
    # Drop tables
    db.execute("DROP TABLE IF EXISTS local_waza_machine")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS local_waza_machine (
        waza TEXT,
        version TEXT,
        machine_no TEXT
      )
    SQL

    local_waza_array.each do |game_version, local_waza|
      if File.exist?("./pokedex/#{game_version}/waza_machine.json")
        # JSONパースエラー時にファイル内容を表示しないようにする
        begin
          content = File.read("./pokedex/#{game_version}/waza_machine.json")
          waza_json = JSON.parse(content)
        rescue JSON::ParserError => e
          STDERR.puts "waza_machine.json のパースに失敗しました: #{e.message}"
          exit 1
        end

        version_key = waza_json['game_version']
        waza_json["waza_machine"].each do |waza_name, waza_data|
          db.execute(
            "INSERT INTO local_waza_machine (waza, version, machine_no) VALUES (?, ?, ?)",
            [
              waza_name,
              game_version,
              waza_data,
            ]
          )
        end
      end
    end

    # -----------------------------------------------------
    puts "load for ability"
    # table:ability_language
    # Drop tables
    db.execute("DROP TABLE IF EXISTS ability_language")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS ability_language (
        ability TEXT,
        version TEXT,
        language TEXT,
        name TEXT,
        description TEXT
      )
    SQL

    # JSONパースエラー時にファイル内容を表示しないようにする
    begin
      content = File.read("./ability/ability.json")
      ability_json = JSON.parse(content)
    rescue JSON::ParserError => e
      STDERR.puts "ability.json のパースに失敗しました: #{e.message}"
      exit 1
    end

    ability_json["ability"].each do |ability_name, ability_data|
      ability_data.each do |version, ability_version_data|
        db.execute(
          "INSERT INTO ability_language (ability, version, language, name, description) VALUES (?, ?, ?, ?, ?)",
          [
            ability_name,
            version,
            'jpn',
            ability_name,
            ability_version_data,
          ]
        )
      end
    end


    # -----------------------------------------------------
    # puts "load for local waza"
    # load for local waza
    # table:local_pokedex_waza
    # Drop tables
    db.execute("DROP TABLE IF EXISTS local_pokedex_waza")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS local_pokedex_waza (
        id TEXT,
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        version TEXT,
        pokedex TEXT,
        conditions TEXT,
        waza TEXT
      )
    SQL
    
    # table:local_pokedex_waza_machine
    # Drop tables
    db.execute("DROP TABLE IF EXISTS local_pokedex_waza_machine")
    # Create tables
    db.execute(<<-SQL)
      CREATE TABLE IF NOT EXISTS local_pokedex_waza_machine (
        id TEXT,
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        version TEXT,
        pokedex TEXT,
        machine_no TEXT
      )
    SQL
    
    local_waza_array = {}
    local_waza_array["Red_Green_Blue_Yellow"] = ["red", "green", "blue", "pikachu"]
    local_waza_array["Gold_Silver_Crystal"] = ["johto"]
    local_waza_array["ruby_sapphire_emerald"] = ["hoenn"]
    local_waza_array["diamond_pearl_platinum"] = ["sinnoh"]
    local_waza_array["black_white"] = ["unova"]
    local_waza_array["black2_white2"] = ["unova"]
    local_waza_array["x_y"] = ["central_kalos", "coast_kalos", "mountain_kalos"]
    local_waza_array["sun_moon"] = ["alola"]
    local_waza_array["UltraSun_UltraMoon"] = ["alola"]
    local_waza_array["sword_shield"] = ["galar", "isle_of_armor", "crown_tundra"]
    local_waza_array["LegendsArceus"] = ["hisui"]
    local_waza_array["Scarlet_Violet"] = ["paldea", "kitakami", "blueberry"]
    
    local_waza_array.each do |game_version, regions|
      if File.exist?("./pokedex/#{game_version}/waza.json")
        puts "load for local waza #{game_version}"
        # JSONパースエラー時にファイル内容を表示しないようにする
        begin
          content = File.read("./pokedex/#{game_version}/waza.json")
          waza_json = JSON.parse(content)
        rescue JSON::ParserError => e
          STDERR.puts "waza.json のパースに失敗しました: #{e.message}"
          exit 1
        end

        version_key = waza_json['game_version']
        regions.each do |region|
          waza_json["waza"][region].each do |waza_data|
            waza_data["form"].each do |form_data|
              form_data[""].each do |form_version_data|
                db.execute(
                  "INSERT INTO local_pokedex_waza (id, globalNo, form, region, mega_evolution, gigantamax, version, pokedex, conditions, waza) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                  [
                    form_data['id'],
                    waza_data['globalNo'].to_s.rjust(4, '0'),
                    form_data['form'],
                    form_data['region'],
                    form_data['mega_evolution'],
                    form_data['gigantamax'],
                    game_version,
                    region,
                    '基本',
                    form_version_data
                  ]
                )
              end
              form_data["思い出し"].each do |form_version_data|
                db.execute(
                  "INSERT INTO local_pokedex_waza (id, globalNo, form, region, mega_evolution, gigantamax, version, pokedex, conditions, waza) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                  [
                    form_data['id'],
                    waza_data['globalNo'].to_s.rjust(4, '0'),
                    form_data['form'],
                    form_data['region'],
                    form_data['mega_evolution'],
                    form_data['gigantamax'],
                    game_version,
                    region,
                    '思い出し',
                    form_version_data
                  ]
                )
              end
              form_data["進化時"].each do |form_version_data|
                db.execute(
                  "INSERT INTO local_pokedex_waza (id, globalNo, form, region, mega_evolution, gigantamax, version, pokedex, conditions, waza) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                  [
                    form_data['id'],
                    waza_data['globalNo'].to_s.rjust(4, '0'),
                    form_data['form'],
                    form_data['region'],
                    form_data['mega_evolution'],
                    form_data['gigantamax'],
                    game_version,
                    region,
                    '進化時',
                    form_version_data
                  ]
                )
              end
              form_data["わざマシン"].each do |form_waza_machine_data|
                db.execute(
                  "INSERT INTO local_pokedex_waza_machine (id, globalNo, form, region, mega_evolution, gigantamax, version, pokedex, machine_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                  [
                    form_data['id'],
                    waza_data['globalNo'].to_s.rjust(4, '0'),
                    form_data['form'],
                    form_data['region'],
                    form_data['mega_evolution'],
                    form_data['gigantamax'],
                    game_version,
                    region,
                    form_waza_machine_data
                  ]
                )
              end

              form_data.each do |key, form_version_data|
                next if key == "" || key == "思い出し" || key == "進化時" || key == "わざマシン" || key == "form" || key == "region" || key == "mega_evolution" || key == "gigantamax"
                form_version_data.each do |waza|
                  db.execute(
                    "INSERT INTO local_pokedex_waza (id, globalNo, form, region, mega_evolution, gigantamax, version, pokedex, conditions, waza) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                      form_data['id'],
                      waza_data['globalNo'].to_s.rjust(4, '0'),
                      form_data['form'],
                      form_data['region'],
                      form_data['mega_evolution'],
                      form_data['gigantamax'],
                      game_version,
                      region,
                      key,
                      waza
                    ]
                  )
                end
              end
            end
          end
        end
      end
    end
    
        # db.execute(
        #         "INSERT INTO local_waza_language (waza, version, language, name, description) VALUES (?, ?, ?, ?, ?)",
        #         [
        #           waza_name,
        #           game_version,
        #           'jpn',
        #           waza_name,
        #           waza_data['description'],
        #         ]
        #       )
        #     end
        #   end
        # end

    puts "load for local description"
    version_array = [
      "red",
      "green",
      "blue",
      "pikachu",
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
      "white",
      "black2",
      "white2",
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
      "legends_arceus",
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
      "legends_arceus",
      "brilliantdiamond",
      "brilliantdiamond_kanji",
      "shiningpearl",
      "shiningpearl_kanji",
      "scarlet",
      "violet",
      "pokemongo",
      "pokemonpinball",
      "pokemonranger",
      "pokemonstadium",
      "pokemonstadium2",
      "new_pokemon_snap"
    ]
    # JSONパースエラー時にファイル内容を表示しないようにする
    begin
      content = File.read("./pokedex/description.json")
      description_json = JSON.parse(content)
    rescue JSON::ParserError => e
      # エラー内容をファイルに出力
      log_path = File.join(__dir__, "description_parse_error.log")
      File.open(log_path, "w:UTF-8") do |f|
        if e.message =~ /(\d+):/
          pos = $1.to_i
          line = content[0...pos].count("\n") + 1
          f.puts "description.json のパースに失敗しました (#{line}行目): #{e.message}"
        else
          f.puts "description.json のパースに失敗しました: #{e.message}"
        end
        f.puts content
      end
      STDERR.puts "エラー内容を #{log_path} に出力しました"
      exit 1
    end

    description_json["description"].each do |pokedex|
      # puts pokedex['globalNo']
      version_array.each do |version|
        if pokedex[version] == "" or pokedex[version] == nil then
          next
        end
        sql = <<-SQL
INSERT INTO local_pokedex_description (id, globalNo, form, region, mega_evolution, gigantamax, version, language, description)
SELECT ?,?,?,?,?,?,?,?,?
WHERE NOT EXISTS (
  SELECT 1 FROM local_pokedex_description
  WHERE id = ? AND globalNo = ? AND form = ? AND region = ? AND mega_evolution = ? AND gigantamax = ? AND version = ? AND language = ?
)
SQL
        # db.execute(sql, [
        #   pokedex['globalNo'].to_s.rjust(4, '0')+'_'+pokedex['form'].to_s+'_'+pokedex['region'].to_s+'_'+pokedex['mega_evolution'].to_s+'_'+pokedex['gigantamax'].to_s,
        #   pokedex['globalNo'].to_s.rjust(4, '0'), 
        #   pokedex['form'], 
        #   pokedex['region'],
        #   pokedex['mega_evolution'], 
        #   pokedex['gigantamax'], 
        #   version,
        #   'jpn', 
        #   pokedex[version],

        #   pokedex['globalNo'].to_s.rjust(4, '0')+'_'+pokedex['form'].to_s+'_'+pokedex['region'].to_s+'_'+pokedex['mega_evolution'].to_s+'_'+pokedex['gigantamax'].to_s,
        #   pokedex['globalNo'].to_s.rjust(4, '0'), 
        #   pokedex['form'], 
        #   pokedex['region'],
        #   pokedex['mega_evolution'], 
        #   pokedex['gigantamax'], 
        #   version,
        #   'jpn'
        # ])
      end
    end

    db.commit
  rescue SQLite3::Exception => e
    # puts "Error occurred while creating database: #{e.message}"
    db.rollback
  end
end