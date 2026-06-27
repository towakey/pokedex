# JSON Schema

本リポジトリの各JSONファイルに対応する [JSON Schema (Draft-07)](https://json-schema.org/) 定義です。

## スキーマ一覧

| スキーマファイル | 対象データ | 説明 |
|-----------------|-----------|------|
| `pokedex.schema.json` | `pokedex/pokedex.json` | 全国図鑑（基本情報・名前・分類・たまごグループ） |
| `version_pokedex.schema.json` | `pokedex/{Version}/{Version}.json` | バージョン別図鑑（種族値・タイプ・特性・図鑑説明） |
| `waza.schema.json` | `pokedex/{Version}/waza.json` | ポケモンごとの習得わざ |
| `waza_list.schema.json` | `pokedex/{Version}/waza_list.json` | わざ詳細（タイプ・威力・PP等） |
| `waza_machine.schema.json` | `pokedex/{Version}/waza_machine.json` | わざマシン番号とわざ名の対応 |
| `evolve.schema.json` | `pokedex/{Version}/evolve.json` | バージョン別進化データ |
| `evolve_changeform.schema.json` | `pokedex/evolve_changeform.json` | 進化・フォルムチェンジ（内部IDベース） |
| `ability.schema.json` | `ability/ability.json` | 特性説明（バージョン別） |
| `type.schema.json` | `type/type.json` | タイプ相性表（世代別） |
| `type_list.schema.json` | `type/list.json` | タイプ名一覧 |
| `item.schema.json` | `item/*.json` | アイテム一覧 |
| `tag.schema.json` | `tag.json` | タグ情報 |
| `translate.schema.json` | `translate/translate.json` | タイプ名等の多言語翻訳 |
| `translate_waza.schema.json` | `translate/waza.json` | わざ名の多言語翻訳 |
| `config.schema.json` | `config/pokedex_config.json` | 設定（地方図鑑マッピング等） |
| `local_global_id_converter.schema.json` | `convert/local_global_id_converter.json` | ローカル⇔グローバルID変換 |

## 使い方

### バリデーション (CLI)

[ajv-cli](https://github.com/ajv-validator/ajv-cli) を使ってJSONデータを検証できます:

```bash
npx ajv validate -s schemas/pokedex.schema.json -d pokedex/pokedex.json
npx ajv validate -s schemas/version_pokedex.schema.json -d pokedex/Scarlet_Violet/Scarlet_Violet.json
```

### エディタ連携

VSCode 等のエディタでJSONファイルを開く際、`$schema` プロパティを使ってスキーマを指定できます:

```json
{
  "$schema": "../schemas/pokedex.schema.json",
  "update": "20260101",
  "pokedex": { ... }
}
```

### プログラムからの利用

```typescript
import Ajv from 'ajv';
import pokedexSchema from '../schemas/pokedex.schema.json';

const ajv = new Ajv();
const validate = ajv.compile(pokedexSchema);

const data = JSON.parse(fs.readFileSync('pokedex/pokedex.json', 'utf-8'));
if (validate(data)) {
  console.log('Valid!');
} else {
  console.error(validate.errors);
}
```
