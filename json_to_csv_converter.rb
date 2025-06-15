#!/usr/bin/env ruby
# -*- coding: utf-8 -*-

require 'json'
require 'csv'

# JSONファイルからCSVファイルに変換するスクリプト
class PokedexJSONToCSVConverter
  def initialize(json_file_path, csv_file_path)
    @json_file_path = json_file_path
    @csv_file_path = csv_file_path
  end

  def convert
    begin
      # JSONファイルを読み込み
      puts "JSONファイルを読み込み中: #{@json_file_path}"
      json_data = JSON.parse(File.read(@json_file_path, encoding: 'UTF-8'))
      
      # CSVファイルを作成
      puts "CSVファイルを作成中: #{@csv_file_path}"
      CSV.open(@csv_file_path, 'w', encoding: 'UTF-8', write_headers: true, headers: get_csv_headers) do |csv|
        
        # 各ポケモンのデータを処理
        json_data['pokedex'].each do |pokemon|
          global_no = pokemon['globalNo']
          
          # 各フォームのデータを処理
          pokemon['form'].each do |form|
            row_data = extract_form_data(global_no, form)
            csv << row_data
          end
        end
      end
      
      puts "変換完了！CSVファイルが作成されました: #{@csv_file_path}"
      
    rescue JSON::ParserError => e
      puts "JSONファイルの解析エラー: #{e.message}"
    rescue Errno::ENOENT => e
      puts "ファイルが見つかりません: #{e.message}"
    rescue => e
      puts "エラーが発生しました: #{e.message}"
    end
  end

  private

  # CSVのヘッダーを定義
  def get_csv_headers
    [
      'globalNo',
      'id',
      'form',
      'region',
      'mega_evolution',
      'gigantamax',
      'classification_jpn',
      'height',
      'weight',
      'name_jpn',
      'name_eng',
      'name_ger',
      'name_fra',
      'name_kor',
      'name_chs',
      'name_cht'
    ]
  end

  # フォームデータからCSV行データを抽出
  def extract_form_data(global_no, form)
    [
      global_no,
      form['id'] || '',
      form['form'] || '',
      form['region'] || '',
      form['mega_evolution'] || '',
      form['gigantamax'] || '',
      form.dig('classification', 'jpn') || '',
      form['height'] || '',
      form['weight'] || '',
      form.dig('name', 'jpn') || '',
      form.dig('name', 'eng') || '',
      form.dig('name', 'ger') || '',
      form.dig('name', 'fra') || '',
      form.dig('name', 'kor') || '',
      form.dig('name', 'chs') || '',
      form.dig('name', 'cht') || ''
    ]
  end
end

# メイン処理
if __FILE__ == $0
  # ファイルパスを設定
  json_file = File.join(File.dirname(__FILE__), 'pokedex', 'pokedex.json')
  csv_file = File.join(File.dirname(__FILE__), 'pokedex.csv')
  
  # 変換実行
  converter = PokedexJSONToCSVConverter.new(json_file, csv_file)
  converter.convert
end
