## -*- coding: utf-8 -*-
from pokedex.pokedex import Pokedex

pokedex = Pokedex()
pokedex.load(249, 0, False)

print(pokedex.name)