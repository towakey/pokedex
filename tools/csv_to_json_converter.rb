#!/usr/bin/env ruby
# -*- coding: utf-8 -*-

require 'csv'
require 'json'

ROOT_DIR = File.expand_path('..', __dir__)

# CSVファイルからJSONファイルに変換するスクリプト
class PokedexCSVToJSONConverter
  def initialize(csv_file_path, json_file_path)
    @csv_file_path = csv_file_path
    @json_file_path = json_file_path
  end

  def convert
    begin
      # CSVファイルを読み込み
      puts "CSVファイルを読み込み中: #{@csv_file_path}"
      
      # ポケモンデータを格納するハッシュ（globalNoでグループ化）
      pokemon_data = {}
      
      CSV.foreach(@csv_file_path, headers: true, encoding: 'UTF-8') do |row|
        global_no = row['globalNo']
        
        # 新しいポケモンの場合、初期化
        unless pokemon_data[global_no]
          pokemon_data[global_no] = {
            'globalNo' => global_no,
            'form' => []
          }
        end
        
        # フォームデータを作成
        form_data = create_form_data(row)
        pokemon_data[global_no]['form'] << form_data
      end
      
      # JSON構造を作成
      json_structure = {
        'update' => Time.now.strftime('%Y%m%d'),
        'pokedex' => pokemon_data.values.sort_by { |pokemon| pokemon['globalNo'].to_i }
      }
      
      # JSONファイルを作成
      puts "JSONファイルを作成中: #{@json_file_path}"
      File.write(@json_file_path, JSON.pretty_generate(json_structure, {
        indent: '  ',
        space: ' ',
        space_before: '',
        object_nl: "\n",
        array_nl: "\n"
      }), encoding: 'UTF-8')
      
      puts "変換完了！JSONファイルが作成されました: #{@json_file_path}"
      puts "総ポケモン数: #{pokemon_data.size}"
      puts "総フォーム数: #{pokemon_data.values.sum { |p| p['form'].size }}"
      
    rescue CSV::MalformedCSVError => e
      puts "CSVファイルの解析エラー: #{e.message}"
    rescue Errno::ENOENT => e
      puts "ファイルが見つかりません: #{e.message}"
    rescue => e
      puts "エラーが発生しました: #{e.message}"
    end
  end

  private

  # CSVの行データからフォームデータを作成
  def create_form_data(row)
    form_data = {
      'id' => row['id'] || '',
      'form' => row['form'] || '',
      'region' => row['region'] || '',
      'mega_evolution' => row['mega_evolution'] || '',
      'gigantamax' => row['gigantamax'] || '',
      'classification' => {
        'jpn' => row['classification_jpn'] || ''
      },
      'height' => row['height'] || '',
      'weight' => row['weight'] || '',
      'name' => {
        'jpn' => row['name_jpn'] || '',
        'eng' => row['name_eng'] || '',
        'ger' => row['name_ger'] || '',
        'fra' => row['name_fra'] || '',
        'kor' => row['name_kor'] || '',
        'chs' => row['name_chs'] || '',
        'cht' => row['name_cht'] || ''
      }
    }
    
    # 空の値をクリーンアップ（元のJSONに合わせて）
    clean_empty_values(form_data)
  end

  # 空の値をクリーンアップする（ただし、構造は保持）
  def clean_empty_values(data)
    case data
    when Hash
      data.each do |key, value|
        if value.is_a?(Hash)
          clean_empty_values(value)
        elsif value.is_a?(Array)
          value.each { |item| clean_empty_values(item) if item.is_a?(Hash) }
        end
      end
    end
    data
  end
end

# メイン処理
if __FILE__ == $0
  # ファイルパスを設定
  csv_file = File.join(ROOT_DIR, 'pokedex.csv')
  json_file = File.join(ROOT_DIR, 'pokedex_from_csv.json')
  
  # 変換実行
  converter = PokedexCSVToJSONConverter.new(csv_file, json_file)
  converter.convert
end
