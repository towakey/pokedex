# Pokedex API 仕様書

## 概要
Pokedex APIは`pokedex.db`（SQLite3）を参照しながら、ポケモンの地域図鑑データや図鑑説明、検索機能、存在判定、図鑑説明マッピングを1つのエンドポイント`pokedex.php`で提供します。

## 基本情報
- **エンドポイント**: `pokedex.php`
- **HTTPメソッド**: GET（プリフライトではOPTIONSを許可）
- **レスポンス形式**: JSON
- **文字エンコーディング**: UTF-8
- **CORS**: `Access-Control-Allow-Origin: *`
- **設定ファイル**: `config/pokedex_config.json`（地域/バージョン/VerIDマッピング）

## 共通レスポンス形式
### 成功時
```json
{
    "success": true,
    "data": {},
    "region": "カントー図鑑",
    "search_word": "たね",
    "search_items": ["description"],
    "language": "jpn",
    "results_count": 1,
    "globalNo": "0001"
}
```
`region`、`search_word`、`search_items`、`language`、`results_count`、`globalNo`などはモードに応じて任意で付与されます。

### エラー時
```json
{
    "success": false,
    "error": "エラーメッセージ"
}
```
HTTPステータスコードは常に200で返却されます。

## モード一覧
| mode値 | 説明 | 主な追加パラメータ |
|--------|------|-------------------|
| (未指定) | 地域図鑑の一覧/詳細取得 | `region`, `no` |
| `description` | 特定ポケモンの図鑑説明を取得 | `globalNo` or `id`, `language` |
| `search` | 図鑑説明/名称/分類を全文検索 | `items`, `word`, `language` |
| `exists` | 地域図鑑内の存在有無を確認 | `region`, `no` or `id` |
| `description_map` | 図鑑説明のバージョングループマッピング取得 | `region`, `no` |

## 共通クエリパラメータ
| パラメータ | 型 | 必須 | 既定値 | 説明 |
|------------|----|------|--------|------|
| `mode` | string | × | `null` | 処理モードを切り替え。未指定時は地域図鑑アクセス。 |
| `region` | string | 条件付き | `null` | 地域コード。`mode`未指定/`exists`/`description_map`で利用。 |
| `no` | integer or string | 条件付き | `null` | 地域図鑑番号。`region`とセットで使用。`description_map`では全国図鑑番号扱い。 |
| `id` | integer or string | 条件付き | `null` | `pokedex.id`。`mode=description`/`search`/`exists`で利用可能。 |
| `globalNo` | integer or string | 条件付き | `null` | 全国図鑑番号。4桁ゼロ埋め・素の数値どちらも許容。 |
| `language` | string | 条件付き | `jpn` | 言語コード。`mode=description`必須、`mode=search`は`all`指定でフィルタ解除。 |
| `items` | string | `mode=search`で必須 | `description` | 検索対象（カンマ区切り）: `description`/`name`/`classification`。 |
| `word` | string | `mode=search`で必須 | `null` | 部分一致検索キーワード。 |

## モード詳細

### 1. 地域図鑑アクセス（mode未指定）
- **一覧取得**: `region`のみ指定。`
  - `region=global`では`pokedex`テーブルを対象に、`data`キーは`globalNo`（ゼロ埋め）で配列を返却。
  - その他の地域は`local_pokedex`を基点に関連テーブルを引き、`hp`や`ability`等も含め返却。
- **詳細取得**: `region`と`no`を併用。該当地域・番号のレコードを1配列として返却し、バージョンごとの図鑑説明（通常版 + `_kanji`派生）を初期化済みで提供。

#### 一覧レスポンス例（region=global）
```json
{
    "success": true,
    "data": {
        "0001": [
            {
                "id": "0001",
                "no": "0001",
                "globalNo": "0001",
                "pokedex": "全国図鑑",
                "version": "global",
                "name": {
                    "jpn": "フシギダネ",
                    "eng": "Bulbasaur"
                },
                "type1": "くさ",
                "type2": "どく",
                "classification": {
                    "jpn": "たねポケモン",
                    "eng": "Seed Pokémon"
                },
                "forms": {
                    "jpn": "通常"
                },
                "egg": ["植物", "怪獣"],
                "height": "0.7",
                "weight": "6.9"
            }
        ]
    },
    "region": "全国図鑑"
}
```

#### 詳細レスポンス例（region=kanto, no=1）
```json
{
    "success": true,
    "data": {
        "001": [
            {
                "id": "0001",
                "no": "001",
                "globalNo": "0001",
                "pokedex": "カントー図鑑",
                "version": "red_green_blue_pikachu",
                "name": {
                    "jpn": "フシギダネ",
                    "eng": "Bulbasaur"
                },
                "type1": "くさ",
                "type2": "どく",
                "classification": {
                    "jpn": "たねポケモン",
                    "eng": "Seed Pokémon"
                },
                "forms": {
                    "jpn": "通常"
                },
                "height": "0.7",
                "weight": "6.9",
                "hp": "45",
                "attack": "49",
                "defense": "49",
                "special_attack": "65",
                "special_defense": "65",
                "speed": "45",
                "ability1": "しんりょく",
                "ability2": null,
                "dream_ability": "ようりょくそ",
                "ability1_description": {
                    "jpn": "HPが減るとくさタイプの技の威力が上がる。"
                },
                "ability2_description": {
                    "": ""
                },
                "dream_ability_description": {
                    "jpn": "天気が晴れのとき素早さが上がる。"
                },
                "description": {
                    "red": {
                        "jpn": "たねポケモン。背中に種がついている。",
                        "eng": ""
                    },
                    "red_kanji": {
                        "jpn": "",
                        "eng": ""
                    },
                    "green": {
                        "jpn": "たねポケモン。背中に種がついている。",
                        "eng": ""
                    },
                    "green_kanji": {
                        "jpn": "",
                        "eng": ""
                    }
                }
            }
        ]
    },
    "region": "カントー図鑑"
}
```

### 2. 図鑑説明取得（mode=description）
| パラメータ | 必須 | 説明 |
|------------|------|------|
| `mode` | ✓ | `description`固定 |
| `globalNo` | △ | `id`と排他。全国図鑑番号から内部IDを引く。 |
| `id` | △ | 内部ID。`globalNo`と排他。 |
| `language` | ✓ | 言語コード（例: `jpn`, `eng`） |

レスポンス例:
```json
{
    "success": true,
    "data": [
        {
            "ver": "red",
            "version": "red_green_blue_pikachu",
            "pokedex": "カントー図鑑",
            "description": "たねポケモン。背中に種がついている。"
        }
    ]
}
```
`ver`はゲームバージョン、`version`はバージョングループ名、`pokedex`は地域図鑑名です。

### 3. 図鑑説明検索（mode=search）
| パラメータ | 必須 | 説明 |
|------------|------|------|
| `mode` | ✓ | `search`固定 |
| `word` | ✓ | 部分一致で検索する語句 |
| `items` | ✓ | 検索対象。カンマ区切りで`description`/`name`/`classification`を指定。 |
| `language` | × | 既定`jpn`。`all`指定で言語制限なし。 |

レスポンス例:
```json
{
    "success": true,
    "data": [
        {
            "id": "0001",
            "matched_fields": ["description", "name"],
            "description": "たねポケモン。背中に種がついている。",
            "verID": "01_00",
            "matched_name": "フシギダネ",
            "name": {
                "jpn": "フシギダネ",
                "eng": "Bulbasaur"
            },
            "imageId": "0001",
            "globalNo": "0001",
            "pokedex": "カントー図鑑",
            "no": "001",
            "ver": "red_green_blue_pikachu",
            "language": "jpn"
        }
    ],
    "search_word": "たね",
    "search_items": ["description", "name"],
    "language": "jpn",
    "results_count": 1
}
```
`verID`は`pokedex_description.verID`、`ver`は`convertVerIdToVersion()`で変換したバージョングループ名です。

### 4. 地域存在確認（mode=exists）
| パラメータ | 必須 | 説明 |
|------------|------|------|
| `mode` | ✓ | `exists`固定 |
| `region` | ✓ | 地域コード。`global`は特別扱い。 |
| `no` | △ | 地域図鑑番号。`id`と排他。 |
| `id` | △ | 内部ID。`no`と排他。 |

レスポンス例（地域指定時）:
```json
{
    "success": true,
    "result": 25
}
```
レスポンス例（`region=global`）:
```json
{
    "success": true,
    "result": {
        "0001": {
            "kanto": 1,
            "kanto_frlg": 1,
            "johto": 226,
            "johto_hgss": 231,
            "hoenn": -1,
            "galar": -1,
            "paldea": -1
        }
    }
}
```
`-1`は該当地域で未収録であることを示します。

### 5. 図鑑説明マッピング取得（mode=description_map）
| パラメータ | 必須 | 説明 |
|------------|------|------|
| `mode` | ✓ | `description_map`固定 |
| `region` | ✓ | 現状`global`のみサポート |
| `no` | ✓ | 全国図鑑番号（数値または4桁文字列） |

レスポンス例:
```json
{
    "success": true,
    "data": {
        "0001": {
            "red_green_blue_pikachu": {
                "raw_ver_group": "01_00,01_01,01_10,01_20",
                "ver_ids": ["01_00", "01_01", "01_10", "01_20"],
                "version_names": ["red", "green", "blue", "pikachu"],
                "representative_ver_id": "01_20",
                "common": {
                    "jpn": "たねポケモン。背中に種がついている。"
                },
                "red": {
                    "jpn": "たねポケモン。背中に種がついている。"
                }
            }
        }
    },
    "globalNo": "0001"
}
```
`pokedex_dex_map`のverIDグループを用いて、共通説明・個別verID説明をまとめて返却します。

## 対応地域一覧
| 地域コード | 地域名（日本語） | バージョングループ | 図鑑説明用バージョン配列 |
|------------|------------------|----------------------|---------------------------|
| global | 全国図鑑 | global | global |
| kanto | カントー図鑑 | red_green_blue_pikachu | red, green, blue, pikachu |
| johto | ジョウト図鑑 | gold_silver_crystal | gold, silver, crystal |
| hoenn | ホウエン図鑑 | ruby_sapphire_emerald | ruby, sapphire, emerald |
| kanto_frlg | カントー図鑑 | firered_leafgreen | firered, leafgreen |
| sinnoh | シンオウ図鑑 | diamond_pearl_platinum | diamond, pearl, platinum |
| johto_hgss | ジョウト図鑑 | heartgold_soulsilver | heartgold, soulsilver |
| unova_bw | イッシュ図鑑 | black_white | black, white |
| unova_b2w2 | イッシュ図鑑 | black2_white2 | black2, white2 |
| central_kalos | セントラルカロス図鑑 | x_y | x, y |
| coast_kalos | コーストカロス図鑑 | x_y | x, y |
| mountain_kalos | マウンテンカロス図鑑 | x_y | x, y |
| alola_sm | アローラ図鑑 | sun_moon | sun, moon |
| alola_usum | アローラ図鑑 | ultrasun_ultramoon | ultrasun, ultramoon |
| galar | ガラル図鑑 | sword_shield | sword, shield |
| crown_tundra | カンムリ雪原図鑑 | sword_shield | sword, shield |
| isle_of_armor | ヨロイ島図鑑 | sword_shield | sword, shield |
| hisui | ヒスイ図鑑 | legendsarceus | legendsarceus |
| paldea | パルデア図鑑 | scarlet_violet | scarlet, violet |
| kitakami | キタカミ図鑑 | scarlet_violet | scarlet, violet |
| blueberry | ブルーベリー図鑑 | scarlet_violet | scarlet, violet |
※ `_kanji`派生は自動的に付加され、空文字で初期化されます。

## レスポンスフィールド詳細
### 共通フィールド
| フィールド | 型 | 説明 |
|------------|----|------|
| `id` | string | `pokedex.id`（フォームID）。 |
| `no` | string | 地域図鑑番号。`region=global`では`globalNo`と同値。 |
| `globalNo` | string | 全国図鑑番号（ゼロ埋め文字列）。 |
| `pokedex` | string | 図鑑名（日本語）。 |
| `version` | string or null | バージョングループ名。 |
| `name` | object | 言語別名称（`pokedex_name`）。 |
| `classification` | object | 言語別分類（`pokedex_classification`）。 |
| `forms` | object | 言語別フォーム名（`pokedex_form`）。 |
| `type1`/`type2` | string or null | タイプ（`local_pokedex_type`）。 |
| `egg` | array | タマゴグループ（グローバル一覧/詳細で返却）。 |
| `height`/`weight` | string | 身長(m)/体重(kg)。 |
| `hp`/`attack`/`defense`/`special_attack`/`special_defense`/`speed` | string | 能力値（地域指定時のみ）。 |
| `ability1`/`ability2`/`dream_ability` | string or null | 特性。 |
| `ability*_description` | object | 特性説明。見つからない場合は`{"": ""}`。 |
| `description` | object | バージョン x 言語の図鑑説明。未取得でも全バージョンキーを初期化。 |
| `imageId` | string | `pokedex_name.id`。`mode=search`のみ。 |
| `ver` | string or null | `local_pokedex.version`を`version_mapping`で変換した値（searchのみ）。 |
| `matched_fields` | array | `mode=search`でヒットした項目名。 |
| `matched_name`/`matched_classification` | string | ヒットした名称/分類。 |
| `verID` | string | `pokedex_description.verID`（searchのみ）。 |

### description_map固有
| フィールド | 型 | 説明 |
|------------|----|------|
| `raw_ver_group` | string | CSV由来のverID連結文字列。 |
| `ver_ids` | array | 個別verID。 |
| `version_names` | array | `version_mapping`の英語名。 |
| `representative_ver_id` | string | グループ代表のverID。 |
| `common` | object | グループ共通説明（存在時のみ）。 |
| `<version_name>` | object | 個別verIDの言語別説明。 |

## エラーメッセージ例
- **`globalNo または id のいずれかを指定してください`**: `mode=description`で識別子未指定。
- **`language を指定してください`**: `mode=description`で`language`未指定。
- **`word を指定してください`**: `mode=search`でキーワード未指定。
- **`検索項目を指定してください`**: `mode=search`で`items`未指定。
- **`有効な検索項目が指定されていません`**: `items`に未対応値のみ指定。
- **`リージョンまたはポケモンNoを指定してください`**: `mode`未指定時に必須パラメータ不足。
- **`無効なリージョンが指定されました`**: `validRegions`に存在しない`region`指定。
- **`region と (no または id) を指定してください`**: `mode=exists`で引数不足。
- **`region と no を指定してください`**: `mode=description_map`で必須不足。
- **`description_map モードでは region=global のみサポートしています`**: 非対応`region`指定。
- **`設定ファイルが見つかりません` / `設定ファイルの読み込みに失敗しました`**: `description_map`処理時に設定ファイル異常。
- **`指定されたポケモンが見つかりません`**: DB照会結果が空。

## 使用例
```text
GET pokedex.php?region=kanto
GET pokedex.php?region=kanto&no=25
GET pokedex.php?mode=description&globalNo=25&language=jpn
GET pokedex.php?mode=search&items=description,name&word=たね&language=jpn
GET pokedex.php?mode=exists&region=paldea&no=1
GET pokedex.php?mode=exists&region=global&no=1
GET pokedex.php?mode=description_map&region=global&no=25
```

## 注意事項
- **`no`/`globalNo`の入力形式**: 数値/文字列どちらも受付。内部処理で文字列比較する箇所があるためゼロ埋め推奨。
- **`language=all`検索**: 言語フィルタが外れるためレスポンス件数が多くなる可能性があります。
- **特性説明の取得範囲**: 一致するバージョン1件のみを返却し、見つからない場合は空オブジェクト（`{"": ""}`）。
- **version_mapping依存**: VerIDからバージョングループ名/英語名へ変換する際に`pokedex_config.json`が必須です。
- **description_mapの前提**: `pokedex_dex_map`テーブル（`csv_to_sql_pokedex_dex_map.rb`などで投入）を利用します。
- **大量クエリ**: 現実装はテーブル結合ではなく個別クエリを多用するため、高頻度アクセス時はキャッシュを検討してください。

## 参照テーブル
- `pokedex`
- `local_pokedex`
- `pokedex_name`
- `pokedex_classification`
- `pokedex_form`
- `pokedex_egg`
- `local_pokedex_type`
- `local_pokedex_status`
- `local_pokedex_ability`
- `local_pokedex_description`
- `pokedex_description`
- `ability_language`
- `pokedex_dex_map`

