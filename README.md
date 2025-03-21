# pokedex.json

ポケモンの原作シリーズのデータをJSONデータにしています。  
どなたでもご自由にお使いください。  
※公式とは何ら関係ありません、公式への問い合わせ等はやめてください。  

入力ミス、データミス等があればISSUE作成、またはTwitterアカウント(@kyoswin7)まで連絡をお願い致します。  

--- 
## pokedex.dbの作成
import_db.rbを実行することでSQLite3のpokedex.dbが作成されます。

---
## API
### パラメーター

| パラメーター | 値 | 説明 |
| --- | --- | --- |
| region | string | 地方図鑑名 |
| no | string | 図鑑NO (詳細モード時必須) |
| mode | string | モード (index / details) |



### 全国図鑑一覧取得
http://localhost/pokedex/pokedex.php?region=global&mode=index
```
{
    "status": "success",
    "data": [
        {
            "no": "1",
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
            "classification": "たねポケモン",
            "height": "0.7",
            "weight": "6.9"
        },
        {
            "no": "2",
            "form": "",
            "region": "",
            "mega_evolution": "",
            "gigantamax": "",
            "jpn": "フシギソウ",
            "eng": "Ivysaur",
            "ger": "Bisaknosp",
            "fra": "Herbizarre",
            "kor": "이상해풀",
            "chs": "妙蛙草",
            "cht": "妙蛙草",
            "classification": "たねポケモン",
            "height": "1.0",
            "weight": "13.0"
        },
        {
            "no": "3",
            "form": "",
            "region": "",
            "mega_evolution": "",
            "gigantamax": "",
            "jpn": "フシギバナ",
            "eng": "Venusaur",
            "ger": "Bisaflor",
            "fra": "Florizarre",
            "kor": "이상해꽃",
            "chs": "妙蛙花",
            "cht": "妙蛙花",
            "classification": "たねポケモン",
            "height": "2.0",
            "weight": "100.0"
        },
        {
            "no": "3",
            "form": "",
            "region": "",
            "mega_evolution": "メガフシギバナ",
            "gigantamax": "",
            "jpn": "メガフシギバナ",
            "eng": "MegaVenusaur",
            "ger": "",
            "fra": "",
            "kor": "",
            "chs": "",
            "cht": "",
            "classification": "たねポケモン",
            "height": "2.4",
            "weight": "155.5"
        },
        {
            "no": "3",
            "form": "",
            "region": "",
            "mega_evolution": "",
            "gigantamax": "キョダイマックスフシギバナ",
            "jpn": "キョダイマックスフシギバナ",
            "eng": "GigantamaxVenusaur",
            "ger": "",
            "fra": "",
            "kor": "",
            "chs": "",
            "cht": "",
            "classification": "たねポケモン",
            "height": "24.0",
            "weight": "155.5"
        },
        ...
    ]
}
```

### 全国図鑑詳細取得
http://localhost/pokedex/pokedex.php?region=global&mode=details&no=3
```
{
    "status": "success",
    "data": [
        {
            "no": "3",
            "form": "",
            "region": "",
            "mega_evolution": "",
            "gigantamax": "",
            "jpn": "フシギバナ",
            "eng": "Venusaur",
            "ger": "Bisaflor",
            "fra": "Florizarre",
            "kor": "이상해꽃",
            "chs": "妙蛙花",
            "cht": "妙蛙花",
            "classification": "たねポケモン",
            "height": "2.0",
            "weight": "100.0"
        },
        {
            "no": "3",
            "form": "",
            "region": "",
            "mega_evolution": "メガフシギバナ",
            "gigantamax": "",
            "jpn": "メガフシギバナ",
            "eng": "MegaVenusaur",
            "ger": "",
            "fra": "",
            "kor": "",
            "chs": "",
            "cht": "",
            "classification": "たねポケモン",
            "height": "2.4",
            "weight": "155.5"
        },
        {
            "no": "3",
            "form": "",
            "region": "",
            "mega_evolution": "",
            "gigantamax": "キョダイマックスフシギバナ",
            "jpn": "キョダイマックスフシギバナ",
            "eng": "GigantamaxVenusaur",
            "ger": "",
            "fra": "",
            "kor": "",
            "chs": "",
            "cht": "",
            "classification": "たねポケモン",
            "height": "24.0",
            "weight": "155.5"
        }
    ],
    "message": "This API is the Pokédex API."
}
```

### 各地方図鑑取得
http://localhost/pokedex/pokedex.php?region=kanto&mode=index
```
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
        ...
    ]
}

```

### 各地方図鑑詳細取得
http://localhost/pokedex/pokedex.php?mode=detail&region=central_kalos&no=85
```
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
        {
            "no": "85",
            "globalNo": "6",
            "form": "",
            "region": "",
            "mega_evolution": "メガリザードンX",
            "gigantamax": "",
            "type1": "ほのお",
            "type2": "ドラゴン",
            "hp": 78,
            "attack": 130,
            "defense": 111,
            "special_attack": 130,
            "special_defense": 85,
            "speed": 100,
            "ability1": "かたいツメ",
            "ability2": "",
            "dream_ability": "",
            "x": "",
            "y": "",
            "jpn": "メガリザードンX",
            "eng": "MegaCharizardX",
            "ger": "",
            "fra": "",
            "kor": "",
            "chs": "",
            "cht": "",
            "classification": "かえんポケモン",
            "height": "1.7",
            "weight": "110.5",
            "ability1_description": "せっしょくする わざの いりょくが あがる。",
            "ability2_description": "",
            "dreame_ability_description": "",
            "waza_list": {
                "": [],
                "思い出し": [],
                "進化時": [],
                "lvup": [],
                "わざマシン": []
            }
        },
        {
            "no": "85",
            "globalNo": "6",
            "form": "",
            "region": "",
            "mega_evolution": "メガリザードンY",
            "gigantamax": "",
            "type1": "ほのお",
            "type2": "ひこう",
            "hp": 78,
            "attack": 104,
            "defense": 78,
            "special_attack": 159,
            "special_defense": 115,
            "speed": 100,
            "ability1": "ひでり",
            "ability2": "",
            "dream_ability": "",
            "x": "",
            "y": "",
            "jpn": "メガリザードンY",
            "eng": "MegaCharizardY",
            "ger": "",
            "fra": "",
            "kor": "",
            "chs": "",
            "cht": "",
            "classification": "かえんポケモン",
            "height": "1.7",
            "weight": "90.5",
            "ability1_description": "せんとうに でると にほんばれに なる。",
            "ability2_description": "",
            "dreame_ability_description": "",
            "waza_list": {
                "": [],
                "思い出し": [],
                "進化時": [],
                "lvup": [],
                "わざマシン": []
            }
        }
    ],
    "message": "This API is the Pokédex API.",
    "region_name": "セントラルカロス図鑑"
}```

---
## ライセンス

このプロジェクトはMITライセンスの下で公開されています。詳細は[LICENSE](LICENSE)ファイルをご覧ください。  
MIT License  
Copyright (c) 2025 kyoswin7  
