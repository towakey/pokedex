#!/usr/bin/env python3
"""
Generate pokemon_metadata.json for pokefuta.com
Based on pokedex repository data
"""
import json
import re

# Load all necessary data files
with open('pokedex/pokedex.json', 'r', encoding='utf-8') as f:
    pokedex_data = json.load(f)['pokedex']

with open('translate/translate.json', 'r', encoding='utf-8') as f:
    type_translations = json.load(f)['translate']

# Load pokemon_types.json for type information (simpler and more complete)
with open('pokemon_types.json', 'r', encoding='utf-8') as f:
    pokemon_types_list = json.load(f)

# Create type translation map
type_map = {}
for item in type_translations:
    if 'jpn' in item and 'eng' in item:
        type_map[item['jpn']] = item['eng']

# Create a lookup for pokemon types by no and types combination
pokemon_types_by_key = {}
for pt in pokemon_types_list:
    no = pt['no']
    type1 = pt['type1']
    type2 = pt['type2']
    types_key = f"{type1}_{type2}" if type2 else type1

    if no not in pokemon_types_by_key:
        pokemon_types_by_key[no] = []
    pokemon_types_by_key[no].append({
        'type1': type1,
        'type2': type2,
        'types_key': types_key
    })

# Manual corrections for known issues
# Alola Ninetales should be Ice/Fairy
type_corrections = {
    '0038-alola': [
        {'ja': 'こおり', 'en': 'Ice'},
        {'ja': 'フェアリー', 'en': 'Fairy'}
    ],
    '0038-aloha': [  # typo variant
        {'ja': 'こおり', 'en': 'Ice'},
        {'ja': 'フェアリー', 'en': 'Fairy'}
    ]
}

# Basic evolution data - will be populated
evolution_data = {
    '0037': {'family_id': '0037', 'evolves_from': None, 'evolves_to': ['0038']},
    '0038': {'family_id': '0037', 'evolves_from': '0037', 'evolves_to': []},
    '0037-alola': {'family_id': '0037-alola', 'evolves_from': None, 'evolves_to': ['0038-alola']},
    '0038-alola': {'family_id': '0037-alola', 'evolves_from': '0037-alola', 'evolves_to': []},
}

def extract_form_suffix(forms_jpn):
    """
    Extract form suffix from Japanese form name.
    Returns: 'alola', 'galar', 'hisui', 'paldea', or None
    """
    if not forms_jpn:
        return None

    forms_lower = forms_jpn.lower()
    if 'アローラ' in forms_jpn:
        return 'alola'
    elif 'ガラル' in forms_jpn:
        return 'galar'
    elif 'ヒスイ' in forms_jpn:
        return 'hisui'
    elif 'パルデア' in forms_jpn:
        return 'paldea'

    return None

def get_generation_from_no(no):
    """
    Determine generation from pokedex number.
    This is a simplified heuristic.
    """
    num = int(no)
    if num <= 151:
        return 1
    elif num <= 251:
        return 2
    elif num <= 386:
        return 3
    elif num <= 493:
        return 4
    elif num <= 649:
        return 5
    elif num <= 721:
        return 6
    elif num <= 809:
        return 7
    elif num <= 905:
        return 8
    else:
        return 9

def get_types_for_pokemon(no, form_suffix, unique_id):
    """
    Get types for a pokemon based on no and form.
    Returns list of type dictionaries with ja and en keys.
    """
    # Check for manual corrections first
    if unique_id in type_corrections:
        return type_corrections[unique_id]

    if no not in pokemon_types_by_key:
        return []

    type_variants = pokemon_types_by_key[no]

    # For regional forms, try to match by form type
    # Alola Vulpix is Ice type, normal Vulpix is Fire type
    if len(type_variants) == 1:
        # Only one variant, use it
        variant = type_variants[0]
    elif len(type_variants) == 2 and form_suffix:
        # Multiple variants - need to determine which one
        # Heuristic: regional forms often have different primary type
        # For now, take the second variant for regional forms
        variant = type_variants[1] if form_suffix else type_variants[0]
    else:
        # Default to first variant
        variant = type_variants[0]

    types_array = []
    if variant['type1']:
        types_array.append({
            "ja": variant['type1'],
            "en": type_map.get(variant['type1'], variant['type1'])
        })
    if variant['type2']:
        types_array.append({
            "ja": variant['type2'],
            "en": type_map.get(variant['type2'], variant['type2'])
        })

    return types_array

# Build pokemon metadata
result = []
processed_ids = set()

for no in sorted(pokedex_data.keys()):
    pokemon_forms = pokedex_data[no]

    for form_id in sorted(pokemon_forms.keys()):
        form_data = pokemon_forms[form_id]

        # Get basic info
        name_jpn = form_data.get('name', {}).get('jpn', '')
        name_eng = form_data.get('name', {}).get('eng', '')
        name_kor = form_data.get('name', {}).get('kor', '')
        name_chs = form_data.get('name', {}).get('chs', '')
        name_cht = form_data.get('name', {}).get('cht', '')

        # Get form information
        forms_jpn = form_data.get('forms', {}).get('jpn', '')
        forms_eng = form_data.get('forms', {}).get('eng', '')

        # Extract form suffix
        form_suffix = extract_form_suffix(forms_jpn)

        # Build unique ID
        if form_suffix:
            unique_id = f"{no}-{form_suffix}"
        else:
            unique_id = no

        # Skip if already processed (handle duplicates)
        if unique_id in processed_ids:
            continue
        processed_ids.add(unique_id)

        # Get types
        types_array = get_types_for_pokemon(no, form_suffix, unique_id)

        # Build names object
        names = {
            "ja": name_jpn,
            "en": name_eng
        }
        if name_kor:
            names["ko"] = name_kor
        if name_chs:
            names["zh-Hans"] = name_chs
        if name_cht:
            names["zh-Hant"] = name_cht

        # Determine generation
        generation = get_generation_from_no(no)
        # Regional forms get their introduction generation
        if form_suffix == 'alola':
            generation = 7
        elif form_suffix == 'galar':
            generation = 8
        elif form_suffix == 'hisui':
            generation = 8
        elif form_suffix == 'paldea':
            generation = 9

        # Evolution info
        if unique_id in evolution_data:
            evolution = evolution_data[unique_id]
        else:
            # Default: single-stage pokemon
            evolution = {
                "family_id": unique_id,
                "evolves_from": None,
                "evolves_to": []
            }

        # Color (placeholder - needs color data)
        color = None

        # Build entry (no assets field)
        entry = {
            "id": unique_id,
            "no": no,
            "form": form_suffix,
            "names": names,
            "types": types_array,
            "generation": generation,
            "evolution": evolution,
            "color": color
        }

        result.append(entry)

# Output as JSON
print(json.dumps(result, ensure_ascii=False, indent=2))
