require 'csv'
require 'sqlite3'

# CSVファイルのパス
csv_file_path = 'csv/dex_ALLtext_250905.csv'

# 出力SQLファイルのパス
sql_file_path = 'insert_pokedex_description.sql'

# データベースファイルのパス
db_file_path = 'pokedex.db'

# SQLクエリを格納する配列
insert_queries = []

# 言語マッピング（CSVの列名 => 言語コード）
language_mapping = {
  'JPN' => 'jpn',
  'ENG' => 'eng',
  'FRA' => 'fra',
  'ITA' => 'ita',
  'GER' => 'ger',
  'SPA' => 'spa',
  'KOR' => 'kor',
  'CHS' => 'chs',
  'CHT' => 'cht',
  'JPN(かな)' => 'jpn_kana',
  'JPN(漢字)' => 'jpn_kanji'
}

puts "CSVファイルを読み込み中..."

# CSVファイルを読み込み、ヘッダーを使用
CSV.foreach(csv_file_path, headers: true, encoding: 'UTF-8') do |row|
  # 基本情報を抽出
  id = row['ID']
  ver_id = row['verID']

  # IDからglobalNoを抽出（IDの最初の4桁が図鑑番号と仮定）
  global_no = id ? id[0, 4] : ''

  # 各言語のデータを処理
  language_mapping.each do |csv_column, language_code|
    dex_text = row[csv_column]

    # データが存在し、空でない場合のみクエリを生成
    if dex_text && !dex_text.strip.empty?
      # 値がnilの場合の処理
      id_safe = id || ''
      global_no_safe = global_no || ''
      ver_id_safe = ver_id || ''
      dex_safe = dex_text || ''

      # 値をエスケープ（シングルクォートをエスケープ）
      id_escaped = id_safe.gsub("'", "''")
      global_no_escaped = global_no_safe.gsub("'", "''")
      ver_id_escaped = ver_id_safe.gsub("'", "''")
      language_escaped = language_code.gsub("'", "''")
      dex_escaped = dex_safe.gsub("'", "''")

      # INSERTクエリを生成
      query = "INSERT INTO pokedex_description (id, globalNo, verID, language, dex) VALUES ('#{id_escaped}', '#{global_no_escaped}', '#{ver_id_escaped}', '#{language_escaped}', '#{dex_escaped}');"

      # クエリを配列に追加
      insert_queries << query
    end
  end
end

puts "SQLクエリをファイルに書き込み中..."

# SQLクエリをファイルに書き込み
File.open(sql_file_path, 'w', encoding: 'UTF-8') do |file|
  file.puts(insert_queries)
end

puts "SQLクエリを #{sql_file_path} に保存しました。合計 #{insert_queries.size} 件のクエリを生成しました。"

puts "データベースに接続してクエリを実行中..."

begin
  # SQLiteデータベースに接続
  db = SQLite3::Database.new(db_file_path)
  
  # テーブルが存在しない場合は作成
  puts "pokedex_descriptionテーブルを作成中..."
  create_table_sql = <<~SQL
    CREATE TABLE IF NOT EXISTS pokedex_description (
      id TEXT,
      globalNo TEXT,
      verID TEXT,
      language TEXT,
      dex TEXT
    )
  SQL
  db.execute(create_table_sql)
  
  # 既存データを削除（重複を避けるため）
  puts "既存データを削除中..."
  db.execute("DELETE FROM pokedex_description")
  
  # トランザクションを開始してパフォーマンスを向上
  puts "データ挿入を開始中... (#{insert_queries.size}件)"
  db.transaction do
    insert_queries.each_with_index do |query, index|
      db.execute(query)
      
      # 進捗表示（1000件ごと）
      if (index + 1) % 1000 == 0
        puts "#{index + 1}/#{insert_queries.size} 件完了"
      end
    end
  end
  
  # 挿入されたレコード数を確認
  count = db.execute("SELECT COUNT(*) FROM pokedex_description")[0][0]
  puts "データベースへの挿入が完了しました。合計 #{count} 件のレコードが挿入されました。"
  
  # 言語別レコード数を表示
  puts "\n言語別レコード数:"
  language_counts = db.execute("SELECT language, COUNT(*) FROM pokedex_description GROUP BY language ORDER BY language")
  language_counts.each do |lang, count|
    puts "  #{lang}: #{count} 件"
  end
  
rescue SQLite3::Exception => e
  puts "データベースエラーが発生しました: #{e.message}"
ensure
  # データベース接続を閉じる
  db&.close
  puts "データベース接続を閉じました。"
end
