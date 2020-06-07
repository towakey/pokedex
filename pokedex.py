## -*- coding: utf-8 -*-
import json

class Pokedex:
    id = 0
    name = ""
    classification = ""
    height = 0
    weight = 0
    description = ""

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

    # 読み込むポケモンの全国図鑑Noと世代を指定
    # def load(self, no, gen):
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

            gen_url = "./pokedex/gen" + str(gen) + "/gen" + str(gen) + ".json"
            with open(gen_url,encoding='utf-8') as f:
                gen_json = json.load(f)

pokedex = Pokedex()
pokedex.load(1000, 7, False)

print(pokedex.name)