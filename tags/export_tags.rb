#!/usr/bin/env ruby
# -*- coding: utf-8 -*-

require 'sqlite3'
require 'json'

# データベースパス
DB_PATH = File.join(__dir__, 'tags.db')
OUTPUT_FILE = File.join(__dir__, 'tags.json')

# データベース接続
begin
  db = SQLite3::Database.new(DB_PATH)
  db.results_as_hash = true
rescue SQLite3::Exception => e
  puts "データベース接続エラー: #{e.message}"
  exit 1
end

# タグデータを取得
begin
  sql = <<~SQL
    SELECT 
      ts.id, ts.area, ts.pokedex_no, ts.tag, ts.status,
      COALESCE(SUM(CASE WHEN tv.vote_type = 'good' THEN 1 ELSE 0 END), 0) as good_count,
      COALESCE(SUM(CASE WHEN tv.vote_type = 'bad' THEN 1 ELSE 0 END), 0) as bad_count
    FROM tag_suggestions ts
    LEFT JOIN tag_votes tv ON ts.id = tv.tag_id
    GROUP BY ts.id
    ORDER BY ts.pokedex_no, ts.area, ts.tag
  SQL
  
  rows = db.execute(sql)
  
  # JSON形式に変換
  tags_data = {}
  
  rows.each do |row|
    pokedex_no = row['pokedex_no'].to_s.rjust(4, '0')
    area = row['area']
    
    tags_data[pokedex_no] ||= {}
    tags_data[pokedex_no][area] ||= []
    
    tags_data[pokedex_no][area] << {
      'name' => row['tag'],
      'status' => row['status'],
      'good' => row['good_count'],
      'bad' => row['bad_count']
    }
  end
  
  # 出力データ作成
  output = {
    'update' => Time.now.strftime('%Y%m%d'),
    'tags' => tags_data
  }
  
  # JSONファイルに出力
  File.write(OUTPUT_FILE, JSON.pretty_generate(output))
  
  puts "エクスポート完了: #{OUTPUT_FILE}"
  puts "図鑑No.数: #{tags_data.keys.length}"
  puts "タグ総数: #{rows.length}"
  
rescue SQLite3::Exception => e
  puts "データベースエラー: #{e.message}"
  exit 1
rescue => e
  puts "エラー: #{e.message}"
  exit 1
ensure
  db&.close
end
