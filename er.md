```mermaid
erDiagram
    POKEDEX {
        string globalNo PK
        string form
        string region
        string mega_evolution
        string gigantamax
        string height
        string weight
    }
    POKEDEX_NAME {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string language
        string name
    }
    POKEDEX_CLASSIFICATION {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string language
        string classification
    }
    LOCAL_POKEDEX {
        string no PK
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        string pokedex
    }
    LOCAL_POKEDEX_TYPE {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        string type1
        string type2
    }
    LOCAL_POKEDEX_ABILITY {
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
    LOCAL_POKEDEX_STATUS {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        int hp
        int attack
        int defense
        int special_attack
        int special_defense
        int speed
    }
    LOCAL_POKEDEX_DESCRIPTION {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        string language
        string description
    }
    LOCAL_POKEDEX_WAZA {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        string pokedex
        string conditions
        string waza
    }
    LOCAL_POKEDEX_WAZA_MACHINE {
        string globalNo FK
        string form
        string region
        string mega_evolution
        string gigantamax
        string version
        string pokedex
        string machine_no
    }
    LOCAL_WAZA {
        string waza PK
        string version
        string type
        string category
        string pp
        string power
        string accuracy
    }
    LOCAL_WAZA_LANGUAGE {
        string waza FK
        string version
        string language
        string name
        string description
    }
    LOCAL_WAZA_MACHINE {
        string waza FK
        string version
        string machine_no
    }
    TYPE_LANGUAGE {
        string type FK
        string language
        string name
    }
    ABILITY_LANGUAGE {
        string ability FK
        string version
        string language
        string name
        string description
    }

    LOCAL_POKEDEX ||--o{ POKEDEX : "matched on globalNo, form, region, mega_evolution, gigantamax"
    POKEDEX ||--o{ POKEDEX_CLASSIFICATION : "matched on globalNo, form, region, mega_evolution, gigantamax"
    POKEDEX ||--o{ POKEDEX_NAME : "matched on globalNo, form, region, mega_evolution, gigantamax"

    LOCAL_POKEDEX ||--o{ LOCAL_POKEDEX_TYPE : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX ||--o{ LOCAL_POKEDEX_ABILITY : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX ||--o{ LOCAL_POKEDEX_STATUS : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX ||--o{ LOCAL_POKEDEX_DESCRIPTION : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX ||--o{ LOCAL_POKEDEX_WAZA : "matched on globalNo, form, region, mega_evolution, gigantamax"
    LOCAL_POKEDEX ||--o{ LOCAL_POKEDEX_WAZA_MACHINE : "matched on globalNo, form, region, mega_evolution, gigantamax"

    LOCAL_POKEDEX_ABILITY ||--o{ ABILITY_LANGUAGE : "matched on ability, version, language"
    LOCAL_POKEDEX_TYPE ||--o{ TYPE_LANGUAGE : "matched on type, language"
    LOCAL_POKEDEX_WAZA ||--o{ LOCAL_WAZA : "matched on waza, version"
    LOCAL_WAZA ||--o{ LOCAL_WAZA_LANGUAGE : "matched on waza, version, language"
    LOCAL_POKEDEX_WAZA_MACHINE ||--o{ LOCAL_WAZA_MACHINE : "matched on version, machine_no"
    LOCAL_WAZA_MACHINE ||--o{ LOCAL_WAZA : "matched on waza, version"