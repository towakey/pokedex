# Pokémon JSON API

このリポジトリは、ポケモンの原作ゲームに登場するデータをJSON形式で提供します。  
誰でも自由に利用・改変・再配布できます。  
※本プロジェクトは公式と一切関係ありません。公式へのお問い合わせはお控えください。

---

## データベースの作成

ローカル環境でSQLite3のデータベース(`pokedex.db`)を生成するには、以下を実行してください。

```bash
ruby import_db.rb
```

---

## API ドキュメント

### エンドポイント

| メソッド | パス                                                           | 説明                               |
| -------- | -------------------------------------------------------------- | ---------------------------------- |
| GET      | `/pokedex.php?region={region}&mode=index`                      | 指定した地方または全国の図鑑一覧を取得 |
| GET      | `/pokedex.php?region={region}&mode=details&no={no}`            | 指定した図鑑Noの詳細情報を取得       |

### パラメーター

| パラメーター | 種別   | 説明                                                       |
| ------------ | ------ | ---------------------------------------------------------- |
| `region`     | string | 地方名（例: `global`, `kanto` など）                      |
| `mode`       | string | モード（`index` または `details`）                        |
| `no`         | string | 図鑑No（`details`モード時に必須）                         |

### レスポンス例

#### 全国図鑑一覧取得（indexモード）

```http
GET http://localhost/pokedex/pokedex.php?region=global&mode=index
```

```json
{
  "status": "success",
  "data": [
    {
      "no": "1",
      "jpn": "フシギダネ",
      "eng": "Bulbasaur",
      "classification": "たねポケモン",
      "height": "0.7",
      "weight": "6.9"
    }
    // ...
  ]
}
```

#### 図鑑詳細取得（detailsモード）

```http
GET http://localhost/pokedex/pokedex.php?region=global&mode=details&no=3
```

```json
{
  "status": "success",
  "data": [
    {
      "no": "3",
      "jpn": "フシギバナ",
      "eng": "Venusaur",
      "classification": "たねポケモン",
      "height": "2.0",
      "weight": "100.0"
    }
    // メガシンカやキョダイマックスを含む複数フォーム
  ],
  "message": "This API is the Pokédex API."
}
```

#### 各地方図鑑一覧取得

```http
GET http://localhost/pokedex/pokedex.php?region=kanto&mode=index
```

```json
{
  "status": "success",
  "data": [
    {
      "no": "1",
      "globalNo": "1",
      "form": "",
      "region": "",
      "mega_evolution": "",
      "gigantamax": "",
      "jpn": "フシギダネ",
      "eng": "Bulbasaur",
      "ger": "Bisasam",
      "fra": "Bulbizarre",
      "kor": "이상해씨",
      "chs": "妙蛙种子",
      "cht": "妙蛙種子",
      "type1": "くさ",
      "type2": "どく"
    },
    // ...
  ]
}
```

#### 各地方図鑑詳細取得

```http
GET http://localhost/pokedex/pokedex.php?mode=detail&region=central_kalos&no=85
```

```json
{
  "status": "success",
  "data": [
    {
      "no": "85",
      "globalNo": "6",
      "form": "",
      "region": "",
      "mega_evolution": "",
      "gigantamax": "",
      "type1": "ほのお",
      "type2": "ひこう",
      "hp": 78,
      "attack": 84,
      "defense": 78,
      "special_attack": 109,
      "special_defense": 85,
      "speed": 100,
      "ability1": "もうか",
      "ability2": "",
      "dream_ability": "サンパワー",
      "x": " くちから しゃくねつの ほのおを はきだすとき シッポのさきは より あかく はげしく もえあがる。",
      "y": " ちじょう 1400メートル まで ハネを つかって とぶことができる。こうねつの ほのおを はく。",
      "jpn": "リザードン",
      "eng": "Charizard",
      "ger": "Glurak",
      "fra": "Dracaufeu",
      "kor": "리자몽",
      "chs": "喷火龙",
      "cht": "噴火龍",
      "classification": "かえんポケモン",
      "height": "1.7",
      "weight": "90.5",
      "ability1_description": "ピンチのとき ほのおの いりょくが あがる。",
      "ability2_description": "",
      "dreame_ability_description": "はれると HPが へるが とくこうが あがる。",
      "waza_list": {
        "": [],
        "思い出し": [],
        "進化時": [],
        "lvup": [],
        "わざマシン": []
      }
    },
    // メガシンカやキョダイマックスを含む複数フォーム
  ],
  "message": "This API is the Pokédex API.",
  "region_name": "セントラルカロス図鑑"
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
