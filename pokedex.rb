require 'sqlite3'
require 'json'

# SQLiteの文字列をエスケープする関数
def escape_sql_string(str)
    return '' if str.nil?
    str.to_s.gsub("'", "''")
end

sqlite = SQLite3::Database.open 'pokedex.db'

# Start transaction for table creation
sqlite.transaction
sql = <<SQL
DROP TABLE IF EXISTS pokedex;
SQL
sqlite.execute(sql)

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
sqlite.execute(sql)
# sqlite.commit

pokedex = JSON.parse(File.read('./pokedex/pokedex.json'))
pokedex["pokedex"].each do |pokemon|
    puts pokemon
    pokemon[""].each do |form|
        puts form
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
        sqlite.execute(sql)
    end

    if pokemon["region_form"]
        pokemon["region_form"].each do |form|
            puts form
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
            sqlite.execute(sql)
        end
    end

    if pokemon["mega_evolution"]
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
            puts sql
            sqlite.execute(sql)
        end
    end

    if pokemon["gigantamax"]
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
            sqlite.execute(sql)
        end
    end
end
sqlite.commit