#!/usr/bin/env python3
"""
静的JSON API生成スクリプト

リポジトリ内のJSONデータを統合し、開発者が手軽に利用できる
静的JSON APIファイル群を dist/api/v1/ 以下に生成する。

使い方:
    python3 tools/generate_api.py

生成されるファイル:
    dist/api/v1/
    ├── pokemon/
    │   ├── index.json          全ポケモン簡易一覧
    │   └── {globalNo}.json     ポケモン個別データ (例: 0001.json)
    ├── moves/
    │   ├── index.json          全バージョンのわざ一覧
    │   └── {version_key}.json  バージョン別わざ詳細
    ├── abilities/
    │   └── index.json          特性一覧
    ├── types/
    │   ├── index.json          タイプ一覧
    │   └── matchup.json        タイプ相性表
    ├── items/
    │   └── index.json          アイテム一覧
    └── meta/
        ├── versions.json       対応バージョン一覧
        └── languages.json      対応言語一覧
"""

import json
import os
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent
DIST_DIR = REPO_ROOT / "dist" / "api" / "v1"

# version_key -> directory name mapping
VERSION_KEY_TO_DIR = {
    "red_green_blue_pikachu": "Red_Green_Blue_Pikachu",
    "gold_silver_crystal": "Gold_Silver_Crystal",
    "ruby_sapphire_emerald": "Ruby_Sapphire_Emerald",
    "firered_leafgreen": "FireRed_LeafGreen",
    "diamond_pearl_platinum": "Diamond_Pearl_Platinum",
    "heartgold_soulsilver": "HeartGold_SoulSilver",
    "black_white": "Black_White",
    "black2_white2": "Black2_White2",
    "x_y": "X_Y",
    "omegaruby_alphasapphire": "OmegaRuby_AlphaSapphire",
    "sun_moon": "Sun_Moon",
    "ultrasun_ultramoon": "UltraSun_UltraMoon",
    "sword_shield": "Sword_Shield",
    "brilliantdiamond_shiningpearl": "BrilliantDiamond_ShiningPearl",
    "legendsarceus": "LegendsArceus",
    "scarlet_violet": "Scarlet_Violet",
    "legendsza": "LegendsZA",
}

GENERATION_MAP = {
    "Red_Green_Blue_Pikachu": 1,
    "Gold_Silver_Crystal": 2,
    "Ruby_Sapphire_Emerald": 3,
    "FireRed_LeafGreen": 3,
    "Diamond_Pearl_Platinum": 4,
    "HeartGold_SoulSilver": 4,
    "Black_White": 5,
    "Black2_White2": 5,
    "X_Y": 6,
    "OmegaRuby_AlphaSapphire": 6,
    "Sun_Moon": 7,
    "UltraSun_UltraMoon": 7,
    "Sword_Shield": 8,
    "BrilliantDiamond_ShiningPearl": 8,
    "LegendsArceus": 8,
    "Scarlet_Violet": 9,
    "LegendsZA": 9,
}

# globalNo ranges per generation (introduced in)
GENERATION_RANGES = [
    (1, 1, 151),
    (2, 152, 251),
    (3, 252, 386),
    (4, 387, 493),
    (5, 494, 649),
    (6, 650, 721),
    (7, 722, 809),
    (8, 810, 905),
    (9, 906, 1025),
]


def get_generation(global_no_int):
    for gen, start, end in GENERATION_RANGES:
        if start <= global_no_int <= end:
            return gen
    return None


def load_json(path):
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def write_json(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, separators=(",", ":"))


def parse_internal_id(internal_id):
    """内部IDからフォルム情報を抽出"""
    parts = internal_id.split("_")
    if len(parts) >= 5:
        return {
            "globalNo": parts[0],
            "formCode": parts[1],
            "regionFlag": parts[2],
            "megaCode": parts[3],
            "gigantamaxFlag": parts[4] if len(parts) > 4 else "0",
        }
    return {"globalNo": parts[0] if parts else internal_id}


def build_pokemon_data():
    """全ポケモンデータを統合して返す"""
    print("Loading global pokedex...")
    global_data = load_json(REPO_ROOT / "pokedex" / "pokedex.json")
    global_pokedex = global_data["pokedex"]

    print("Loading evolve/changeform...")
    ec_data = load_json(REPO_ROOT / "pokedex" / "evolve_changeform.json")
    evolve_map = ec_data.get("evolve", {})
    changeform_map = ec_data.get("changeform", {})

    print("Loading tags...")
    tag_data = load_json(REPO_ROOT / "tag.json")
    tag_map = tag_data.get("tag", {})

    print("Loading ability data...")
    ability_data = load_json(REPO_ROOT / "ability" / "ability.json")
    ability_descs = ability_data.get("ability", {})

    # Load all version data
    print("Loading version data...")
    version_data = {}
    for vk, vdir in VERSION_KEY_TO_DIR.items():
        vpath = REPO_ROOT / "pokedex" / vdir / f"{vdir}.json"
        if vpath.exists():
            vd = load_json(vpath)
            version_data[vk] = vd

    # Build pokemon dict: globalNo -> merged data
    pokemon_all = {}

    for gno_str, forms_dict in global_pokedex.items():
        gno_int = int(gno_str)
        gen = get_generation(gno_int)
        gno_padded = gno_str.zfill(4)

        # Base data from first (normal) form
        normal_form_id = None
        normal_form = None
        all_forms = []

        for form_id, form_data in forms_dict.items():
            parsed = parse_internal_id(form_id)
            is_normal = (
                not form_data.get("form")
                and not form_data.get("region")
                and not form_data.get("mega_evolution")
                and not form_data.get("gigantamax")
            )
            if is_normal and normal_form is None:
                normal_form_id = form_id
                normal_form = form_data

            form_entry = {
                "internalId": form_id,
                "form": form_data.get("form") or "",
                "region": form_data.get("region") or "",
                "megaEvolution": form_data.get("mega_evolution") or "",
                "gigantamax": form_data.get("gigantamax") or "",
                "name": form_data.get("name", {}),
                "classification": form_data.get("classification", {}),
                "height": form_data.get("height"),
                "weight": form_data.get("weight"),
            }
            all_forms.append(form_entry)

        if normal_form is None:
            normal_form = list(forms_dict.values())[0]
            normal_form_id = list(forms_dict.keys())[0]

        # Build version-specific data
        version_entries = {}
        for vk, vd in version_data.items():
            vdir = VERSION_KEY_TO_DIR[vk]
            vpokedex = vd.get("pokedex", {})
            for dex_name, dex_data in vpokedex.items():
                for local_no, entries in dex_data.items():
                    for eid, edata in entries.items():
                        parsed = parse_internal_id(eid)
                        if parsed.get("globalNo") == gno_padded:
                            if vk not in version_entries:
                                version_entries[vk] = {}

                            form_key = edata.get("form") or ""
                            region_key = edata.get("region") or ""
                            mega_key = edata.get("mega_evolution") or ""
                            gmax_key = edata.get("gigantamax") or ""
                            entry_key = f"{form_key}|{region_key}|{mega_key}|{gmax_key}"

                            version_entry = {
                                "localNo": local_no,
                                "pokedex": dex_name,
                                "form": form_key,
                                "region": region_key,
                                "megaEvolution": mega_key,
                                "gigantamax": gmax_key,
                                "type1": edata.get("type1", ""),
                                "type2": edata.get("type2", ""),
                                "stats": {
                                    "hp": edata.get("hp", 0),
                                    "attack": edata.get("attack", 0),
                                    "defense": edata.get("defense", 0),
                                    "spAtk": edata.get("special_attack", 0),
                                    "spDef": edata.get("special_defense", 0),
                                    "speed": edata.get("speed", 0),
                                    "total": sum([
                                        edata.get("hp", 0),
                                        edata.get("attack", 0),
                                        edata.get("defense", 0),
                                        edata.get("special_attack", 0),
                                        edata.get("special_defense", 0),
                                        edata.get("speed", 0),
                                    ]),
                                },
                                "abilities": {
                                    "ability1": edata.get("ability1", ""),
                                    "ability2": edata.get("ability2", ""),
                                    "dreamAbility": edata.get("dream_ability", ""),
                                },
                                "description": edata.get("description", {}),
                            }

                            if "name" in edata:
                                version_entry["name"] = edata["name"]

                            if entry_key not in version_entries[vk]:
                                version_entries[vk][entry_key] = version_entry

        # Build evolution info
        evolution = {"from": [], "to": []}
        gno_short = gno_padded
        # Check evolve_map: key is the evolved pokemon's globalNo
        # evolve_map[gno] = { evolved_form_id: [pre_evolution_form_ids] }
        if gno_padded in evolve_map:
            for evolved_id, pre_ids in evolve_map[gno_padded].items():
                for pre_id in pre_ids:
                    pre_gno = pre_id.split("_")[0]
                    pre_gno_padded = pre_gno.zfill(4)
                    if pre_gno_padded in global_pokedex:
                        pre_forms = global_pokedex[pre_gno_padded]
                        pre_name = ""
                        for fid, fd in pre_forms.items():
                            n = fd.get("name", {})
                            if n.get("jpn"):
                                pre_name = n["jpn"]
                                break
                        evolution["from"].append({
                            "globalNo": pre_gno_padded,
                            "name": pre_name,
                        })

        # Check what this pokemon evolves to
        for target_gno, target_data in evolve_map.items():
            for evolved_id, pre_ids in target_data.items():
                for pre_id in pre_ids:
                    pre_gno = pre_id.split("_")[0]
                    if pre_gno == gno_padded:
                        target_gno_padded = target_gno.zfill(4)
                        if target_gno_padded in global_pokedex:
                            target_forms = global_pokedex[target_gno_padded]
                            target_name = ""
                            for fid, fd in target_forms.items():
                                n = fd.get("name", {})
                                if n.get("jpn"):
                                    target_name = n["jpn"]
                                    break
                            evolution["to"].append({
                                "globalNo": target_gno_padded,
                                "name": target_name,
                            })

        # Deduplicate evolution entries
        seen_from = set()
        unique_from = []
        for e in evolution["from"]:
            key = e["globalNo"]
            if key not in seen_from:
                seen_from.add(key)
                unique_from.append(e)
        evolution["from"] = unique_from

        seen_to = set()
        unique_to = []
        for e in evolution["to"]:
            key = e["globalNo"]
            if key not in seen_to:
                seen_to.add(key)
                unique_to.append(e)
        evolution["to"] = unique_to

        # Get tags
        tags = []
        gno_key = str(gno_int)
        if gno_key in tag_map:
            tags = tag_map[gno_key]

        # Get latest types/stats (prefer latest version with non-empty data)
        latest_types = {"type1": "", "type2": ""}
        latest_stats = None
        latest_abilities = None
        version_priority = list(reversed(list(VERSION_KEY_TO_DIR.keys())))
        for vk in version_priority:
            if vk in version_entries:
                for entry_key, entry in version_entries[vk].items():
                    if not entry.get("form") and not entry.get("region") and not entry.get("megaEvolution") and not entry.get("gigantamax"):
                        if not latest_types["type1"] and entry["type1"]:
                            latest_types = {"type1": entry["type1"], "type2": entry["type2"]}
                        if latest_stats is None and entry["stats"]["total"] > 0:
                            latest_stats = entry["stats"]
                        abilities = entry["abilities"]
                        if latest_abilities is None and (abilities["ability1"] or abilities["dreamAbility"]):
                            latest_abilities = abilities
                        break
                if latest_types["type1"] and latest_stats and latest_abilities:
                    break

        pokemon = {
            "globalNo": gno_str,
            "generation": gen,
            "name": normal_form.get("name", {}),
            "classification": normal_form.get("classification", {}),
            "eggGroups": normal_form.get("egg", []),
            "height": normal_form.get("height"),
            "weight": normal_form.get("weight"),
            "types": latest_types,
            "stats": latest_stats,
            "abilities": latest_abilities,
            "forms": all_forms,
            "evolution": evolution,
            "tags": tags,
            "versionData": {
                vk: list(entries.values())
                for vk, entries in version_entries.items()
            },
        }

        pokemon_all[gno_str] = pokemon

    return pokemon_all


def generate_pokemon_files(pokemon_all):
    """ポケモン個別ファイルとインデックスを生成"""
    pokemon_dir = DIST_DIR / "pokemon"

    # Index: lightweight list
    index_entries = []
    for gno_str in sorted(pokemon_all.keys(), key=lambda x: int(x)):
        p = pokemon_all[gno_str]
        index_entries.append({
            "globalNo": gno_str,
            "generation": p["generation"],
            "name": p["name"],
            "types": p["types"],
            "stats": p["stats"],
        })

    write_json(pokemon_dir / "index.json", {
        "count": len(index_entries),
        "pokemon": index_entries,
    })
    print(f"  pokemon/index.json ({len(index_entries)} entries)")

    # Individual files
    for gno_str, pokemon in pokemon_all.items():
        gno_padded = gno_str.zfill(4)
        write_json(pokemon_dir / f"{gno_padded}.json", pokemon)

    print(f"  pokemon/{{globalNo}}.json ({len(pokemon_all)} files)")


def generate_moves_files():
    """わざデータを生成"""
    moves_dir = DIST_DIR / "moves"

    index_entries = {}

    for vk, vdir in VERSION_KEY_TO_DIR.items():
        waza_list_path = REPO_ROOT / "pokedex" / vdir / "waza_list.json"
        if not waza_list_path.exists():
            continue

        data = load_json(waza_list_path)
        waza_list = data.get("waza_list", {})

        version_moves = {}
        for ver_key, val in waza_list.items():
            if not isinstance(val, dict):
                continue
            # MoveDetail at top level (e.g. Z-moves in Sun_Moon)
            if "type" in val and "category" in val:
                version_moves[ver_key] = val
            else:
                # Nested: version_key -> { move_name: MoveDetail }
                for move_name, detail in val.items():
                    if isinstance(detail, dict):
                        version_moves[move_name] = detail

        if version_moves:
            write_json(moves_dir / f"{vk}.json", {
                "gameVersion": vk,
                "count": len(version_moves),
                "moves": version_moves,
            })
            index_entries[vk] = {
                "gameVersion": vk,
                "directory": vdir,
                "moveCount": len(version_moves),
            }

    write_json(moves_dir / "index.json", {
        "count": len(index_entries),
        "versions": index_entries,
    })
    print(f"  moves/index.json ({len(index_entries)} versions)")
    print(f"  moves/{{version}}.json ({len(index_entries)} files)")


def generate_abilities_file():
    """特性データを生成"""
    abilities_dir = DIST_DIR / "abilities"

    data = load_json(REPO_ROOT / "ability" / "ability.json")
    abilities = data.get("ability", {})

    entries = {}
    for name, descs in abilities.items():
        entries[name] = {
            "name": name,
            "descriptions": descs,
        }

    write_json(abilities_dir / "index.json", {
        "count": len(entries),
        "abilities": entries,
    })
    print(f"  abilities/index.json ({len(entries)} abilities)")


def generate_types_files():
    """タイプデータを生成"""
    types_dir = DIST_DIR / "types"

    # Type list
    list_data = load_json(REPO_ROOT / "type" / "list.json")
    type_list = list_data.get("type", [])

    # Type matchup
    matchup_data = load_json(REPO_ROOT / "type" / "type.json")
    matchups = matchup_data.get("type", [])

    # Translations
    translate_data = load_json(REPO_ROOT / "translate" / "translate.json")
    translations = {}
    for entry in translate_data.get("translate", []):
        jpn = entry.get("jpn", "")
        if jpn:
            translations[jpn] = {k: v for k, v in entry.items() if k != "jpn"}

    type_entries = []
    for t in type_list:
        entry = {"jpn": t}
        if t in translations:
            entry.update(translations[t])
        type_entries.append(entry)

    write_json(types_dir / "index.json", {
        "count": len(type_entries),
        "types": type_entries,
    })

    write_json(types_dir / "matchup.json", {
        "generations": matchups,
    })

    print(f"  types/index.json ({len(type_entries)} types)")
    print("  types/matchup.json")


def generate_items_file():
    """アイテムデータを生成"""
    items_dir = DIST_DIR / "items"

    categories = {
        "item": "道具",
        "item_ball": "ボール",
        "item_battle": "バトル用アイテム",
        "item_important": "大切なもの",
        "item_mail": "メール",
        "item_recovery": "回復アイテム",
    }

    all_items = {}
    for filename, category_name in categories.items():
        path = REPO_ROOT / "item" / f"{filename}.json"
        if path.exists():
            data = load_json(path)
            items = data.get(filename, [])
            all_items[filename] = {
                "category": category_name,
                "count": len(items),
                "items": items,
            }

    write_json(items_dir / "index.json", all_items)
    total = sum(c["count"] for c in all_items.values())
    print(f"  items/index.json ({total} items)")


def generate_meta_files():
    """メタデータを生成"""
    meta_dir = DIST_DIR / "meta"

    # Versions
    config = load_json(REPO_ROOT / "config" / "pokedex_config.json")
    lpm = config.get("local_pokedex_mapping", {})
    regions = config.get("regions", {})

    versions = {}
    for vk, vdir in VERSION_KEY_TO_DIR.items():
        gen = GENERATION_MAP.get(vdir, 0)
        vinfo = lpm.get(vk, {})
        pokedex_names = [p.get("jpn", "") for p in vinfo.get("pokedex", [])]
        ver_list = []
        for ver_name, ver_detail in vinfo.get("version", {}).items():
            ver_list.append({
                "id": ver_name,
                "title": ver_detail.get("title", ""),
                "shortTitle": ver_detail.get("shortTitle", ""),
            })

        versions[vk] = {
            "directory": vdir,
            "generation": gen,
            "pokedexNames": pokedex_names,
            "versions": ver_list,
        }

    region_list = {}
    for rk, rdef in regions.items():
        region_list[rk] = {
            "versionKey": rdef.get("version_key", ""),
            "pokedexIndex": rdef.get("pokedex_index", 0),
            "displayName": rdef.get("display_jpn", ""),
        }

    write_json(meta_dir / "versions.json", {
        "gameVersions": versions,
        "regions": region_list,
    })

    # Languages
    write_json(meta_dir / "languages.json", {
        "languages": [
            {"code": "jpn", "name": "日本語", "nameEng": "Japanese"},
            {"code": "eng", "name": "英語", "nameEng": "English"},
            {"code": "fra", "name": "フランス語", "nameEng": "French"},
            {"code": "ita", "name": "イタリア語", "nameEng": "Italian"},
            {"code": "ger", "name": "ドイツ語", "nameEng": "German"},
            {"code": "spa", "name": "スペイン語", "nameEng": "Spanish"},
            {"code": "kor", "name": "韓国語", "nameEng": "Korean"},
            {"code": "chs", "name": "簡体字中国語", "nameEng": "Simplified Chinese"},
            {"code": "cht", "name": "繁体字中国語", "nameEng": "Traditional Chinese"},
        ],
    })

    print("  meta/versions.json")
    print("  meta/languages.json")


LANG_CODES = ["jpn", "eng", "fra", "ita", "ger", "spa", "kor", "chs", "cht"]


def generate_bundle_files(pokemon_all):
    """用途別統合ファイル (dist/ 直下) を生成"""
    dist_root = REPO_ROOT / "dist"

    # pokemon-basic.json: 名前・タイプ・種族値のみ
    basic_entries = []
    for gno_str in sorted(pokemon_all.keys(), key=lambda x: int(x)):
        p = pokemon_all[gno_str]
        basic_entries.append({
            "globalNo": gno_str,
            "generation": p["generation"],
            "name": p["name"],
            "types": p["types"],
            "stats": p["stats"],
        })
    write_json(dist_root / "pokemon-basic.json", {
        "count": len(basic_entries),
        "pokemon": basic_entries,
    })
    print(f"  pokemon-basic.json ({len(basic_entries)} entries)")

    # pokemon-full.json: 全データ統合版 (versionData含む)
    full_entries = []
    for gno_str in sorted(pokemon_all.keys(), key=lambda x: int(x)):
        full_entries.append(pokemon_all[gno_str])
    write_json(dist_root / "pokemon-full.json", {
        "count": len(full_entries),
        "pokemon": full_entries,
    })
    print(f"  pokemon-full.json ({len(full_entries)} entries)")

    # pokemon-{lang}.json: 言語別フィルタ済み
    for lang in LANG_CODES:
        lang_entries = []
        for gno_str in sorted(pokemon_all.keys(), key=lambda x: int(x)):
            p = pokemon_all[gno_str]
            name = p["name"].get(lang)
            if name is None:
                name = p["name"].get("jpn", "")
            classification = p["classification"].get(lang)
            if classification is None:
                classification = p["classification"].get("jpn", "")
            lang_entries.append({
                "globalNo": gno_str,
                "generation": p["generation"],
                "name": name,
                "classification": classification,
                "types": p["types"],
                "stats": p["stats"],
                "abilities": p["abilities"],
            })
        write_json(dist_root / f"pokemon-{lang}.json", {
            "language": lang,
            "count": len(lang_entries),
            "pokemon": lang_entries,
        })
    print(f"  pokemon-{{lang}}.json ({len(LANG_CODES)} languages)")

    # type-matchup.json: タイプ相性のみ
    matchup_data = load_json(REPO_ROOT / "type" / "type.json")
    write_json(dist_root / "type-matchup.json", matchup_data)
    print("  type-matchup.json")


def main():
    print(f"Generating static JSON API in {DIST_DIR}")
    print()

    pokemon_all = build_pokemon_data()

    print()
    print("Writing files...")
    print()
    print("[Bundle files (dist/)]")
    generate_bundle_files(pokemon_all)

    print()
    print("[API files (dist/api/v1/)]")
    generate_pokemon_files(pokemon_all)
    generate_moves_files()
    generate_abilities_file()
    generate_types_files()
    generate_items_file()
    generate_meta_files()

    # Count total files
    dist_root = REPO_ROOT / "dist"
    total_files = sum(1 for _ in dist_root.rglob("*.json"))
    total_size = sum(f.stat().st_size for f in dist_root.rglob("*.json"))
    print()
    print(f"Done! Generated {total_files} files ({total_size / 1024 / 1024:.1f} MB)")
    print(f"Output: {dist_root}")


if __name__ == "__main__":
    main()
