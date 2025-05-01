# CreateDB.rb
require 'sqlite3'
require 'json'

if __FILE__ == $0
  db = SQLite3::Database.new('new_pokedex.db')
  begin
    db.transaction
    
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
      puts pokemon['no']
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

    pokedex_json = JSON.parse(File.read('./pokedex/Red_Green_Blue_Yellow/Red_Green_Blue_Yellow.json'))
    game_version = pokedex_json['game_version']

    pokedex_json["pokedex"]["カントー図鑑"].each do |pokemon|
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

    db.commit
  rescue SQLite3::Exception => e
    puts "Error occurred while creating database: #{e.message}"
    db.rollback
  end
end