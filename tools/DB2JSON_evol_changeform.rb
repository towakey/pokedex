#!/usr/bin/env ruby
# DB2JSON_evol_changeform.rb
# pokedex.db の pokedex_evol / pokedex_changeform テーブルから JSON を生成するスクリプト

require 'sqlite3'
require 'json'
require 'time'

ROOT_DIR = File.expand_path('..', __dir__)
OUT_PATH = File.join(ROOT_DIR, 'evolve_changeform.json')

def fetch_evol_data(db)
  sql = <<-SQL
    SELECT id, globalNo, evol
      FROM pokedex_evol
     ORDER BY CAST(globalNo AS INTEGER) ASC,
              id ASC,
              evol ASC
  SQL

  rows = db.execute(sql)

  evol_hash = {}
  rows.each do |id, global_no, evol|
    evol_hash[global_no] ||= {}
    evol_hash[global_no][id] ||= []
    evol_hash[global_no][id] << evol
  end

  evol_hash
end


def fetch_changeform_data(db)
  sql = <<-SQL
    SELECT id, globalNo, changeform
      FROM pokedex_changeform
     ORDER BY CAST(globalNo AS INTEGER) ASC,
              id ASC,
              changeform ASC
  SQL

  rows = db.execute(sql)

  changeform_hash = {}
  rows.each do |id, global_no, changeform|
    changeform_hash[global_no] ||= {}
    changeform_hash[global_no][id] ||= []
    changeform_hash[global_no][id] << changeform
  end

  changeform_hash
end


def build_output_structure
  {
    'update' => Time.now.strftime('%Y%m%d%H%M'),
    'evolve' => {},
    'changeform' => {}
  }
end


def write_json_file(path, data)
  File.open(path, 'w:UTF-8') do |file|
    file.write(JSON.pretty_generate(data, indent: '  '))
  end
end

if __FILE__ == $0
  db_path = File.join(ROOT_DIR, 'pokedex.db')

  db = SQLite3::Database.new(db_path)

  output = build_output_structure
  output['evolve'] = fetch_evol_data(db)
  output['changeform'] = fetch_changeform_data(db)

  write_json_file(OUT_PATH, output)

  puts "evolve_changeform.json を生成しました"
end
