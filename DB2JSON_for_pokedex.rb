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

############################################################
#  ID 生成ヘルパー
#  DB2JSON.rb と同等のロジックを流用
############################################################
def generate_pokemon_id(no, region, spare1, spare2, gigantamax, mega_evolution, form, mf, out_of_index, shiny)
  # リージョン値
  region_value = case region
                 when 'アローラのすがた' then '01'
                 when 'ガラルのすがた'  then '02'
                 when 'ヒスイのすがた'  then '03'
                 when 'パルデアのすがた' then '04'
                 else '00'
                 end

  # キョダイマックス・メガシンカフラグ
  gigantamax_value     = gigantamax.to_s.empty?     ? '0' : '1'
  mega_evolution_value = mega_evolution.to_s.empty? ? '0' : '1'

  # フォーム値 (数値ならゼロパディング、空なら00、文字列なら01)
  form_value = if form.to_s.empty?
                 '00'
               elsif form.to_s =~ /^\d+$/
                 form.to_i.to_s.rjust(2, '0')
               else
                 '01'
               end

  no.to_s.rjust(4, '0') + "_" + region_value + spare1 + spare2 +
    gigantamax_value + mega_evolution_value + form_value + "_" +
    mf.to_s + "_" + out_of_index.to_s.rjust(3, '0') + "_" + shiny.to_s
end

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

  unless pokemon_by_no.key?(global_no)
    pokemon_by_no[global_no] = {
      'globalNo' => global_no,
      # 'name'     => name_hash,
      'form'     => []
    }
  end

  # 分類（classification）を pokedex_classification テーブルから取得
  classification_rows = db.execute('SELECT language, classification FROM pokedex_classification WHERE id = ?', [row['id']])
  classification_hash = {}
  classification_rows.each { |c| classification_hash[c['language']] = c['classification'] }

  # フォーム連番 (00, 01, 02, ...)
  form_seq = pokemon_by_no[global_no]['form'].length.to_s.rjust(2, '0')

  form_hash = {
    'id'             => generate_pokemon_id(row['globalNo'], row['region'], '0', '0', row['gigantamax'], row['mega_evolution'], form_seq, '0', '000', '0'),
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

  pokemon_by_no[global_no]['form'] << form_hash
end

json_root = {
  'update'  => Time.now.strftime('%Y%m%d'),
  'pokedex' => pokemon_by_no.values
}

File.write(OUT_FILE, JSON.pretty_generate(json_root), mode: 'w:UTF-8')
puts "→ #{OUT_FILE} を生成しました"