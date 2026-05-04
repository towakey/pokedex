import { useMemo, useCallback } from "react";
import { AgGridReact } from "ag-grid-react";
import {
  ModuleRegistry,
  ClientSideRowModelModule,
  CommunityFeaturesModule,
} from "ag-grid-community";
import type { CellValueChangedEvent, ColDef } from "ag-grid-community";
import "ag-grid-community/styles/ag-grid.css";
import "ag-grid-community/styles/ag-theme-alpine.css";

ModuleRegistry.registerModules([
  ClientSideRowModelModule,
  CommunityFeaturesModule,
]);

interface RegionalEntry {
  form: string;
  region: string;
  mega_evolution: string;
  gigantamax: string;
  type1: string;
  type2: string;
  hp: number;
  attack: number;
  defense: number;
  special_attack: number;
  special_defense: number;
  speed: number;
  ability1: string;
  ability2: string;
  dream_ability: string;
  description: Record<string, string>;
}

interface ParsedRegionalData {
  update: string;
  game_version: string;
  pokedex: Record<string, Record<string, Record<string, RegionalEntry>>>;
}

interface RegionalGridRow {
  no: string;
  variantKey: string;
  pokemonName: string;
  globalNo: string;
  form: string;
  region: string;
  megaEvolution: string;
  gigantamax: string;
  type1: string;
  type2: string;
  hp: number;
  attack: number;
  defense: number;
  specialAttack: number;
  specialDefense: number;
  speed: number;
  ability1: string;
  ability2: string;
  dreamAbility: string;
  descriptions: Record<string, string>;
}

interface Props {
  data: ParsedRegionalData;
  regionName: string;
  pokemonNames: Record<string, string>;
  onDataChange: (updater: (prev: ParsedRegionalData) => ParsedRegionalData) => void;
}

function extractGlobalNo(variantKey: string): string {
  const parts = variantKey.split("_");
  return parts[0] || "";
}

export function RegionalGrid({ data, regionName, pokemonNames, onDataChange }: Props) {
  const { rows, descriptionKeys } = useMemo(() => {
    const result: RegionalGridRow[] = [];
    const descKeys = new Set<string>();
    const regionData = data.pokedex[regionName];
    if (!regionData) return { rows: result, descriptionKeys: [] as string[] };

    for (const no of Object.keys(regionData).sort()) {
      const variants = regionData[no];
      for (const [variantKey, entry] of Object.entries(variants)) {
        const globalNo = extractGlobalNo(variantKey);
        const paddedGlobalNo = globalNo.padStart(4, "0");
        const name = pokemonNames[paddedGlobalNo] || pokemonNames[globalNo] || "";

        if (entry.description) {
          Object.keys(entry.description).forEach((k) => descKeys.add(k));
        }

        result.push({
          no,
          variantKey,
          pokemonName: name,
          globalNo,
          form: entry.form ?? "",
          region: entry.region ?? "",
          megaEvolution: entry.mega_evolution ?? "",
          gigantamax: entry.gigantamax ?? "",
          type1: entry.type1 ?? "",
          type2: entry.type2 ?? "",
          hp: entry.hp ?? 0,
          attack: entry.attack ?? 0,
          defense: entry.defense ?? 0,
          specialAttack: entry.special_attack ?? 0,
          specialDefense: entry.special_defense ?? 0,
          speed: entry.speed ?? 0,
          ability1: entry.ability1 ?? "",
          ability2: entry.ability2 ?? "",
          dreamAbility: entry.dream_ability ?? "",
          descriptions: entry.description ?? {},
        });
      }
    }
    return { rows: result, descriptionKeys: Array.from(descKeys).sort() };
  }, [data, regionName, pokemonNames]);

  const columnDefs = useMemo<ColDef<RegionalGridRow>[]>(() => {
    const cols: ColDef<RegionalGridRow>[] = [
      { field: "no", headerName: "No.", width: 70, editable: false, pinned: "left" },
      { field: "pokemonName", headerName: "ポケモン名", width: 120, editable: false, pinned: "left" },
      { field: "variantKey", headerName: "バリアントキー", width: 200, editable: false },
      { field: "globalNo", headerName: "全国No", width: 80, editable: false },
      { field: "form", headerName: "フォーム", width: 100, editable: true },
      { field: "region", headerName: "リージョン", width: 100, editable: true },
      { field: "megaEvolution", headerName: "メガ進化", width: 100, editable: true },
      { field: "gigantamax", headerName: "キョダイ", width: 100, editable: true },
      { field: "type1", headerName: "タイプ1", width: 90, editable: true },
      { field: "type2", headerName: "タイプ2", width: 90, editable: true },
      { field: "hp", headerName: "HP", width: 70, editable: true, cellEditor: "agNumberCellEditor" },
      { field: "attack", headerName: "攻撃", width: 70, editable: true, cellEditor: "agNumberCellEditor" },
      { field: "defense", headerName: "防御", width: 70, editable: true, cellEditor: "agNumberCellEditor" },
      { field: "specialAttack", headerName: "特攻", width: 70, editable: true, cellEditor: "agNumberCellEditor" },
      { field: "specialDefense", headerName: "特防", width: 70, editable: true, cellEditor: "agNumberCellEditor" },
      { field: "speed", headerName: "素早", width: 70, editable: true, cellEditor: "agNumberCellEditor" },
      { field: "ability1", headerName: "特性1", width: 120, editable: true },
      { field: "ability2", headerName: "特性2", width: 120, editable: true },
      { field: "dreamAbility", headerName: "夢特性", width: 120, editable: true },
    ];

    for (const key of descriptionKeys) {
      cols.push({
        headerName: `説明(${key})`,
        width: 200,
        editable: true,
        valueGetter: (params) => params.data?.descriptions?.[key] ?? "",
        valueSetter: (params) => {
          if (params.data) {
            params.data.descriptions[key] = params.newValue ?? "";
          }
          return true;
        },
      });
    }

    return cols;
  }, [descriptionKeys]);

  const onCellValueChanged = useCallback(
    (event: CellValueChangedEvent<RegionalGridRow>) => {
      const row = event.data;
      if (!row) return;

      onDataChange((prev: ParsedRegionalData) => {
        const newData = JSON.parse(JSON.stringify(prev)) as ParsedRegionalData;
        const entry = newData.pokedex[regionName]?.[row.no]?.[row.variantKey];
        if (!entry) return prev;

        const field = event.colDef.field as string | undefined;

        if (field === "form") entry.form = event.newValue ?? "";
        else if (field === "region") entry.region = event.newValue ?? "";
        else if (field === "megaEvolution") entry.mega_evolution = event.newValue ?? "";
        else if (field === "gigantamax") entry.gigantamax = event.newValue ?? "";
        else if (field === "type1") entry.type1 = event.newValue ?? "";
        else if (field === "type2") entry.type2 = event.newValue ?? "";
        else if (field === "hp") entry.hp = Number(event.newValue) || 0;
        else if (field === "attack") entry.attack = Number(event.newValue) || 0;
        else if (field === "defense") entry.defense = Number(event.newValue) || 0;
        else if (field === "specialAttack") entry.special_attack = Number(event.newValue) || 0;
        else if (field === "specialDefense") entry.special_defense = Number(event.newValue) || 0;
        else if (field === "speed") entry.speed = Number(event.newValue) || 0;
        else if (field === "ability1") entry.ability1 = event.newValue ?? "";
        else if (field === "ability2") entry.ability2 = event.newValue ?? "";
        else if (field === "dreamAbility") entry.dream_ability = event.newValue ?? "";
        else if (!field) {
          // Description column (uses valueGetter/valueSetter, no field)
          entry.description = { ...row.descriptions };
        }

        return newData;
      });
    },
    [onDataChange, regionName]
  );

  return (
    <div className="ag-theme-alpine-dark" style={{ height: "100%", width: "100%" }}>
      <AgGridReact<RegionalGridRow>
        rowData={rows}
        columnDefs={columnDefs}
        defaultColDef={{
          sortable: true,
          filter: true,
          resizable: true,
        }}
        onCellValueChanged={onCellValueChanged}
        getRowId={(params) => `${params.data.no}_${params.data.variantKey}`}
        animateRows={false}
      />
    </div>
  );
}
