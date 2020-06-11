## -*- coding: utf-8 -*-
from pokedex.pokedex import Pokedex

pokedex = Pokedex()
pokedex.load(1, 7, False)

print(pokedex.name)
print(pokedex.description['ja']['ultrasun_kanji'])