# 静的JSON API エンドポイント仕様

このリポジトリは GitHub Actions で `tools/generate_api.py` を実行し、生成した静的JSONファイル群を **GitHub Pages** にデプロイしています。
サーバー不要で、`fetch()` するだけでポケモンデータを利用できます。

- **APIベースURL**: `https://towakey.github.io/pokedex/api/v1/`
- **バンドルファイルベースURL**: `https://towakey.github.io/pokedex/`
- **レスポンス形式**: JSON (UTF-8)
- **CORS**: GitHub Pages のため任意のオリジンから取得可能
- **認証**: 不要

> **注意**: 全ファイルは静的にビルドされます。`master` ブランチのデータ更新時（`pokedex/`, `ability/`, `type/`, `item/`, `tag.json`, `config/`, `translate/`, `tools/generate_api.py`）に自動で再生成・デプロイされます。

---

## エンドポイント一覧

| パス | 説明 |
| --- | --- |
| `pokedex/index.json` | 全ポケモンの簡易一覧 |
| `pokedex/{globalNo}.json` | ポケモン個別データ（統合版）例: `pokedex/0025.json` |
| `pokedex/region/index.json` | 地方図鑑一覧（メタデータ） |
| `pokedex/region/{slug}.json` | 地方図鑑別ポケモン一覧 例: `pokedex/region/kanto.json` |
| `moves/index.json` | 全バージョンのわざ一覧 |
| `moves/{version_key}.json` | バージョン別わざ詳細 |
| `abilities/index.json` | 特性一覧 |
| `types/index.json` | タイプ一覧 |
| `types/matchup.json` | タイプ相性表 |
| `items/index.json` | アイテム一覧 |
| `meta/versions.json` | 対応バージョン・地方図鑑一覧 |
| `meta/languages.json` | 対応言語一覧 |

### バンドルファイル（`api/v1/` の外、ベースURL直下）

| パス | 説明 |
| --- | --- |
| `pokedex-basic.json` | 全ポケモンの名前・タイプ・種族値のみ |
| `pokedex-full.json` | 全ポケモンの統合データ（個別ファイルと同等） |
| `pokedex-{lang}.json` | 言語別フィルタ済み（`jpn`, `eng`, `fra`, `ita`, `ger`, `spa`, `kor`, `chs`, `cht`） |
| `type-matchup.json` | タイプ相性表のみ |

---

## エンドポイント詳細

### 1. ポケモン一覧 — `pokedex/index.json`

全1025匹の簡易一覧。

```http
GET https://towakey.github.io/pokedex/api/v1/pokedex/index.json
```

```json
{
  "count": 1025,
  "pokedex": [
    {
      "globalNo": "0001",
      "generation": 1,
      "name": { "jpn": "フシギダネ", "eng": "Bulbasaur", "fra": "Bulbizarre", "...": "..." },
      "types": { "type1": "くさ", "type2": "どく" },
      "stats": { "hp": 45, "attack": 49, "defense": 49, "spAtk": 65, "spDef": 65, "speed": 45, "total": 318 }
    }
  ]
}
```

### 2. ポケモン個別データ — `pokedex/{globalNo}.json`

全国図鑑番号（4桁ゼロ埋め）でアクセス。フォルム・進化・タグ・バージョン別データを統合。

```http
GET https://towakey.github.io/pokedex/api/v1/pokedex/0025.json
```

```json
{
  "globalNo": "0025",
  "generation": 1,
  "name": { "jpn": "ピカチュウ", "eng": "Pikachu", "...": "..." },
  "classification": { "jpn": "ねずみポケモン", "...": "..." },
  "eggGroups": ["陸上", "妖精"],
  "height": "0.4",
  "weight": "6.0",
  "types": { "type1": "でんき", "type2": "" },
  "stats": { "hp": 35, "attack": 55, "defense": 40, "spAtk": 50, "spDef": 50, "speed": 90, "total": 320 },
  "abilities": { "ability1": "せいでんき", "ability2": "", "dreamAbility": "ひらいしん" },
  "forms": [
    {
      "internalId": "0025_00000000_0_000_0",
      "form": "", "region": "", "megaEvolution": "", "gigantamax": "",
      "name": { "jpn": "ピカチュウ", "...": "..." },
      "classification": { "jpn": "ねずみポケモン", "...": "..." },
      "height": "0.4", "weight": "6.0"
    }
  ],
  "evolution": {
    "from": [{ "globalNo": "0172", "name": "ピチュー" }],
    "to": [{ "globalNo": "0026", "name": "ライチュウ" }]
  },
  "tags": [],
  "versionData": {
    "red_green_blue_pikachu": [
      {
        "localNo": "0025",
        "pokedex": "カントー図鑑",
        "form": "", "region": "", "megaEvolution": "", "gigantamax": "",
        "type1": "でんき", "type2": "",
        "stats": { "hp": 35, "attack": 55, "defense": 30, "spAtk": 50, "spDef": 50, "speed": 90, "total": 310 },
        "abilities": { "ability1": "", "ability2": "", "dreamAbility": "" },
        "description": { "red": "...", "green": "...", "blue": "...", "pikachu": "..." }
      }
    ]
  }
}
```

| フィールド | 型 | 説明 |
| --- | --- | --- |
| `globalNo` | string | 全国図鑑番号（4桁ゼロ埋め） |
| `generation` | number | 初登場世代 |
| `name` / `classification` | object | 言語別の名前・分類 |
| `eggGroups` | string[] | タマゴグループ |
| `types` | object | `type1` / `type2` |
| `stats` | object | 種族値（`total` は合計） |
| `abilities` | object | `ability1` / `ability2` / `dreamAbility` |
| `forms` | object[] | フォルム別基礎情報 |
| `evolution` | object | 進化元 `from` / 進化先 `to` |
| `tags` | array | タグ（`tag.json` 由来） |
| `versionData` | object | バージョンキーごとの詳細（タイプ・種族値・特性・図鑑説明） |

### 3. 地方図鑑一覧 — `pokedex/region/index.json`

全25地方図鑑（全国図鑑含む）のメタデータ。

```http
GET https://towakey.github.io/pokedex/api/v1/pokedex/region/index.json
```

```json
{
  "count": 25,
  "regions": [
    { "slug": "global", "label": "全国図鑑", "count": 1025 },
    { "slug": "kanto", "label": "カントー図鑑", "count": 151 }
  ]
}
```

### 4. 地方図鑑別ポケモン一覧 — `pokedex/region/{slug}.json`

`slug` を指定して各地方図鑑のポケモンを地方図鑑番号順に取得。

```http
GET https://towakey.github.io/pokedex/api/v1/pokedex/region/kanto.json
```

```json
{
  "slug": "kanto",
  "label": "カントー図鑑",
  "versionKey": "red_green_blue_pikachu",
  "count": 151,
  "pokedex": [
    {
      "localNo": "0001",
      "globalNo": "0001",
      "internalId": "0001_00000000_0_000_0",
      "name": { "jpn": "フシギダネ", "eng": "Bulbasaur", "...": "..." },
      "type1": "くさ",
      "type2": "どく",
      "stats": { "hp": 45, "attack": 49, "defense": 49, "spAtk": 65, "spDef": 65, "speed": 45 },
      "abilities": { "ability1": "しんりょく", "ability2": "", "dreamAbility": "ようりょくそ" },
      "region": "ヒスイのすがた"
    }
  ]
}
```

| フィールド | 型 | 説明 |
| --- | --- | --- |
| `localNo` | string | 地方図鑑番号（4桁ゼロ埋め） |
| `globalNo` | string | 全国図鑑番号 |
| `internalId` | string | 内部ID（フォルム識別） |
| `name` | object | 言語別の名前 |
| `type1` / `type2` | string | タイプ |
| `stats` | object | 種族値 |
| `abilities` | object | 特性 |
| `form` / `region` / `megaEvolution` / `gigantamax` | string | フォルム情報（該当時のみ付与） |

> `global.json` は全国図鑑で、`localNo` と `globalNo` が一致し、`name` / `types` / `stats` のみを含む簡易形式です。

#### 地方図鑑スラッグ一覧

| slug | 図鑑名 | 対応バージョン |
| --- | --- | --- |
| `global` | 全国図鑑 | 全般 |
| `kanto` | カントー図鑑 | 赤・緑・青・ピカチュウ |
| `kanto_frlg` | カントー図鑑 | ファイアレッド・リーフグリーン |
| `johto` | ジョウト図鑑 | 金・銀・クリスタル |
| `johto_hgss` | ジョウト図鑑 | ハートゴールド・ソウルシルバー |
| `hoenn` | ホウエン図鑑 | ルビー・サファイア・エメラルド |
| `hoenn_oras` | ホウエン図鑑 | オメガルビー・アルファサファイア |
| `sinnoh` | シンオウ図鑑 | ダイヤモンド・パール・プラチナ |
| `sinnoh_bdsp` | シンオウ図鑑 | ブリリアントダイヤモンド・シャイニングパール |
| `unova_bw` | イッシュ図鑑 | ブラック・ホワイト |
| `unova_b2w2` | イッシュ図鑑 | ブラック2・ホワイト2 |
| `central_kalos` | セントラルカロス図鑑 | X・Y |
| `coast_kalos` | コーストカロス図鑑 | X・Y |
| `mountain_kalos` | マウンテンカロス図鑑 | X・Y |
| `alola_sm` | アローラ図鑑 | サン・ムーン |
| `alola_usum` | アローラ図鑑 | ウルトラサン・ウルトラムーン |
| `galar` | ガラル図鑑 | ソード・シールド |
| `isle_of_armor` | ヨロイ島図鑑 | ソード・シールド |
| `crown_tundra` | カンムリ雪原図鑑 | ソード・シールド |
| `hisui` | ヒスイ図鑑 | レジェンズアルセウス |
| `paldea` | パルデア図鑑 | スカーレット・バイオレット |
| `kitakami` | キタカミ図鑑 | スカーレット・バイオレット |
| `blueberry` | ブルーベリー図鑑 | スカーレット・バイオレット |
| `lumiose` | ミアレ図鑑 | レジェンズZ-A |
| `hyperspace_lumiose` | 異次元図鑑 | レジェンズZ-A |

### 5. わざ一覧 — `moves/index.json` / `moves/{version_key}.json`

```http
GET https://towakey.github.io/pokedex/api/v1/moves/index.json
GET https://towakey.github.io/pokedex/api/v1/moves/red_green_blue_pikachu.json
```

`moves/{version_key}.json`:

```json
{
  "gameVersion": "red_green_blue_pikachu",
  "count": 165,
  "moves": {
    "はたく": { "type": "ノーマル", "category": "物理", "power": "40", "accuracy": "100", "pp": "35", "description": "..." }
  }
}
```

### 6. 特性一覧 — `abilities/index.json`

```json
{
  "count": 310,
  "abilities": {
    "あくしゅう": {
      "name": "あくしゅう",
      "descriptions": { "ruby_sapphire_emerald": "...", "black_white": "..." }
    }
  }
}
```

### 7. タイプ一覧・相性表 — `types/index.json` / `types/matchup.json`

```json
// types/index.json
{
  "count": 18,
  "types": [
    { "jpn": "ノーマル", "eng": "Normal", "ger": "Normal", "fra": "Normal", "kor": "노말", "chs": "一般", "cht": "一般" }
  ]
}
```

```json
// types/matchup.json （世代ごとに相性が異なるため配列）
{
  "generations": [
    {
      "geme_version": ["red_green_blue_pikachu"],
      "type": {
        "ノーマル": { "ノーマル": 1, "いわ": 0.5, "ゴースト": 0, "...": 1 }
      }
    }
  ]
}
```

相性倍率: `2` = 効果ばつぐん / `1` = 通常 / `0.5` = いまひとつ / `0` = 効果なし。

### 8. アイテム一覧 — `items/index.json`

カテゴリ別にアイテム名を格納。

```json
{
  "item":           { "category": "道具",     "count": 343, "items": [{ "name": "あいいろのたま" }] },
  "item_ball":      { "category": "ボール",   "count": 0,   "items": [] },
  "item_battle":    { "category": "戦闘用",   "count": 0,   "items": [] },
  "item_important": { "category": "大切なもの", "count": 0,  "items": [] },
  "item_mail":      { "category": "メール",   "count": 0,   "items": [] },
  "item_recovery":  { "category": "回復",     "count": 0,   "items": [] }
}
```

### 9. メタ情報 — `meta/versions.json` / `meta/languages.json`

```json
// meta/versions.json
{
  "gameVersions": {
    "red_green_blue_pikachu": {
      "directory": "Red_Green_Blue_Pikachu",
      "generation": 1,
      "pokedexNames": ["カントー図鑑"],
      "versions": [{ "id": "red", "title": "ポケットモンスター 赤", "shortTitle": "赤" }]
    }
  },
  "regions": { "kanto": { "versionKey": "red_green_blue_pikachu", "pokedexIndex": 0, "displayName": "カントー図鑑" } }
}
```

```json
// meta/languages.json
{
  "languages": [
    { "code": "jpn", "name": "日本語", "nameEng": "Japanese" },
    { "code": "eng", "name": "英語",   "nameEng": "English" }
  ]
}
```

---

## 対応バージョンキー（version_key）

| version_key | 世代 | タイトル |
| --- | --- | --- |
| `red_green_blue_pikachu` | 1 | 赤・緑・青・ピカチュウ |
| `gold_silver_crystal` | 2 | 金・銀・クリスタル |
| `ruby_sapphire_emerald` | 3 | ルビー・サファイア・エメラルド |
| `firered_leafgreen` | 3 | ファイアレッド・リーフグリーン |
| `diamond_pearl_platinum` | 4 | ダイヤモンド・パール・プラチナ |
| `heartgold_soulsilver` | 4 | ハートゴールド・ソウルシルバー |
| `black_white` | 5 | ブラック・ホワイト |
| `black2_white2` | 5 | ブラック2・ホワイト2 |
| `x_y` | 6 | X・Y |
| `omegaruby_alphasapphire` | 6 | オメガルビー・アルファサファイア |
| `sun_moon` | 7 | サン・ムーン |
| `ultrasun_ultramoon` | 7 | ウルトラサン・ウルトラムーン |
| `sword_shield` | 8 | ソード・シールド |
| `brilliantdiamond_shiningpearl` | 8 | ブリリアントダイヤモンド・シャイニングパール |
| `legendsarceus` | 8 | レジェンズアルセウス |
| `scarlet_violet` | 9 | スカーレット・バイオレット |
| `legendsza` | 9 | レジェンズZ-A |

---

## 対応言語コード

| code | 言語 |
| --- | --- |
| `jpn` | 日本語 |
| `eng` | 英語 |
| `fra` | フランス語 |
| `ita` | イタリア語 |
| `ger` | ドイツ語 |
| `spa` | スペイン語 |
| `kor` | 韓国語 |
| `chs` | 簡体字中国語 |
| `cht` | 繁体字中国語 |

---

## 利用例

### JavaScript (fetch)

```js
const BASE = 'https://towakey.github.io/pokedex/api/v1'

// ピカチュウの全情報
const pikachu = await fetch(`${BASE}/pokedex/0025.json`).then(r => r.json())
console.log(pikachu.name.jpn)                 // "ピカチュウ"
console.log(pikachu.evolution.from[0].name)   // "ピチュー"

// カントー図鑑の一覧
const kanto = await fetch(`${BASE}/pokedex/region/kanto.json`).then(r => r.json())
console.log(kanto.count)                       // 151
console.log(kanto.pokedex[0].name.jpn)         // "フシギダネ"

// 地方図鑑の一覧を取得
const regions = await fetch(`${BASE}/pokedex/region/index.json`).then(r => r.json())
regions.regions.forEach(r => console.log(r.slug, r.label, r.count))
```

### Python (requests)

```python
import requests

BASE = "https://towakey.github.io/pokedex/api/v1"

pikachu = requests.get(f"{BASE}/pokedex/0025.json").json()
print(pikachu["name"]["jpn"])  # ピカチュウ

kanto = requests.get(f"{BASE}/pokedex/region/kanto.json").json()
print(kanto["count"])          # 151
```

### curl

```bash
curl https://towakey.github.io/pokedex/api/v1/pokedex/0025.json
curl https://towakey.github.io/pokedex/api/v1/pokedex/region/kanto.json
```

---

## ローカルでの生成

```bash
python3 tools/generate_api.py
# -> dist/ 以下に全ファイルが生成される（dist/api/v1/ が上記エンドポイント）
```

型定義・スキーマは `types/index.d.ts` と `schemas/` を参照してください。
