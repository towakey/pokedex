# TypeScript 型定義

本リポジトリの各JSONファイルに対応する TypeScript 型定義です。

## ファイル

| ファイル | 説明 |
|---------|------|
| `index.d.ts` | 全型定義（単一ファイル） |

## 使い方

### プロジェクトへの組み込み

型定義ファイルをプロジェクトにコピーするか、`tsconfig.json` の `typeRoots` / `paths` で参照してください。

```typescript
import type {
  PokedexFile,
  VersionPokedexFile,
  WazaListFile,
  MoveDetail,
  PokemonGlobalForm,
  VersionPokemonForm,
  LocalizedText,
} from '@towakey/pokedex/types';
```

### 使用例

#### 全国図鑑データの読み込み

```typescript
import type { PokedexFile } from './types';
import fs from 'fs';

const data: PokedexFile = JSON.parse(
  fs.readFileSync('pokedex/pokedex.json', 'utf-8')
);

// フシギダネの全フォルムを取得
const bulbasaur = data.pokedex['0001'];
for (const [formId, form] of Object.entries(bulbasaur)) {
  console.log(`${form.name.jpn} (${form.name.eng})`);
  console.log(`  分類: ${form.classification.jpn}`);
  console.log(`  たまごグループ: ${form.egg.join(', ')}`);
  console.log(`  高さ: ${form.height}m / 重さ: ${form.weight}kg`);
}
```

#### バージョン別データの読み込み

```typescript
import type { VersionPokedexFile } from './types';

const sv: VersionPokedexFile = JSON.parse(
  fs.readFileSync('pokedex/Scarlet_Violet/Scarlet_Violet.json', 'utf-8')
);

// パルデア図鑑の各ポケモンを処理
for (const [dexName, regionalDex] of Object.entries(sv.pokedex)) {
  for (const [no, pokemonEntry] of Object.entries(regionalDex)) {
    for (const [formId, form] of Object.entries(pokemonEntry)) {
      console.log(`No.${no}: ${form.type1}${form.type2 ? '/' + form.type2 : ''}`);
      console.log(`  HP:${form.hp} 攻撃:${form.attack} 防御:${form.defense}`);
      console.log(`  特攻:${form.special_attack} 特防:${form.special_defense} 素早:${form.speed}`);
      console.log(`  特性: ${form.ability1}${form.ability2 ? ' / ' + form.ability2 : ''}`);
    }
  }
}
```

#### わざデータの利用

```typescript
import type { WazaListFile, MoveDetail } from './types';

const wazaList: WazaListFile = JSON.parse(
  fs.readFileSync('pokedex/Scarlet_Violet/waza_list.json', 'utf-8')
);

// 特定バージョンの全わざを処理
for (const [version, moves] of Object.entries(wazaList.waza_list)) {
  for (const [moveName, detail] of Object.entries(moves)) {
    console.log(`${moveName}: ${detail.type} ${detail.category} 威力${detail.power}`);
  }
}
```

## 型の一覧

### データファイル型 (トップレベル)

| 型名 | 対応ファイル |
|------|-------------|
| `PokedexFile` | `pokedex/pokedex.json` |
| `VersionPokedexFile` | `pokedex/{Version}/{Version}.json` |
| `WazaFile` | `pokedex/{Version}/waza.json` |
| `WazaListFile` | `pokedex/{Version}/waza_list.json` |
| `WazaMachineFile` | `pokedex/{Version}/waza_machine.json` |
| `EvolveFile` | `pokedex/{Version}/evolve.json` |
| `EvolveChangeformFile` | `pokedex/evolve_changeform.json` |
| `AbilityFile` | `ability/ability.json` |
| `TypeFile` | `type/type.json` |
| `TypeListFile` | `type/list.json` |
| `ItemFile` | `item/*.json` |
| `TagFile` | `tag.json` |
| `TranslateFile` | `translate/translate.json` |
| `TranslateWazaFile` | `translate/waza.json` |
| `PokedexConfigFile` | `config/pokedex_config.json` |
| `LocalGlobalIdConverterFile` | `convert/local_global_id_converter.json` |

### 主要なデータ型

| 型名 | 説明 |
|------|------|
| `LocalizedText` | 多言語テキスト (`jpn`, `eng`, ...) |
| `PokemonGlobalForm` | 全国図鑑のポケモン基本情報 |
| `VersionPokemonForm` | バージョン別のポケモンデータ（種族値・特性含む） |
| `MoveDetail` | わざの詳細情報 |
| `TypeMatchupEntry` | タイプ相性データ |
| `TagEntry` | タグ情報 |
| `EvolutionEntry` | 進化情報 |

### ユーティリティ型

| 型名 | 説明 |
|------|------|
| `LanguageCode` | 言語コードのユニオン型 |
| `GameVersionDir` | ゲームバージョンディレクトリ名のユニオン型 |
| `GameVersionKey` | ゲームバージョンキー (スネークケース) のユニオン型 |
| `InternalId` | 内部ID (文字列エイリアス) |
