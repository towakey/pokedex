# Pokedex API 仕様書

## 概要
Pokedex APIは、ポケモンの図鑑情報を提供するRESTful APIです。地域別図鑑、ポケモンの詳細情報、図鑑説明の検索などの機能を提供します。

## 基本情報
- **エンドポイント**: `pokedex.php`
- **HTTPメソッド**: GET
- **レスポンス形式**: JSON
- **文字エンコーディング**: UTF-8
- **CORS**: 全てのオリジンからのアクセスを許可

## 共通レスポンス形式

### 成功時
```json
{
    "success": true,
    "data": {},
    "region": "地域名（日本語）"
}
```

### エラー時
```json
{
    "success": false,
    "error": "エラーメッセージ"
}
```

## エンドポイント一覧

### 1. 図鑑説明取得 (mode=description)

指定したポケモンの図鑑説明を言語別に取得します。

#### リクエストパラメータ
| パラメータ | 型 | 必須 | 説明 |
|-----------|---|------|------|
| mode | string | ✓ | "description" 固定 |
| globalNo | integer | ○ | 全国図鑑番号（idとの択一） |
| id | integer | ○ | ポケモンID（globalNoとの択一） |
| language | string | ✓ | 言語コード（例: "jpn", "eng"） |

#### レスポンス例
```json
{
    "success": true,
    "data": [
        {
            "id": "1",
            "language": "jpn",
            "description": "たねポケモン。背中に種がついている。"
        }
    ]
}
```

### 2. 図鑑説明検索 (mode=search)

図鑑説明内のテキストを検索します。

#### リクエストパラメータ
| パラメータ | 型 | 必須 | 説明 |
|-----------|---|------|------|
| mode | string | ✓ | "search" 固定 |
| item | string | ✓ | "description" 固定（現在サポートされている項目） |
| word | string | ✓ | 検索キーワード |

#### レスポンス例
```json
{
    "success": true,
    "data": [
        {
            "id": "1",
            "language": "jpn",
            "description": "たねポケモン。背中に種がついている。",
            "ver": "red"
        }
    ]
}
```

### 3. 存在確認 (mode=exists)

指定したポケモンが地域図鑑に存在するかを確認します。

#### リクエストパラメータ
| パラメータ | 型 | 必須 | 説明 |
|-----------|---|------|------|
| mode | string | ✓ | "exists" 固定 |
| region | string | ✓ | 地域名 |
| no | integer | ○ | 図鑑番号（idとの択一） |
| id | integer | ○ | ポケモンID（noとの択一） |

#### レスポンス例
```json
{
    "success": true,
    "result": 1
}
```
※ `result`: 地域図鑑での番号。存在しない場合は -1

### 4. 地域図鑑一覧取得

指定した地域の全ポケモン一覧を取得します。

#### リクエストパラメータ
| パラメータ | 型 | 必須 | 説明 |
|-----------|---|------|------|
| region | string | ✓ | 地域名 |

#### レスポンス例
```json
{
    "success": true,
    "data": {
        "1": [
            {
                "id": "1",
                "no": "1",
                "globalNo": "1",
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
                "description": {
                    "red": {
                        "jpn": "たねポケモン。背中に種がついている。",
                        "eng": "A strange seed was planted on its back at birth."
                    }
                }
            }
        ]
    },
    "region": "カントー図鑑"
}
```

### 5. ポケモン詳細情報取得

指定した地域の特定のポケモンの詳細情報を取得します。

#### リクエストパラメータ
| パラメータ | 型 | 必須 | 説明 |
|-----------|---|------|------|
| region | string | ✓ | 地域名 |
| no | integer | ✓ | 図鑑番号 |

#### レスポンス例
```json
{
    "success": true,
    "data": {
        "1": [
            {
                "id": "1",
                "no": "1",
                "globalNo": "1",
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
                    "jpn": "HPが減ったときくさタイプの技の威力が上がる。"
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
                    "green": {
                        "jpn": "たねポケモン。背中に種がついている。",
                        "eng": ""
                    }
                }
            }
        ]
    },
    "region": "カントー図鑑"
}
```

## 対応地域一覧

| 地域コード | 地域名（日本語） | バージョン | 対応ゲーム |
|-----------|-----------------|-----------|------------|
| global | 全国図鑑 | global | 全ゲーム |
| kanto | カントー図鑑 | red_green_blue_pikachu | 赤・緑・青・ピカチュウ |
| johto | ジョウト図鑑 | gold_silver_crystal | 金・銀・クリスタル |
| hoenn | ホウエン図鑑 | ruby_sapphire_emerald | ルビー・サファイア・エメラルド |
| sinnoh | シンオウ図鑑 | diamond_pearl_platinum | ダイヤモンド・パール・プラチナ |
| unova | イッシュ図鑑 | black_white | ブラック・ホワイト |
| unova_b2w2 | イッシュ図鑑 | black2_white2 | ブラック2・ホワイト2 |
| central_kalos | セントラルカロス図鑑 | x_y | X・Y |
| coast_kalos | コーストカロス図鑑 | x_y | X・Y |
| mountain_kalos | マウンテンカロス図鑑 | x_y | X・Y |
| alola_sm | アローラ図鑑 | sun_moon | サン・ムーン |
| alola_usum | アローラ図鑑 | ultrasun_ultramoon | ウルトラサン・ウルトラムーン |
| galar | ガラル図鑑 | sword_shield | ソード・シールド |
| crown_tundra | カンムリ雪原図鑑 | sword_shield | ソード・シールド |
| isle_of_armor | ヨロイ島図鑑 | sword_shield | ソード・シールド |
| hisui | ヒスイ図鑑 | legendsarceus | レジェンズアルセウス |
| paldea | パルデア図鑑 | scarlet_violet | スカーレット・バイオレット |
| kitakami | キタカミ図鑑 | scarlet_violet | スカーレット・バイオレット |
| blueberry | ブルーベリー図鑑 | scarlet_violet | スカーレット・バイオレット |

## レスポンスフィールド詳細

### ポケモン基本情報
| フィールド | 型 | 説明 |
|-----------|---|------|
| id | string | ポケモンの内部ID |
| no | string | 地域図鑑での番号 |
| globalNo | string | 全国図鑑番号 |
| pokedex | string | 図鑑名（日本語） |
| version | string | ゲームバージョン識別子 |

### ポケモン名称・分類
| フィールド | 型 | 説明 |
|-----------|---|------|
| name | object | ポケモン名（言語別） |
| name.jpn | string | 日本語名 |
| name.eng | string | 英語名 |
| classification | object | 分類（言語別） |
| classification.jpn | string | 日本語分類 |
| classification.eng | string | 英語分類 |

### ポケモンタイプ・能力値
| フィールド | 型 | 説明 |
|-----------|---|------|
| type1 | string | タイプ1 |
| type2 | string/null | タイプ2（なしの場合はnull） |
| height | string | 高さ（メートル） |
| weight | string | 重さ（キログラム） |
| hp | string | HP |
| attack | string | こうげき |
| defense | string | ぼうぎょ |
| special_attack | string | とくこう |
| special_defense | string | とくぼう |
| speed | string | すばやさ |

### 特性情報
| フィールド | 型 | 説明 |
|-----------|---|------|
| ability1 | string/null | 特性1 |
| ability2 | string/null | 特性2 |
| dream_ability | string/null | 夢特性 |
| ability1_description | object | 特性1の説明（言語別） |
| ability2_description | object | 特性2の説明（言語別） |
| dream_ability_description | object | 夢特性の説明（言語別） |

### 図鑑説明
| フィールド | 型 | 説明 |
|-----------|---|------|
| description | object | 図鑑説明（バージョン・言語別） |
| description.{version} | object | 各バージョンの説明 |
| description.{version}.jpn | string | 日本語説明 |
| description.{version}.eng | string | 英語説明 |

## エラーハンドリング

### よくあるエラー
- `無効なリージョンが指定されました`: 対応していない地域コードが指定された場合
- `region と (no または id) を指定してください`: 必須パラメータが不足している場合
- `指定されたポケモンが見つかりません`: 指定された番号のポケモンが存在しない場合
- `現在はitem=descriptionのみサポートしています`: 検索モードで未対応の項目が指定された場合
- `リージョンまたはポケモンNoを指定してください`: 基本的なパラメータが不足している場合

## 使用例

### 1. カントー図鑑の全ポケモン取得
```
GET pokedex.php?region=kanto
```

### 2. フシギダネの詳細情報取得
```
GET pokedex.php?region=kanto&no=1
```

### 3. ポケモンの図鑑説明取得
```
GET pokedex.php?mode=description&globalNo=1&language=jpn
```

### 4. 図鑑説明検索
```
GET pokedex.php?mode=search&item=description&word=たね
```

### 5. ポケモンの存在確認
```
GET pokedex.php?mode=exists&region=kanto&no=1
```

## 注意事項

1. **数値ソート**: ポケモン番号は数値として正しくソートされます（1, 2, 10の順）
2. **バージョン依存**: 一部の情報（タイプ、ステータス、特性）はゲームバージョンによって異なる場合があります
3. **言語対応**: 現在は日本語（jpn）と英語（eng）に対応していますが、全ての項目で両言語が利用可能とは限りません
4. **CORS**: 全てのオリジンからのアクセスが許可されていますが、本番環境では適切に制限することを推奨します
5. **特性説明**: 特性の説明は対応するゲームバージョンから取得されますが、存在しない場合は空文字列が返されます

## データベース構造

このAPIは以下のSQLiteテーブルを使用しています：
- `pokedex`: 基本ポケモン情報
- `local_pokedex`: 地域図鑑情報
- `pokedex_name`: ポケモン名（多言語）
- `pokedex_classification`: ポケモン分類（多言語）
- `local_pokedex_type`: タイプ情報
- `local_pokedex_status`: ステータス情報
- `local_pokedex_ability`: 特性情報
- `ability_language`: 特性説明（多言語）
- `local_pokedex_description`: 図鑑説明（多言語・バージョン別）
