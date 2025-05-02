# CreateDB.rb
require 'sqlite3'
require 'json'

if __FILE__ == $0
  db = SQLite3::Database.new('new_pokedex.db')
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
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        language TEXT,
        classification TEXT
      )
    SQL

    pokedex_json = JSON.parse(File.read('./pokedex/pokedex.json'))
    pokedex_json["pokedex"].each do |pokemon|
      pokemon["form"].each do |form|
        db.execute(
          "INSERT INTO pokedex (globalNo, form, region, mega_evolution, gigantamax, height, weight) VALUES (?, ?, ?, ?, ?, ?, ?)",
          [
            pokemon['no'],
            form['form'],
            form['region'],
            form['mega_evolution'],
            form['gigantamax'],
            form['height'],
            form['weight']
          ]
        )

        db.execute(
          "INSERT INTO pokedex_classification (globalNo, form, region, mega_evolution, gigantamax, language, classification) VALUES (?, ?, ?, ?, ?, ?, ?)",
          [
            pokemon['no'],
            form['form'],
            form['region'],
            form['mega_evolution'],
            form['gigantamax'],
            # form['language'],
            'jpn',                  # 現時点では日本語のみ
            form['classification']
          ]
        )

        if form['name'] then
          form['name'].each do |language, name|
            db.execute(
              "INSERT INTO pokedex_name (globalNo, form, region, mega_evolution, gigantamax, language, name) VALUES (?, ?, ?, ?, ?, ?, ?)",
              [
                pokemon['no'],
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
              "INSERT INTO pokedex_name (globalNo, form, region, mega_evolution, gigantamax, language, name) VALUES (?, ?, ?, ?, ?, ?, ?)",
              [
                pokemon['no'],
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
        globalNo TEXT,
        form TEXT,
        region TEXT,
        mega_evolution TEXT,
        gigantamax TEXT,
        version TEXT,
        version_name TEXT,
        language TEXT,
        description TEXT
      )
    SQL

    local_pokedex_array = {}
    local_pokedex_array["red_green_blue_yellow"] = ["カントー図鑑"]
    local_pokedex_array["gold_silver_crystal"] = ["ジョウト図鑑"]
    local_pokedex_array["ruby_sapphire_emerald"] = ["ホウエン図鑑"]
    local_pokedex_array["diamond_pearl_platinum"] = ["シンオウ図鑑"]
    local_pokedex_array["black_white"] = ["イッシュ図鑑"]
    local_pokedex_array["black2_white2"] = ["イッシュ図鑑"]
    local_pokedex_array["x_y"] = ["セントラルカロス図鑑", "コーストカロス図鑑", "マウンテンカロス図鑑"]
    local_pokedex_array["sun_moon"] = ["アローラ図鑑"]
    local_pokedex_array["UltraSun_UltraMoon"] = ["アローラ図鑑"]
    local_pokedex_array["sword_shield"] = ["ガラル図鑑", "カンムリ雪原図鑑", "ヨロイ島図鑑"]
    local_pokedex_array["LegendsArceus"] = ["ヒスイ図鑑"]
    local_pokedex_array["Scarlet_Violet"] = ["パルデア図鑑", "キタカミ図鑑", "ブルーベリー図鑑"]

    local_pokedex_array.each do |game_version, local_pokedex|
      pokedex_json = JSON.parse(File.read("./pokedex/#{game_version}/#{game_version}.json"))

      local_pokedex.each do |pokedex_name|  
        pokedex_json["pokedex"][pokedex_name].each do |pokemon|
          pokemon["status"].each do |form|
            db.execute(
              "INSERT INTO local_pokedex (no, globalNo, form, region, mega_evolution, gigantamax, version, pokedex) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
              [
                pokemon['no'],
                pokemon['globalNo'],
                form['form'],
                form['region'],
                form['mega_evolution'],
                form['gigantamax'],
                game_version,
                'カントー図鑑'
              ]
            )

            db.execute(
              "INSERT INTO local_pokedex_type (globalNo, form, region, mega_evolution, gigantamax, version, type1, type2) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
              [
                pokemon['globalNo'],
                form['form'],
                form['region'],
                form['mega_evolution'],
                form['gigantamax'],
                game_version,
                form['type1'],
                form['type2']
              ]
            )

            db.execute(
              "INSERT INTO local_pokedex_ability (globalNo, form, region, mega_evolution, gigantamax, version, ability1, ability2, dream_ability) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
              [
                pokemon['globalNo'],
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
              "INSERT INTO local_pokedex_status (globalNo, form, region, mega_evolution, gigantamax, version, hp, attack, defense, special_attack, special_defense, speed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
              [
                pokemon['globalNo'],
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
              db.execute(
                "INSERT INTO local_pokedex_description (globalNo, form, region, mega_evolution, gigantamax, version, version_name, language, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                  pokemon['globalNo'],
                  form['form'],
                  form['region'],
                  form['mega_evolution'],
                  form['gigantamax'],
                  game_version,
                  language,
                  'jpn',
                  description
                ]
              )
            end
          end
        end
      end
    end

    # description_json = JSON.parse(File.read("./pokedex/description.json"))
    # description_json["description"].each do |pokedex|
    #   db.execute(<<-SQL)
    #     INSERT INTO pokedex_description (globalNo, form, region, mega_evolution, gigantamax, language, description) VALUES (?, ?, ?, ?, ?, ?, ?)
    #   SQL
    # end

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
    local_waza_array["red_green_blue_yellow"] = ["カントー図鑑"]
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
      waza_json = JSON.parse(File.read("./pokedex/#{game_version}/waza_list.json"))
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
        waza_json = JSON.parse(File.read("./pokedex/#{game_version}/waza_machine.json"))
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

    ability_json = JSON.parse(File.read("./ability/ability.json"))
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
        puts "load for local waza"
        # load for local waza
        # table:local_waza
        # Drop tables
        db.execute("DROP TABLE IF EXISTS local_pokedex_waza")
        # Create tables
        db.execute(<<-SQL)
          CREATE TABLE IF NOT EXISTS local_pokedex_waza (
            globalNo TEXT,
            form TEXT,
            region TEXT,
            mega_evolution TEXT,
            gigantamax TEXT,
            version TEXT,
            conditions TEXT,
            waza TEXT,
          )
        SQL
    
        # table:local_pokedex_waza_machine
        # Drop tables
        db.execute("DROP TABLE IF EXISTS local_pokedex_waza_machine")
        # Create tables
        db.execute(<<-SQL)
          CREATE TABLE IF NOT EXISTS local_pokedex_waza_machine (
            globalNo TEXT,
            form TEXT,
            region TEXT,
            mega_evolution TEXT,
            gigantamax TEXT,
            version TEXT,
            machine_no TEXT,
          )
        SQL
    
        local_waza_array = {}
        local_waza_array["red_green_blue_yellow"] = ["kanto"]
        local_waza_array["gold_silver_crystal"] = ["johto"]
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
            waza_json = JSON.parse(File.read("./pokedex/#{game_version}/waza.json"))
            version_key = waza_json['game_version']
            waza_json["waza"][regions].each do |no, waza_data|
              db.execute(
                "INSERT INTO local_pokedex_waza (globalNo, form, region, mega_evolution, gigantamax, version, conditions, waza) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                  waza_data['globalNo'],
                  game_version,
                  regions,
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
        end
    
    db.commit
  rescue SQLite3::Exception => e
    puts "Error occurred while creating database: #{e.message}"
    db.rollback
  end
end