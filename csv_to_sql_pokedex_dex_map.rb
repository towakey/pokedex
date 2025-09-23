require 'csv'
require 'sqlite3'

# CSVファイルのパス
csv_file_path = 'csv/dex_mapping_250911.csv'

# 出力SQLファイルのパス
sql_file_path = 'insert_pokedex_dex_map.sql'

# データベースファイルのパス
db_file_path = 'pokedex.db'

# SQLクエリを格納する配列
insert_queries = []

puts "CSVファイルを読み込み中..."

# CSVファイルを読み込み、ヘッダーを使用
CSV.foreach(csv_file_path, headers: true, encoding: 'UTF-8') do |row|
  # 基本情報を抽出
  id = row['ID']
  
  # IDからglobalNoを抽出（IDの最初の4桁が図鑑番号と仮定）
  global_no = id ? id[0, 4] : ''
  
  # 2列目以降（verID01からverID11）の値を取得し、空でないものを結合
  ver_ids = []
  (1..11).each do |i|
    ver_id_column = "verID#{sprintf('%02d', i)}"
    ver_id_value = row[ver_id_column]
    
    # 値が存在し、空でない場合に配列に追加
    if ver_id_value && !ver_id_value.strip.empty?
      ver_ids << ver_id_value.strip
    end
  end
  
  # verIDsをカンマ区切りで結合
  combined_ver_id = ver_ids.join(',')
  
  # データが存在する場合のみクエリを生成
  if id && !id.strip.empty? && !combined_ver_id.empty?
    # 値がnilの場合の処理
    id_safe = id || ''
    global_no_safe = global_no || ''
    combined_ver_id_safe = combined_ver_id || ''
    
    # 値をエスケープ（シングルクォートをエスケープ）
    id_escaped = id_safe.gsub("'", "''")
    global_no_escaped = global_no_safe.gsub("'", "''")
    combined_ver_id_escaped = combined_ver_id_safe.gsub("'", "''")
    
    # INSERTクエリを生成
    query = "INSERT INTO pokedex_dex_map (id, globalNo, verID) VALUES ('#{id_escaped}', '#{global_no_escaped}', '#{combined_ver_id_escaped}');"
    
    # クエリを配列に追加
    insert_queries << query
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
  puts "pokedex_dex_mapテーブルを作成中..."
  create_table_sql = <<~SQL
    CREATE TABLE IF NOT EXISTS pokedex_dex_map (
      id TEXT,
      globalNo TEXT,
      verID TEXT
    )
  SQL
  db.execute(create_table_sql)
  
  # 既存データを削除（重複を避けるため）
  puts "既存データを削除中..."
  db.execute("DELETE FROM pokedex_dex_map")
  
  # トランザクションを開始してパフォーマンスを向上
  puts "データ挿入を開始中... (#{insert_queries.size}件)"
  db.transaction do
    insert_queries.each_with_index do |query, index|
      db.execute(query)
      
      # 進捗表示（100件ごと）
      if (index + 1) % 100 == 0
        puts "#{index + 1}/#{insert_queries.size} 件完了"
      end
    end
  end
  
  # 挿入されたレコード数を確認
  count = db.execute("SELECT COUNT(*) FROM pokedex_dex_map")[0][0]
  puts "データベースへの挿入が完了しました。合計 #{count} 件のレコードが挿入されました。"
  
  # サンプルデータを表示
  puts "\nサンプルデータ（最初の5件）:"
  sample_data = db.execute("SELECT * FROM pokedex_dex_map LIMIT 5")
  sample_data.each do |row|
    puts "  ID: #{row[0]}, globalNo: #{row[1]}, verID: #{row[2]}"
  end
  
rescue SQLite3::Exception => e
  puts "データベースエラーが発生しました: #{e.message}"
ensure
  # データベース接続を閉じる
  db&.close
  puts "データベース接続を閉じました。"
end
