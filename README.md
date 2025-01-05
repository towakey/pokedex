# pokedex.json

ポケモンの原作シリーズのデータをJSONデータにしています。  
どなたでもご自由にお使いください。  
※公式とは何ら関係ありません、公式への問い合わせ等はやめてください。  

入力ミス、データミス等があればISSUE作成、またはTwitterアカウント(@kyoswin7)まで連絡をお願い致します。  

--- 
## pokedex.dbの作成
import_db.rbを実行することでSQLite3のpokedex.dbが作成されます。

---
## webAPI
pokedex.dbを使用したwebAPIを作成することが可能です。

### 全国図鑑取得
```
http://localhost/pokedex/global

{
    "status": "success",
    "data": [
        {
            "id": "1",
            "form": "",
            "region": "",
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
        ...
    ]
}
```
### 各地方図鑑取得
```
http://localhost/pokedex/kanto
http://localhost/pokedex/johto
http://localhost/pokedex/hoenn
http://localhost/pokedex/sinnoh
http://localhost/pokedex/unova_bw
http://localhost/pokedex/unova_b2w2
http://localhost/pokedex/central_kalos
http://localhost/pokedex/coast_kalos
http://localhost/pokedex/mountain_kalos
http://localhost/pokedex/alola_sm
http://localhost/pokedex/alola_usum
http://localhost/pokedex/galar
http://localhost/pokedex/crown_tundra
http://localhost/pokedex/isle_of_armor
http://localhost/pokedex/hisui
http://localhost/pokedex/paldea
http://localhost/pokedex/kitakami
http://localhost/pokedex/blueberry

{
    "status": "success",
    "data": [
        {
            "id": "1",
            "globalNo": "1",
            "form": "",
            "type1": "くさ",
            "type2": "どく",
            "hp": 45,
            "attack": 49,
            "defense": 49,
            "special_attack": 65,
            "special_defense": 65,
            "speed": 45,
            "ability1": "",
            "ability2": "",
            "dream_ability": "",
            "red": " うまれたときから せなかに しょくぶつの タネが あって すこしずつ おおきく そだつ。",
            "green": " うまれたときから せなかに しょくぶつの タネが あって すこしずつ おおきく そだつ。",
            "blue": " うまれたときから せなかに ふしぎな タネが うえてあって からだと ともに そだつという。",
            "pikachu": " なんにちだって なにも たべなくても げんき! せなかのタネに たくさん えいようが あるから へいきだ!",
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
        ...
    ]
}

```

### 各地方図鑑取得(個別)
```
http://localhost/pokedex/kanto/1

{
    "status": "success",
    "data": [
        {
            "id": "1",
            "globalNo": "1",
            "form": "",
            "type1": "くさ",
            "type2": "どく",
            "hp": 45,
            "attack": 49,
            "defense": 49,
            "special_attack": 65,
            "special_defense": 65,
            "speed": 45,
            "ability1": "",
            "ability2": "",
            "dream_ability": "",
            "red": " うまれたときから せなかに しょくぶつの タネが あって すこしずつ おおきく そだつ。",
            "green": " うまれたときから せなかに しょくぶつの タネが あって すこしずつ おおきく そだつ。",
            "blue": " うまれたときから せなかに ふしぎな タネが うえてあって からだと ともに そだつという。",
            "pikachu": " なんにちだって なにも たべなくても げんき! せなかのタネに たくさん えいようが あるから へいきだ!",
            "jpn": "フシギダネ",
            "eng": "Bulbasaur",
            "ger": "Bisasam",
            "fra": "Bulbizarre",
            "kor": "이상해씨",
            "chs": "妙蛙种子",
            "cht": "妙蛙種子",
            "classification": "たねポケモン",
            "height": "0.7",
            "weight": "6.9",
            "waza_list": {
                "initial": null,
                "remember": null,
                "evolution": null,
                "level": null,
                "machine": null
            }
        }
    ],
    "message": "",
    "region_name": "カントー図鑑"
}
```

---
## ライセンス

このプロジェクトはMITライセンスの下で公開されています。詳細は[LICENSE](LICENSE)ファイルをご覧ください。  
MIT License  
Copyright (c) 2025 kyoswin7  
