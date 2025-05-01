```mermaid
erDiagram
    LOCAL_POKEDEX_JSON {
        string no PK
        string globalNo PK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        string pokedex
    }
    POKEDEX_JSON {
        string globalNo PK
        string form
        string region
        string mega_evolution
        string gigantamax
        string height
        string weight
    }
    POKEDEX_CLASSIFICATION_JSON {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string language
        string classification
    }
    LOCAL_POKEDEX_NAME_JSON {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string language
        string name
    }
    LOCAL_POKEDEX_DESCRIPTION_JSON {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string language
        string version
        string description
    }
    LOCAL_POKEDEX_TYPE_JSON {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        string type1
        string type2
    }
    LOCAL_POKEDEX_ABILITY_JSON {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        string ability1
        string ability2
        string dream_ability
    }
    LOCAL_POKEDEX_STATUS_JSON {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        string hp
        string attack
        string defense
        string special_attack
        string special_defense
        string speed
    }
    LOCAL_POKEDEX_WAZA_JSON {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        string conditions
        string waza
    }
    LOCAL_POKEDEX_WAZA_MACHINE_JSON {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        string machine_no
    }

    LOCAL_WAZA_JSON {
        string waza FK
        string version
        string type
        string category
        string pp
        string power
        string accuracy
        string priority
    }
    LOCAL_WAZA_LANGUAGE_JSON {
        string waza FK
        string language
        string name
        string description
    }
    LOCAL_WAZA_MACHINE_JSON {
        string version
        string machine_no
        string waza
    }

    TYPE_LANGUAGE_JSON {
        string type FK
        string language
        string name
    }

    ABILITY_LANGUAGE_JSON {
        string ability FK
        string language
        string name
        string description
    }

    LOCAL_POKEDEX_JSON ||--o{ POKEDEX_JSON : "matched on no, form, region, mega_evolution, gigantamax"
    POKEDEX_JSON ||--o{ POKEDEX_CLASSIFICATION_JSON : "matched on no"
    LOCAL_POKEDEX_JSON ||--o{ LOCAL_POKEDEX_TYPE_JSON : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX_JSON ||--o{ LOCAL_POKEDEX_ABILITY_JSON : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX_JSON ||--o{ LOCAL_POKEDEX_STATUS_JSON : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX_JSON ||--o{ LOCAL_POKEDEX_DESCRIPTION_JSON : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX_JSON ||--o{ LOCAL_POKEDEX_NAME_JSON : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX_JSON ||--o{ LOCAL_POKEDEX_WAZA_JSON : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX_JSON ||--o{ LOCAL_POKEDEX_WAZA_MACHINE_JSON : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX_ABILITY_JSON ||--o{ ABILITY_LANGUAGE_JSON : "matched on language, name"
    LOCAL_POKEDEX_TYPE_JSON ||--o{ TYPE_LANGUAGE_JSON : "matched on language"
    LOCAL_POKEDEX_WAZA_JSON ||--o{ LOCAL_WAZA_JSON : "matched on waza, version"
    LOCAL_WAZA_JSON ||--o{ LOCAL_WAZA_LANGUAGE_JSON : "matched on language, name"
    LOCAL_POKEDEX_WAZA_MACHINE_JSON ||--o{ LOCAL_WAZA_MACHINE_JSON : "matched on version, machine_no"
    LOCAL_WAZA_MACHINE_JSON ||--o{ LOCAL_WAZA_JSON : "matched on waza, version"