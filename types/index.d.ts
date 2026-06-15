/**
 * towakey/pokedex TypeScript 型定義
 *
 * ポケモン図鑑JSONデータの型定義です。
 * 各JSONファイルの構造に対応する型を提供します。
 *
 * @example
 * ```typescript
 * import type { PokedexFile, VersionPokedexFile } from './types';
 *
 * const pokedex: PokedexFile = JSON.parse(fs.readFileSync('pokedex/pokedex.json', 'utf-8'));
 * const sv: VersionPokedexFile = JSON.parse(fs.readFileSync('pokedex/Scarlet_Violet/Scarlet_Violet.json', 'utf-8'));
 * ```
 */

// ============================================================
// 共通型
// ============================================================

/** 多言語テキスト。値がnullの場合はデータ未登録。 */
export interface LocalizedText {
  jpn: string | null;
  eng?: string | null;
  fra?: string | null;
  ita?: string | null;
  ger?: string | null;
  spa?: string | null;
  kor?: string | null;
  chs?: string | null;
  cht?: string | null;
}

/**
 * 言語コード
 *
 * | コード | 言語 |
 * |--------|------|
 * | `jpn`  | 日本語 |
 * | `eng`  | 英語 |
 * | `fra`  | フランス語 |
 * | `ita`  | イタリア語 |
 * | `ger`  | ドイツ語 |
 * | `spa`  | スペイン語 |
 * | `kor`  | 韓国語 |
 * | `chs`  | 簡体字中国語 |
 * | `cht`  | 繁体字中国語 |
 */
export type LanguageCode = 'jpn' | 'eng' | 'fra' | 'ita' | 'ger' | 'spa' | 'kor' | 'chs' | 'cht';

/**
 * ゲームバージョン識別子
 *
 * ディレクトリ名と一致する。
 */
export type GameVersionDir =
  | 'Red_Green_Blue_Pikachu'
  | 'Gold_Silver_Crystal'
  | 'Ruby_Sapphire_Emerald'
  | 'FireRed_LeafGreen'
  | 'Diamond_Pearl_Platinum'
  | 'HeartGold_SoulSilver'
  | 'Black_White'
  | 'Black2_White2'
  | 'X_Y'
  | 'OmegaRuby_AlphaSapphire'
  | 'Sun_Moon'
  | 'UltraSun_UltraMoon'
  | 'Sword_Shield'
  | 'BrilliantDiamond_ShiningPearl'
  | 'LegendsArceus'
  | 'Scarlet_Violet'
  | 'LegendsZA';

/**
 * ゲームバージョン識別子 (スネークケース)
 *
 * JSONデータ内の `game_version` フィールドで使用される。
 */
export type GameVersionKey =
  | 'red_green_blue_pikachu'
  | 'gold_silver_crystal'
  | 'ruby_sapphire_emerald'
  | 'firered_leafgreen'
  | 'diamond_pearl_platinum'
  | 'heartgold_soulsilver'
  | 'black_white'
  | 'black2_white2'
  | 'x_y'
  | 'omegaruby_alphasapphire'
  | 'sun_moon'
  | 'ultrasun_ultramoon'
  | 'sword_shield'
  | 'brilliantdiamond_shiningpearl'
  | 'legends_arceus'
  | 'scarlet_violet'
  | 'legends_za';

/**
 * 内部ID
 *
 * `{globalNo}_{formCode}_{regionFlag}_{megaCode}_{gigantamaxFlag}` の形式。
 *
 * @example "0001_00000000_0_000_0" // フシギダネ (通常フォルム)
 * @example "0003_00000100_0_000_0" // フシギバナ (メガシンカ)
 * @example "0003_00001000_0_000_0" // フシギバナ (キョダイマックス)
 */
export type InternalId = string;

// ============================================================
// pokedex/pokedex.json (全国図鑑)
// ============================================================

/** pokedex/pokedex.json のルートオブジェクト */
export interface PokedexFile {
  update: string;
  pokedex: PokedexData;
}

/** globalNo (4桁ゼロ埋め) をキーとするポケモンデータ */
export type PokedexData = Record<string, PokemonEntry>;

/**
 * 1匹のポケモンのフォルム一覧
 *
 * 内部IDをキーとし、各フォルムのデータを値とする。
 * 通常フォルムのみのポケモンは1エントリ、メガシンカ等があるポケモンは複数エントリ。
 */
export type PokemonEntry = Record<InternalId, PokemonGlobalForm>;

/** 全国図鑑のポケモン基本情報 (1フォルム) */
export interface PokemonGlobalForm {
  /** フォルム名 (通常フォルムは空文字またはnull) */
  form: string | null;
  /** リージョン名 (通常は空文字またはnull) */
  region: string | null;
  /** メガシンカ名 (メガシンカでなければ空文字またはnull) */
  mega_evolution: string | null;
  /** キョダイマックス名 (キョダイマックスでなければ空文字またはnull) */
  gigantamax: string | null;
  /** ポケモン名 (多言語) */
  name: LocalizedText;
  /** 分類 (多言語) (例: "たねポケモン") */
  classification: LocalizedText;
  /** たまごグループ (例: ["怪獣", "植物"]) */
  egg: string[];
  /** フォルム表示名 (多言語) */
  forms: LocalizedText;
  /** 高さ (m)。データ未登録の場合はnull。 */
  height: string | null;
  /** 重さ (kg)。データ未登録の場合はnull。 */
  weight: string | null;
}

// ============================================================
// pokedex/{Version}/{Version}.json (バージョン別図鑑)
// ============================================================

/** pokedex/{Version}/{Version}.json のルートオブジェクト */
export interface VersionPokedexFile {
  update: string;
  game_version: string;
  pokedex: VersionPokedexData;
}

/** 図鑑名をキーとする地方図鑑データ */
export type VersionPokedexData = Record<string, RegionalPokedex>;

/** 地方図鑑番号 (4桁ゼロ埋め) をキーとするポケモンデータ */
export type RegionalPokedex = Record<string, VersionPokemonEntry>;

/** 1匹のポケモンのフォルム一覧 (バージョン別) */
export type VersionPokemonEntry = Record<InternalId, VersionPokemonForm>;

/** バージョン固有のポケモンフォルムデータ (1フォルム) */
export interface VersionPokemonForm {
  /** ポケモンID (一部バージョンのみ存在) */
  id?: string;
  /** ポケモン名 (一部バージョンのメガシンカ等で存在) */
  name?: Record<string, string | null>;
  /** フォルム名 */
  form: string | null;
  /** リージョン名 */
  region: string | null;
  /** メガシンカ名 */
  mega_evolution: string | null;
  /** キョダイマックス名 */
  gigantamax: string | null;
  /** タイプ1 (例: "くさ") */
  type1: string;
  /** タイプ2 (単タイプの場合は空文字) */
  type2: string;
  /** HP種族値 */
  hp: number;
  /** こうげき種族値 */
  attack: number;
  /** ぼうぎょ種族値 */
  defense: number;
  /** とくこう種族値 */
  special_attack: number;
  /** とくぼう種族値 */
  special_defense: number;
  /** すばやさ種族値 */
  speed: number;
  /** 特性1 */
  ability1: string;
  /** 特性2 (なしの場合は空文字) */
  ability2: string;
  /** 夢特性 (なしの場合は空文字) */
  dream_ability: string;
  /**
   * 図鑑説明
   *
   * バージョン名 (例: "scarlet", "red") をキーとし、説明文を値とする。
   */
  description: Record<string, string>;
}

// ============================================================
// pokedex/{Version}/waza.json (習得わざ)
// ============================================================

/** pokedex/{Version}/waza.json のルートオブジェクト */
export interface WazaFile {
  version?: string;
  update: string;
  game_version: string;
  waza: WazaData;
}

/** 図鑑名をキーとする地方別わざデータ */
export type WazaData = Record<string, PokemonWaza[]>;

/** 1匹のポケモンのわざ習得データ */
export interface PokemonWaza {
  /** 地方図鑑番号 */
  no: string;
  /** 全国図鑑番号 */
  globalNo: string;
  /** フォルムごとのわざ習得データ */
  form: FormWaza[];
}

/**
 * 1フォルムのわざ習得データ
 *
 * 固定プロパティ (`form`, `region` 等) に加え、
 * 動的キーとして習得条件を持つ:
 * - 数値文字列キー (例: `"7"`, `"10"`): レベルアップわざ (そのレベルで習得)
 * - `""` (空文字): 基本わざ
 * - `"思い出し"`: 思い出しわざ
 * - `"進化時"`: 進化時に習得するわざ
 * - `"わざマシン"`: わざマシンで習得するわざ名の配列
 */
export interface FormWaza {
  /** フォルム名 */
  form: string;
  /** リージョン名 */
  region: string;
  /** メガシンカ名 */
  mega_evolution: string;
  /** キョダイマックス名 */
  gigantamax: string;
  /** 習得条件をキーとし、わざ名配列を値とする動的プロパティ */
  [condition: string]: string | string[];
}

// ============================================================
// pokedex/{Version}/waza_list.json (わざ詳細)
// ============================================================

/** pokedex/{Version}/waza_list.json のルートオブジェクト */
export interface WazaListFile {
  update: string;
  game_version: string;
  waza_list: WazaListData;
}

/** バージョン名をキーとするわざ一覧 */
export type WazaListData = Record<string, Record<string, MoveDetail>>;

/** わざの詳細情報 */
export interface MoveDetail {
  /** わざのタイプ (例: "ほのお") */
  type: string;
  /** 分類 ("物理", "特殊", "変化") */
  category: string;
  /** 威力 (変化わざは "---" 等) */
  power: string;
  /** 命中率 */
  accuracy: string;
  /** PP */
  pp: string;
  /** わざの説明文 */
  description: string;
}

// ============================================================
// pokedex/{Version}/waza_machine.json (わざマシン)
// ============================================================

/** pokedex/{Version}/waza_machine.json のルートオブジェクト */
export interface WazaMachineFile {
  update: string;
  game_version: string;
  /** わざマシン番号をキー、わざ名を値とする */
  waza_machine: Record<string, string>;
}

// ============================================================
// pokedex/{Version}/evolve.json (バージョン別進化)
// ============================================================

/** pokedex/{Version}/evolve.json のルートオブジェクト */
export interface EvolveFile {
  update: string;
  game_version: string;
  evolve: EvolveData;
}

/** 図鑑名をキーとする進化データ */
export type EvolveData = Record<string, EvolveRegion>;

/** 進化カテゴリ名をキーとする */
export type EvolveRegion = Record<string, EvolveEntries>;

/** 地方図鑑番号をキーとする進化エントリ */
export type EvolveEntries = Record<string, EvolutionEntry>;

/** 1匹のポケモンの進化情報 */
export interface EvolutionEntry {
  /** 全国図鑑番号 */
  globalNo: string;
  /**
   * 進化先の配列
   *
   * 空文字キーの値として格納。各要素は `{地方図鑑番号: 進化条件}` のオブジェクト。
   */
  [key: string]: string | EvolutionTarget[];
}

/** 進化先。地方図鑑番号をキー、進化条件 (例: "LV16") を値とする。 */
export type EvolutionTarget = Record<string, string>;

// ============================================================
// pokedex/evolve_changeform.json (進化・フォルムチェンジ)
// ============================================================

/** pokedex/evolve_changeform.json のルートオブジェクト */
export interface EvolveChangeformFile {
  update: string;
  /** 進化データ: globalNoをキー、{進化先内部ID: [進化元内部ID配列]} を値 */
  evolve: Record<string, FormMapping>;
  /** フォルムチェンジデータ: globalNoをキー */
  changeform: Record<string, FormMapping>;
}

/** 変化先の内部IDをキーとし、変化元の内部ID配列を値とする */
export type FormMapping = Record<string, string[]>;

// ============================================================
// ability/ability.json (特性)
// ============================================================

/** ability/ability.json のルートオブジェクト */
export interface AbilityFile {
  update: string;
  game_version: string;
  ability: AbilityData;
}

/** 特性名(日本語)をキーとするデータ */
export type AbilityData = Record<string, AbilityEntry>;

/** バージョン識別子をキーとし、そのバージョンでの特性説明文を値とする */
export type AbilityEntry = Record<string, string>;

// ============================================================
// type/type.json (タイプ相性)
// ============================================================

/** type/type.json のルートオブジェクト */
export interface TypeFile {
  update: string;
  game_version?: string;
  type: TypeMatchupEntry[];
}

/** 1世代のタイプ相性データ */
export interface TypeMatchupEntry {
  /** 対象ゲームバージョン一覧 (注: 元データのキー名は "geme_version") */
  geme_version: string[];
  /** 攻撃タイプ名をキーとするタイプ相性マップ */
  type: Record<string, TypeMatchup>;
}

/** 防御タイプ名をキーとし、ダメージ倍率 (0, 0.5, 1, 2) を値とする */
export type TypeMatchup = Record<string, number>;

// ============================================================
// type/list.json (タイプ一覧)
// ============================================================

/** type/list.json のルートオブジェクト */
export interface TypeListFile {
  update: string;
  type: string[];
}

// ============================================================
// item/*.json (アイテム)
// ============================================================

/** アイテムJSONファイルのルートオブジェクト */
export interface ItemFile {
  item?: ItemEntry[];
  item_ball?: ItemEntry[];
  item_battle?: ItemEntry[];
  item_important?: ItemEntry[];
  item_mail?: ItemEntry[];
  item_recovery?: ItemEntry[];
}

/** アイテムエントリ */
export interface ItemEntry {
  /** アイテム名 (日本語) */
  name: string;
}

// ============================================================
// tag.json (タグ)
// ============================================================

/** tag.json のルートオブジェクト */
export interface TagFile {
  tag: TagData;
}

/** globalNo (数値文字列) をキーとするタグデータ */
export type TagData = Record<string, TagEntry[]>;

/** 1フォルムのタグ情報 */
export interface TagEntry {
  /** ポケモン名 (日本語) */
  name: string;
  /** フォルム名 (通常フォルムは空文字) */
  form: string;
  /** 画像ファイル識別子 (例: "0001", "0003-mega1") */
  img: string;
  /** タグの配列 (登場バージョン等) */
  tag: string[];
}

// ============================================================
// translate/translate.json (タイプ名翻訳)
// ============================================================

/** translate/translate.json のルートオブジェクト */
export interface TranslateFile {
  update: string;
  translate: TranslateEntry[];
}

/** 1項目の多言語翻訳 */
export interface TranslateEntry {
  jpn: string;
  eng?: string;
  ger?: string;
  fra?: string;
  kor?: string;
  chs?: string;
  cht?: string;
}

// ============================================================
// translate/waza.json (わざ名翻訳)
// ============================================================

/** translate/waza.json のルートオブジェクト */
export interface TranslateWazaFile {
  update: string;
  translate: WazaTranslateEntry[];
}

/**
 * わざ翻訳データの1要素
 *
 * わざ名(日本語)をキーとし、多言語翻訳オブジェクトを値とする。
 */
export type WazaTranslateEntry = Record<string, WazaTranslation>;

/** 1つのわざの多言語翻訳 */
export interface WazaTranslation {
  eng?: string;
  fra?: string;
  ger?: string;
  ita?: string;
  spa?: string;
  kor?: string;
  chs?: string;
  cht?: string;
}

// ============================================================
// config/pokedex_config.json (設定)
// ============================================================

/** config/pokedex_config.json のルートオブジェクト */
export interface PokedexConfigFile {
  local_pokedex_mapping: Record<string, LocalPokedexMapping>;
  regions: Record<string, RegionDefinition>;
  version_mapping: Record<string, { version: string }>;
  region_mapping: Record<string, string>;
  default_region_value: string | Record<string, string>;
}

/** 1バージョンの地方図鑑マッピング */
export interface LocalPokedexMapping {
  /** 図鑑名リスト */
  pokedex: Array<{ jpn: string }>;
  /** バージョン名をキーとするバージョン情報 */
  version: Record<string, VersionInfo>;
}

/** バージョン情報 */
export interface VersionInfo {
  /** バージョンタイトル */
  title: string;
  /** 短縮タイトル */
  shortTitle: string;
  /** 説明 (発売日等) */
  description: string;
  /** 画像識別子 */
  image?: string;
}

/** リージョン定義 */
export interface RegionDefinition {
  /** 対応するバージョン識別子 */
  version_key: string;
  /** 図鑑インデックス */
  pokedex_index?: number;
  /** 表示名 (日本語) */
  display_jpn?: string;
}

// ============================================================
// convert/local_global_id_converter.json (ID変換)
// ============================================================

/** convert/local_global_id_converter.json のルートオブジェクト */
export interface LocalGlobalIdConverterFile {
  update: string;
  local_global_id_converter: Record<string, ConverterEntry>;
}

/** 1世代のID変換エントリ */
export interface ConverterEntry {
  /** この世代の開始 globalNo */
  border: number;
  /** この世代の最終 globalNo */
  top: number;
  /** 対応するテーブル/ディレクトリ名 */
  table: string;
}
