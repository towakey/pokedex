## -*- coding: utf-8 -*-
import json

class Pokedex:
    id = 0
    name = ""
    classification = ""
    height = 0
    weight = 0
    description = {}
    description["ja"] = {}

    def __init__(self):
        self.pokedex_url = "./pokedex/pokedex.json"
    
    def exchange_gen(self, no, gen, minor):
        exchange_url = "./pokedex/gen" + str(gen) + "/gen" + str(gen) + "_no.json"
        try:
            with open(exchange_url, encoding='utf-8') as f:
                exchange_json = json.load(f)
            if minor:
                # マイナーチェンジ
                if str(no) in exchange_json[1]:
                    result = exchange_json[1][str(no)]
                else:
                    result = 0
            else:
                if str(no) in exchange_json[1]:
                    result = exchange_json[0][str(no)]
                else:
                    result = 0
        except:
            result = 0
        return int(result)
    
    def getDescription(self, no, gen):
        description_url = "./pokedex/description.json"
        with open(description_url, encoding='utf-8') as f:
            description_json = json.load(f)
        description = []
        if gen == 1:
            description["ja"]["red"] = description_json[no-1]["ja"]["red"]
            description["ja"]["green"] = description_json[no-1]["ja"]["green"]
            description["ja"]["blue"] = description_json[no-1]["ja"]["blue"]
            description["ja"]["pikachu"] = description_json[no-1]["ja"]["pikachu"]
        elif gen ==2:
            description["ja"]["gold"] = description_json[no-1]["ja"]["gold"]
            description["ja"]["silver"] = description_json[no-1]["ja"]["silver"]
            description["ja"]["crystal"] = description_json[no-1]["ja"]["crystal"]
        elif gen ==3:
            description["ja"]["ruby"] = description_json[no-1]["ja"]["ruby"]
            description["ja"]["sapphire"] = description_json[no-1]["ja"]["sapphire"]
            description["ja"]["firered"] = description_json[no-1]["ja"]["firered"]
            description["ja"]["leafgreen"] = description_json[no-1]["ja"]["leafgreen"]
            description["ja"]["emerald"] = description_json[no-1]["ja"]["emerald"]
        elif gen ==4:
            description["ja"]["diamond"] = description_json[no-1]["ja"]["diamond"]
            description["ja"]["pearl"] = description_json[no-1]["ja"]["pearl"]
            description["ja"]["platinum"] = description_json[no-1]["ja"]["platinum"]
        elif gen ==5:
            description["ja"]["black"] = description_json[no-1]["ja"]["black"]
            description["ja"]["white"] = description_json[no-1]["ja"]["white"]
            description["ja"]["black2"] = description_json[no-1]["ja"]["black2"]
            description["ja"]["white2"] = description_json[no-1]["ja"]["white2"]
            description["ja"]["heartgold"] = description_json[no-1]["ja"]["heartgold"]
            description["ja"]["soulsilver"] = description_json[no-1]["ja"]["soulsilver"]
        elif gen ==6:
            self.description["ja"]["x"] = description_json[no-1]["ja"]["x"]
            self.description["ja"]["x_kanji"] = description_json[no-1]["ja"]["x_kanji"]
            self.description["ja"]["y"] = description_json[no-1]["ja"]["y"]
            self.description["ja"]["y_kanji"] = description_json[no-1]["ja"]["y_kanji"]
            self.description["ja"]["omegaruby"] = description_json[no-1]["ja"]["omegaruby"]
            self.description["ja"]["omegaruby_kanji"] = description_json[no-1]["ja"]["omegaruby_kanji"]
            self.description["ja"]["alphasapphire"] = description_json[no-1]["ja"]["alphasapphire"]
            self.description["ja"]["alphasapphire_kanji"] = description_json[no-1]["ja"]["alphasapphire_kanji"]
        elif gen ==7:
            self.description["ja"]["sun"] = description_json[no-1]["ja"]["sun"]
            self.description["ja"]["sun_kanji"] = description_json[no-1]["ja"]["sun_kanji"]
            self.description["ja"]["moon"] = description_json[no-1]["ja"]["moon"]
            self.description["ja"]["moon_kanji"] = description_json[no-1]["ja"]["moon_kanji"]
            self.description["ja"]["ultrasun"] = description_json[no-1]["ja"]["ultrasun"]
            self.description["ja"]["ultrasun_kanji"] = description_json[no-1]["ja"]["ultrasun_kanji"]
            self.description["ja"]["ultramoon"] = description_json[no-1]["ja"]["ultramoon"]
            self.description["ja"]["ultramoon_kanji"] = description_json[no-1]["ja"]["ultramoon_kanji"]
        elif gen ==8:
            pass


    # 読み込むポケモンの全国図鑑Noと世代を指定
    def load(self, no, gen, minor):
        # 世代指定が0の場合は全国図鑑を探す
        if gen > 0:
            no = self.exchange_gen(no, gen, minor)
        # pokedex.jsonからの情報をロード
        if no > 0:
            with open(self.pokedex_url,encoding='utf-8') as f:
                pokedex_json = json.load(f)
            self.id = pokedex_json[no - 1]['id']
            self.name = pokedex_json[no - 1]['name']
            self.classification = pokedex_json[no - 1]['classification']
            self.height = pokedex_json[no - 1]['height']
            self.weight = pokedex_json[no - 1]['weight']

            # 世代を指定しない場合は呼び出さない
            if gen > 0:
                self.getDescription(no, gen)
                gen_url = "./pokedex/gen" + str(gen) + "/gen" + str(gen) + ".json"
                # with open(gen_url,encoding='utf-8') as f:
                #     gen_json = json.load(f)
