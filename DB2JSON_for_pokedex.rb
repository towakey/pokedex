#!/usr/bin/env ruby

require 'sqlite3'
require 'json'
require 'fileutils'

DB_PATH = File.expand_path('pokedex.db', __dir__)
OUT_DIR  = File.join(__dir__, 'pokedex')
FileUtils.mkdir_p(OUT_DIR)
OUT_FILE = File.join(OUT_DIR, 'pokedex.json')

db = SQLite3::Database.new(DB_PATH)
db.results_as_hash = true


rows = db.execute(
  <<~SQL
    SELECT *
      FROM pokedex
SQL
)

pokemon_by_no = {}

rows.each do |row|
  global_no = row['globalNo']

  # 名前を pokedex_name テーブルから取得
  name_rows = db.execute('SELECT language, name FROM pokedex_name WHERE id = ?', [row['id']])
  name_hash = {}
  name_rows.each { |n| name_hash[n['language']] = n['name'] }
  # フォールバック: データが無い言語は pokedex テーブルの値を使用
  %w[jpn eng ger fra kor chs cht].each do |lang|
    name_hash[lang] ||= row[lang]
  end

  pokemon_by_no[global_no] ||= {}

  # 分類（classification）を pokedex_classification テーブルから取得
  classification_rows = db.execute('SELECT language, classification FROM pokedex_classification WHERE id = ?', [row['id']])
  classification_hash = {}
  classification_rows.each { |c| classification_hash[c['language']] = c['classification'] }

  form_id = row['id']

  form_hash = {
    # 'id'             => form_id,
    'form'           => row['form'],
    'region'         => row['region'],
    'mega_evolution' => row['mega_evolution'],
    'gigantamax'     => row['gigantamax'],
    'classification' => classification_hash,
    'height'         => row['height'],
    'weight'         => row['weight']
  }

  # フォーム名を常に設定（通常フォームも対象）
  form_name_hash = {}
  name_rows.each { |n| form_name_hash[n['language']] = n['name'] }

  # フォールバック: データが無い言語は pokedex テーブルの列を使用
  %w[jpn eng ger fra kor chs cht].each do |lang|
    form_name_hash[lang] ||= row[lang]
  end

  form_hash['name'] = form_name_hash

  pokemon_by_no[global_no][form_id] = form_hash
end

json_root = {
  'update'  => Time.now.strftime('%Y%m%d'),
  'pokedex' => pokemon_by_no
}

File.write(OUT_FILE, JSON.pretty_generate(json_root), mode: 'w:UTF-8')
puts "→ #{OUT_FILE} を生成しました"