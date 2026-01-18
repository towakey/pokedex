# Pokémon JSON API

このリポジトリは、ポケモンの原作ゲームに登場するデータをJSON形式で提供します。  
誰でも自由に利用・改変・再配布できます。  
※本プロジェクトは公式と一切関係ありません。公式へのお問い合わせはお控えください。

---

## データベースの作成

ローカル環境でSQLite3のデータベース(`pokedex.db`)を生成するには、以下を実行してください。

```bash
ruby tools/import_db.rb
```

---

## API ドキュメント

### エンドポイント

| メソッド | パス                                                           | 説明                                 |
| -------- | -------------------------------------------------------------- | ------------------------------------ |
| GET      | `/pokedex.php?region={region}`                                 | 指定した地方または全国の図鑑一覧を取得 |
| GET      | `/pokedex.php?region={region}&no={no}`                         | 指定した図鑑Noの詳細情報を取得       |
| GET      | `/pokedex.php?mode=description&globalNo={globalNo}&language={language}` | 図鑑説明を取得 |
| GET      | `/pokedex.php?mode=description&id={id}&language={language}`    | ポケモンID指定で図鑑説明を取得        |
| GET      | `/pokedex.php?mode=search&item=description&word={word}`       | 図鑑説明を検索                       |
| GET      | `/pokedex.php?mode=exists&region={region}&no={globalNo}`      | 指定globalNoが地方図鑑に存在するか確認 |
| GET      | `/pokedex.php?mode=exists&region={region}&id={id}`            | 指定IDが地方図鑑に存在するか確認       |

### パラメーター

| パラメーター | 種別   | 説明                                                       |
| ------------ | ------ | ---------------------------------------------------------- |
| `region`     | string | 地方名（例: `global`, `kanto`, `johto`, `hoenn`, `sinnoh` など） |
| `no`         | string | 図鑑No                                                     |
| `mode`       | string | モード（`description`, `search`, `exists`）               |
| `globalNo`   | string | 全国図鑑No                                                 |
| `id`         | string | ポケモンの内部ID                                           |
| `language`   | string | 言語コード（例: `jpn`, `eng`）                             |
| `item`       | string | 検索対象項目（現在は`description`のみサポート）             |
| `word`       | string | 検索キーワード                                             |

### サポートしている地方（region）

| region値         | 図鑑名                 | 対応バージョン              |
| ---------------- | --------------------- | ------------------------- |
| `global`         | 全国図鑑              | 全般                      |
| `kanto`          | カントー図鑑          | 赤・緑・青・ピカチュウ     |
| `johto`          | ジョウト図鑑          | 金・銀・クリスタル         |
| `hoenn`          | ホウエン図鑑          | ルビー・サファイア・エメラルド |
| `kanto_frlg`     | カントー図鑑          | ファイアレッド・リーフグリーン |
| `sinnoh`         | シンオウ図鑑          | ダイヤモンド・パール・プラチナ |
| `johto_hgss`     | ジョウト図鑑          | ハートゴールド・ソウルシルバー |
| `unova_bw`       | イッシュ図鑑          | ブラック・ホワイト         |
| `unova_b2w2`     | イッシュ図鑑          | ブラック2・ホワイト2       |
| `central_kalos`  | セントラルカロス図鑑   | X・Y                      |
| `coast_kalos`    | コーストカロス図鑑     | X・Y                      |
| `mountain_kalos` | マウンテンカロス図鑑   | X・Y                      |
| `alola_sm`       | アローラ図鑑          | サン・ムーン               |
| `alola_usum`     | アローラ図鑑          | ウルトラサン・ウルトラムーン |
| `galar`          | ガラル図鑑            | ソード・シールド           |
| `crown_tundra`   | カンムリ雪原図鑑       | ソード・シールド           |
| `isle_of_armor`  | ヨロイ島図鑑          | ソード・シールド           |
| `hisui`          | ヒスイ図鑑            | レジェンズアルセウス       |
| `paldea`         | パルデア図鑑          | スカーレット・バイオレット  |
| `kitakami`       | キタカミ図鑑          | スカーレット・バイオレット  |
| `blueberry`      | ブルーベリー図鑑       | スカーレット・バイオレット  |

### レスポンス例

#### 全国図鑑一覧取得

```http
GET http://localhost/pokedex/pokedex.php?region=global
```

```json
{
  "success": true,
  "data": {
    "1": [
      {
        "id": "1",
        "globalNo": "1",
        "no": "1",
        "name": {
          "jpn": "フシギダネ",
          "eng": "Bulbasaur",
          "ger": "Bisasam",
          "fra": "Bulbizarre",
          "kor": "이상해씨",
          "chs": "妙蛙种子",
          "cht": "妙蛙種子"
        },
        "type1": "くさ",
        "type2": "どく",
        "classification": {
          "jpn": "たねポケモン",
          "eng": "Seed Pokémon"
        },
        "forms": {
          "jpn": "",
          "eng": ""
        },
        "height": "0.7",
        "weight": "6.9"
      }
    ]
    // ...
  },
  "region": null
}
```

#### 地方図鑑一覧取得

```http
GET http://localhost/pokedex/pokedex.php?region=kanto
```

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
          "jpn": "たねポケモン"
        },
        "forms": {},
        "height": "0.7",
        "weight": "6.9",
        "hp": 45,
        "attack": 49,
        "defense": 49,
        "special_attack": 65,
        "special_defense": 65,
        "speed": 45,
        "ability1": "しんりょく",
        "ability2": null,
        "dream_ability": "ようりょくそ"
      }
    ]
    // ...
  },
  "region": "カントー図鑑"
}
```

#### 図鑑詳細取得

```http
GET http://localhost/pokedex/pokedex.php?region=kanto&no=1
```

```json
{
  "success": true,
  "data": {
    "1": [
      {
        "id": "1",
        "no": "1",
        "globalNo": "1",
        // 基本情報は一覧と同様
        "description": {
          "red": {
            "jpn": "うまれたときから　せなかに　ふしぎな　タネが　うえてあって　からだと　ともに　そだつという。",
            "eng": ""
          },
          "green": {
            "jpn": "うまれたときから　せなかに　ふしぎな　タネが　うえてあって　からだと　ともに　そだつという。",
            "eng": ""
          }
          // ...
        },
        "ability1_description": {
          "jpn": "ピンチのとき　くさタイプの　わざの　いりょくが　あがる。"
        }
        // ...
      }
    ]
  },
  "region": "カントー図鑑"
}
```

#### 図鑑説明取得

```http
GET http://localhost/pokedex/pokedex.php?mode=description&globalNo=1&language=jpn
```

```json
{
  "success": true,
  "data": [
    {
      "ver": "red",
      "version": "red_green_blue_pikachu",
      "pokedex": "カントー図鑑",
      "description": "うまれたときから　せなかに　ふしぎな　タネが　うえてあって　からだと　ともに　そだつという。"
    },
    {
      "ver": "green",
      "version": "red_green_blue_pikachu", 
      "pokedex": "カントー図鑑",
      "description": "うまれたときから　せなかに　ふしぎな　タネが　うえてあって　からだと　ともに　そだつという。"
    }
    // ...
  ]
}
```

#### 図鑑説明検索

```http
GET http://localhost/pokedex/pokedex.php?mode=search&item=description&word=ほのお
```

```json
{
  "success": true,
  "data": [
    {
      "id": "4",
      "ver": "red",
      "version": "red_green_blue_pikachu",
      "pokedex": "カントー図鑑",
      "language": "jpn",
      "description": "うまれたときから　しっぽに　ほのおが　ともっている。しっぽの　ほのおが　きえたとき　その　いのちは　おわってしまう。",
      "no": "4",
      "globalNo": "4",
      "region": "カントー図鑑",
      "version_info": "red_green_blue_pikachu",
      "name": {
        "jpn": "ヒトカゲ",
        "eng": "Charmander"
      }
    }
    // ...
  ],
  "search_word": "ほのお",
  "results_count": 42
}
```

#### 存在確認

```http
GET http://localhost/pokedex/pokedex.php?mode=exists&region=kanto&no=151
```

```json
{
  "success": true,
  "result": 150
}
```

---

## 貢献方法

- IssueやPull Requestは大歓迎です。
- データの誤りや提案があればお気軽にご連絡ください。

---

## ライセンス

MITライセンス

Copyright (c) 2025 kyoswin7

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
