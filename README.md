# Pokedex

ポケモンの原作シリーズのデータをJSONデータにしています。  
どなたでもご自由にお使いください。  
※中の人は公式とは何ら関係ありません、公式への問い合わせ等はやめてください。  

入力ミス、データミス等があればISSUE作成、またはTwitterアカウント(@kyoswin7)まで連絡をお願い致します。  

---
## 世代の定義
- 1世代:赤、緑、青、ピカチュウ
- 2世代:金、銀、クリスタル
- 3世代:ルビー、サファイア、エメラルド、ファイアレッド、リーフグリーン
- 4世代:ダイアモンド、パール、プラチナ、ハートゴールド、ソウルシルバー
- 5世代:ブラック、ホワイト、ブラック2、ホワイト2
- 6世代:X、Y、オメガルビー、アルファサファイア
- 7世代:サン、ムーン、ウルトラサン、ウルトラムーン
- 8世代:ソード、シールド、エキスパンションパス
- 9世代:レジェンズアルセウス

---
## 各ファイルの構成
### pokedex.json
- version
- update
- pokedex
  - id
  - name
    - jpn
    - eng
    - ger
    - fra
    - kor
    - chs
    - cht
  - classification
  - height
  - weight
---

### description.json
- version
- update
- description
  - id
  - ja
    - red
    - green
    - blue
    - pikachu
    - gold
    - silver
    - crystal
    - ruby
    - sapphire
    - firered
    - leafgreen
    - emerald
    - diamond
    - pearl
    - platinum
    - black
    - white
    - black2
    - white2
    - heartgold
    - soulsilver
    - x
    - x_kanji
    - y
    - y_kanji
    - omegaruby
    - omegaruby_kanji
    - alphasapphire
    - alphasapphire_kanji
    - sun
    - sun_kanji
    - moon
    - moon_kanji
    - ultrasun
    - ultrasun_kanji
    - ultramoon
    - ultramoon_kanji
    - letsgopikachu
    - letsgopikachu_kanji
    - letsgoeevee
    - letsgoeevee_kanji
    - sword
    - sword_kanji
    - shield
    - shield_kanji
    - pokemongo
    - pokemonstadium
    - pokemonpinball
    - pokemonranger
---

### gen*_conversion.json
- gen
- name
- conversion
  - [ローカル図鑑]
  - [全国図鑑]
---

### gen*.json
- version
- update
- gen*
  - local_id
  - type1
  - type2
  - hp
  - attack
  - defense
  - special_attack
  - special_defense
  - speed
---

### type.json
- version
- update
- type
  - ノーマル～フェアリー
    - ノーマル
    - ほのお
    - みず
    - でんき
    - くさ
    - こおり
    - かくとう
    - どく
    - じめん
    - ひこう
    - エスパー
    - むし
    - いわ
    - ゴースト
    - ドラゴン
    - あく
    - はがね
    - フェアリー

