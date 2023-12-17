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
- 8世代:ソード、シールド、鎧の孤島、冠の雪原、レジェンズアルセウス
- 9世代:スカーレット、バイオレット、碧の仮面、藍の円盤
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
  - ("" or mega_evolution or primal_reversion or region_form or gigantamax)
    - classification
    - height
    - weight
---

### [各ゲームタイトル/各ゲームタイトル.json]
- version
- update
- game_version
- pokedex
  - ローカル図鑑
    - no
    - globalNo
    - status
      - form
      - type1
      - type2
      - hp
      - attack
      - defense
      - special_attack
      - special_defense
      - speed
      - ability1
      - ability2
      - dream_ability
      - description
        - game ver1
        - game ver2
        - etc...
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
---
### ability.json
- version
- update
- ability
  - とくせい名
    - game_version1
    - game_version2
    - etc...
