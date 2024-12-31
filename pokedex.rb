require 'sqlite3'
require 'json'

class PokedexImporter
  def initialize(db_path = 'pokedex.db', json_path = './pokedex/pokedex.json')
    @db_path = db_path
    @json_path = json_path
    @sqlite = SQLite3::Database.open(@db_path)
  end

  def import
    @sqlite.transaction
    begin
      create_tables
      import_data
      @sqlite.commit
    rescue SQLite3::Exception => e
      @sqlite.rollback
      raise e
    end
  end

  private

  def escape_sql_string(str)
    return '' if str.nil?
    str.to_s.gsub("'", "''")
  end

  def create_tables
    create_pokedex_table
    create_version_table
  end

  def create_pokedex_table
    sql = <<SQL
DROP TABLE IF EXISTS pokedex;
SQL
    @sqlite.execute(sql)

    sql = <<SQL
CREATE TABLE pokedex (
    id INTEGER,
    form TEXT,
    region TEXT,
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

  def create_version_table
    sql = <<SQL
DROP TABLE IF EXISTS version;
SQL
    @sqlite.execute(sql)

    sql = <<SQL
CREATE TABLE version (
    table_name TEXT,
    update_date TEXT,
    created_at TEXT
);
SQL
    @sqlite.execute(sql)
  end

  def import_data
    pokedex_data = JSON.parse(File.read(@json_path))
    insert_version_info(pokedex_data["update"])
    import_pokemon_data(pokedex_data["pokedex"])
  end

  def insert_version_info(update_date)
    sql = <<SQL
INSERT INTO version (table_name, update_date, created_at)
VALUES (
    '#{escape_sql_string('pokedex')}',
    '#{escape_sql_string(update_date)}',
    '#{escape_sql_string(Time.now.to_s)}'
);
SQL
    @sqlite.execute(sql)
  end

  def import_pokemon_data(pokemon_list)
    pokemon_list.each do |pokemon|
      import_base_form(pokemon)
      import_region_forms(pokemon)
      import_mega_evolutions(pokemon)
      import_gigantamax_forms(pokemon)
    end
  end

  def import_base_form(pokemon)
    return unless pokemon[""]
    pokemon[""].each do |form|
      sql = <<SQL
INSERT INTO pokedex
VALUES (
    #{pokemon['id']},
    '#{escape_sql_string(form['form'])}',
    '#{escape_sql_string(form['region'])}',
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

  def import_region_forms(pokemon)
    return unless pokemon["region_form"]
    pokemon["region_form"].each do |form|
      sql = <<SQL
INSERT INTO pokedex
VALUES (
    #{pokemon['id']},
    '#{escape_sql_string(form['form'])}',
    '#{escape_sql_string(form['region'])}',
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
INSERT INTO pokedex
VALUES (
    #{pokemon['id']},
    '#{escape_sql_string(form['form'])}',
    '#{escape_sql_string(form['region'])}',
    '#{escape_sql_string(form['name']['jpn'] || pokemon['name']['jpn'])}',
    '#{escape_sql_string(form['name']['eng'] || pokemon['name']['eng'])}',
    '#{escape_sql_string(form['name']['ger'] || "")}',
    '#{escape_sql_string(form['name']['fra'] || "")}',
    '#{escape_sql_string(form['name']['kor'] || "")}',
    '#{escape_sql_string(form['name']['chs'] || "")}',
    '#{escape_sql_string(form['name']['cht'] || "")}',
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
INSERT INTO pokedex
VALUES (
    #{pokemon['id']},
    '#{escape_sql_string(form['form'])}',
    '#{escape_sql_string(form['region'])}',
    '#{escape_sql_string(form['name']['jpn'] || pokemon['name']['jpn'])}',
    '#{escape_sql_string(form['name']['eng'] || pokemon['name']['eng'])}',
    '#{escape_sql_string(form['name']['ger'] || "")}',
    '#{escape_sql_string(form['name']['fra'] || "")}',
    '#{escape_sql_string(form['name']['kor'] || "")}',
    '#{escape_sql_string(form['name']['chs'] || "")}',
    '#{escape_sql_string(form['name']['cht'] || "")}',
    '#{escape_sql_string(form['classification'])}',
    '#{escape_sql_string(form['height'])}',
    '#{escape_sql_string(form['weight'])}'
);
SQL
      @sqlite.execute(sql)
    end
  end
end

# スクリプトの実行
if __FILE__ == $0
  importer = PokedexImporter.new
  importer.import
end