#!/usr/bin/env ruby
# frozen_string_literal: true
# ------------------------------------------------------------
# import_csv2db_description.rb
# ポケモン HOME の図鑑説明（漢字）CSV を SQLite データベース
#   pokedex.db の local_pokedex_description テーブルへ取り込むスクリプト。
# ------------------------------------------------------------
# 使い方:
#   ruby import_csv2db_description.rb [CSV_PATH]
#   CSV_PATH を省略した場合、カレントディレクトリにある
#   『ポケモン全世代図鑑_HOME漢字.csv』を読み込みます。
#
# CSV 仕様（1 行目はヘッダー行）
#   ナンバー,初出,HOME,名前,テキスト
#   0150,31 HOME,剣,ミュウツー,ミュウの 遺伝子から つくられた ポケモン。
#
# テーブル仕様（CreateDB.rb と同一）
#   local_pokedex_description (
#     id, globalNo, form, region, mega_evolution,
#     gigantamax, version, ver, language, description
#   )
# ------------------------------------------------------------
require 'csv'
require 'sqlite3'
require 'fileutils'

# -----------------------------
# 定数定義
# -----------------------------
DB_PATH  = File.expand_path('pokedex.db', __dir__)
CSV_PATH = ARGV[0] || File.expand_path('ポケモン全世代図鑑_HOME漢字.csv', Dir.pwd)
VERSION  = 'pokemon_home' # HOME 固有のバージョン名（大分類）
LANG     = 'jpn'

# HOME 列（剣/盾 など）→ ver 列 へのマッピング
# CSV の列順（local_pokedex_description と同一）
FIELDS = %w[id globalNo form region mega_evolution gigantamax version ver language description].freeze

# -----------------------------
# ヘルパー: pokedex_name から id などを取得
# -----------------------------
# pokedex_name テーブルは参照しないため削除

# -----------------------------
# ヘルパー: INSERT 用 SQL（重複チェック付き）
# -----------------------------
INSERT_SQL = <<~SQL.freeze
  INSERT INTO local_pokedex_description (
    id, globalNo, form, region, mega_evolution, gigantamax,
    version, ver, language, description)
  SELECT ?,?,?,?,?,?,?,?, ?, ?
  WHERE NOT EXISTS (
    SELECT 1 FROM local_pokedex_description
      WHERE id = ? AND globalNo = ? AND form = ? AND region = ?
        AND mega_evolution = ? AND gigantamax = ?
        AND version = ? AND ver = ? AND language = ?
  )
SQL

# -----------------------------
# 初期化
# -----------------------------
abort("CSV ファイルが見つかりません: #{CSV_PATH}") unless File.file?(CSV_PATH)

db = SQLite3::Database.new(DB_PATH)
db.results_as_hash = true

# local_pokedex_description テーブルが無い場合は作成
# CreateDB.rb で作成済みのケースが多いが、単体実行でも動くようにしておく
create_table_sql = <<~SQL
  CREATE TABLE IF NOT EXISTS local_pokedex_description (
    id TEXT,
    globalNo TEXT,
    form TEXT,
    region TEXT,
    mega_evolution TEXT,
    gigantamax TEXT,
    version TEXT,
    ver TEXT,
    language TEXT,
    description TEXT,
    PRIMARY KEY (id, version, ver, language)
  )
SQL
db.execute(create_table_sql)

# -----------------------------
# 取り込み処理
# -----------------------------
begin
  db.transaction

  # local_pokedex_description と同一列を持つ CSV をそのまま取り込む
  CSV.foreach(CSV_PATH, encoding: 'UTF-8', headers: true) do |row|
    # 必須項目チェック（description が空ならスキップ）
    next if row['description'].to_s.strip.empty?

    # CSV → 配列 (id, globalNo, ..., description)
    data = FIELDS.map { |f| row[f] }

    # INSERT 用 + 重複チェック用パラメータ
    params = data + data[0..8]

    db.execute(INSERT_SQL, params)
  end

  db.commit
  puts "✔ CSV からの取り込みが完了しました (#{CSV_PATH})"
rescue => e
  db.rollback
  warn "[ERROR] #{e.class}: #{e.message}"
  warn e.backtrace.first(5)
  exit 1
ensure
  db.close if db
end
