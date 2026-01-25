#!/usr/bin/env ruby
# frozen_string_literal: true

require 'json'
require 'fileutils'
require 'open3'
require 'pathname'
require 'rbconfig'
require 'tty-prompt'
require 'pastel'

ROOT_DIR = File.expand_path('..', __dir__)
TOOLS_DIR = __dir__
CONFIG_PATH = File.join(TOOLS_DIR, 'tui_config.json')

class MultiIO
  def initialize(*targets)
    @targets = targets
  end

  def write(*args)
    @targets.each { |target| target.write(*args) }
  end

  def flush
    @targets.each(&:flush)
  end
end

def load_config
  JSON.parse(File.read(CONFIG_PATH))
rescue Errno::ENOENT
  warn "設定ファイルが見つかりません: #{CONFIG_PATH}"
  exit 1
rescue JSON::ParserError => e
  warn "設定ファイルのパースに失敗しました: #{e.message}"
  exit 1
end

def resolve_path(root, path)
  return nil if path.nil? || path.strip.empty?
  target = Pathname.new(path)
  target.absolute? ? target.to_s : File.expand_path(path, root)
end

def select_file(prompt, title, base_dir, pattern, default_path)
  entries = []
  if base_dir && Dir.exist?(base_dir)
    entries = Dir.children(base_dir).select do |name|
      File.file?(File.join(base_dir, name)) && name.match?(pattern)
    end
  end

  choices = entries.map { |name| File.join(base_dir, name) }
  choices << default_path if default_path && File.exist?(default_path)
  choices = choices.uniq
  choices << '手入力'

  selection = prompt.select(title, choices)
  return selection unless selection == '手入力'

  input = prompt.ask('パスを入力してください', default: default_path)
  input&.strip
end

def run_script(script_name, args, log_dir)
  script_path = File.join(TOOLS_DIR, script_name)
  unless File.exist?(script_path)
    warn "スクリプトが見つかりません: #{script_path}"
    return
  end

  FileUtils.mkdir_p(log_dir) if log_dir
  log_path = log_dir ? File.join(log_dir, "tool_#{Time.now.strftime('%Y%m%d_%H%M%S')}.log") : nil

  original_stdout = $stdout
  original_stderr = $stderr
  log_file = log_path ? File.open(log_path, 'w:utf-8') : nil
  $stdout = log_file ? MultiIO.new(original_stdout, log_file) : original_stdout
  $stderr = log_file ? MultiIO.new(original_stderr, log_file) : original_stderr

  puts "実行開始: #{script_name} #{args.join(' ')}"
  Open3.popen2e(RbConfig.ruby, script_path, *args) do |stdin, stdout_err, wait_thr|
    stdin.close
    stdout_err.each { |line| puts line.chomp }
    status = wait_thr.value
    puts status.success? ? '完了しました。' : "失敗しました (status=#{status.exitstatus})."
  end
ensure
  log_file&.close
  $stdout = original_stdout
  $stderr = original_stderr
  puts "ログ出力: #{log_path}" if log_path
end

config = load_config
prompt = TTY::Prompt.new
pastel = Pastel.new

defaults = config.fetch('defaults', {})
json_dir = resolve_path(ROOT_DIR, defaults['json_dir'] || 'pokedex')
csv_dir = resolve_path(ROOT_DIR, defaults['csv_dir'] || 'data/spreadsheet')
db_path = resolve_path(ROOT_DIR, defaults['db_path'] || 'pokedex.db')
log_dir = resolve_path(ROOT_DIR, defaults['log_dir'] || 'tools/logs')

loop do
  choice = prompt.select(pastel.cyan('Pokedex Tools TUI')) do |menu|
    menu.choice('1) JSON → DB 取り込み (CreateDB)', :create_db)
    menu.choice('2) DB → JSON 出力 (DB2JSON / 地方選択)', :db2json)
    menu.choice('3) DB → JSON 出力 (pokedex.json)', :pokedex_json)
    menu.choice('4) CSV → DB 取り込み (dex/map)', :csv_import)
    menu.choice('0) 終了', :exit)
  end

  case choice
  when :create_db
    run_script('CreateDB.rb', [], log_dir)
  when :db2json
    versions = config.fetch('db2json_versions', [])
    if versions.empty? && json_dir && Dir.exist?(json_dir)
      versions = Dir.children(json_dir).select { |name| File.directory?(File.join(json_dir, name)) }.sort
    end

    if versions.empty?
      warn '出力対象のバージョンが見つかりません。設定ファイルを確認してください。'
      next
    end

    selected = prompt.multi_select('出力する地方を選択してください', versions, min: 1)
    run_script('DB2JSON.rb', selected, log_dir)
  when :pokedex_json
    run_script('DB2JSON_for_pokedex.rb', [], log_dir)
  when :csv_import
    dex_default = csv_dir ? File.join(csv_dir, 'dex.csv') : nil
    map_default = csv_dir ? File.join(csv_dir, 'map.csv') : nil

    dex_path = select_file(prompt, 'dex.csv を選択してください', csv_dir, /dex.*\.csv/i, dex_default)
    map_path = select_file(prompt, 'map.csv を選択してください', csv_dir, /map.*\.csv/i, map_default)

    unless dex_path && File.exist?(dex_path)
      warn "dex.csv が見つかりません: #{dex_path}"
      next
    end
    unless map_path && File.exist?(map_path)
      warn "map.csv が見つかりません: #{map_path}"
      next
    end
    unless db_path && File.exist?(db_path)
      warn "pokedex.db が見つかりません: #{db_path}"
      next
    end

    run_script('import_pokedex_map_from_csv.rb', [dex_path, map_path, db_path], log_dir)
  when :exit
    break
  end
end
