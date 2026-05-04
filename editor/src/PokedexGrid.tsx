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

interface PokedexEntry {
  form: string | null;
  region: string | null;
  mega_evolution: string | null;
  gigantamax: string | null;
  forms: Record<string, string>;
  classification: Record<string, string>;
  egg: string[];
  height: string;
  weight: string;
  name: Record<string, string>;
}

interface ParsedPokedexData {
  update: string;
  pokedex: Record<string, Record<string, PokedexEntry>>;
}

interface PokedexGridRow {
  id: string;
  variantKey: string;
  nameJpn: string;
  nameEng: string;
  nameFra: string;
  nameIta: string;
  nameGer: string;
  nameSpa: string;
  nameKor: string;
  nameChs: string;
  nameCht: string;
  formsJpn: string;
  formsEng: string;
  classificationJpn: string;
  classificationEng: string;
  egg: string;
  height: string;
  weight: string;
  form: string;
  region: string;
  megaEvolution: string;
  gigantamax: string;
}

interface Props {
  data: ParsedPokedexData;
  onDataChange: (updater: (prev: ParsedPokedexData) => ParsedPokedexData) => void;
}

export function PokedexGrid({ data, onDataChange }: Props) {
  const rows = useMemo<PokedexGridRow[]>(() => {
    const result: PokedexGridRow[] = [];
    const pokedex = data.pokedex;
    for (const id of Object.keys(pokedex).sort()) {
      const variants = pokedex[id];
      for (const [variantKey, entry] of Object.entries(variants)) {
        result.push({
          id,
          variantKey,
          nameJpn: entry.name?.jpn ?? "",
          nameEng: entry.name?.eng ?? "",
          nameFra: entry.name?.fra ?? "",
          nameIta: entry.name?.ita ?? "",
          nameGer: entry.name?.ger ?? "",
          nameSpa: entry.name?.spa ?? "",
          nameKor: entry.name?.kor ?? "",
          nameChs: entry.name?.chs ?? "",
          nameCht: entry.name?.cht ?? "",
          formsJpn: entry.forms?.jpn ?? "",
          formsEng: entry.forms?.eng ?? "",
          classificationJpn: entry.classification?.jpn ?? "",
          classificationEng: entry.classification?.eng ?? "",
          egg: Array.isArray(entry.egg) ? entry.egg.join(", ") : "",
          height: entry.height ?? "",
          weight: entry.weight ?? "",
          form: entry.form ?? "",
          region: entry.region ?? "",
          megaEvolution: entry.mega_evolution ?? "",
          gigantamax: entry.gigantamax ?? "",
        });
      }
    }
    return result;
  }, [data]);

  const columnDefs = useMemo<ColDef<PokedexGridRow>[]>(
    () => [
      { field: "id", headerName: "ID", width: 80, editable: false, pinned: "left" },
      { field: "variantKey", headerName: "バリアントキー", width: 200, editable: false },
      { field: "nameJpn", headerName: "名前(日)", width: 120, editable: true },
      { field: "nameEng", headerName: "名前(英)", width: 120, editable: true },
      { field: "nameFra", headerName: "名前(仏)", width: 120, editable: true },
      { field: "nameIta", headerName: "名前(伊)", width: 120, editable: true },
      { field: "nameGer", headerName: "名前(独)", width: 120, editable: true },
      { field: "nameSpa", headerName: "名前(西)", width: 120, editable: true },
      { field: "nameKor", headerName: "名前(韓)", width: 120, editable: true },
      { field: "nameChs", headerName: "名前(簡中)", width: 120, editable: true },
      { field: "nameCht", headerName: "名前(繁中)", width: 120, editable: true },
      { field: "formsJpn", headerName: "フォーム(日)", width: 120, editable: true },
      { field: "formsEng", headerName: "フォーム(英)", width: 120, editable: true },
      { field: "classificationJpn", headerName: "分類(日)", width: 140, editable: true },
      { field: "classificationEng", headerName: "分類(英)", width: 140, editable: true },
      { field: "egg", headerName: "タマゴグループ", width: 140, editable: true },
      { field: "height", headerName: "高さ", width: 80, editable: true },
      { field: "weight", headerName: "重さ", width: 80, editable: true },
      { field: "form", headerName: "form", width: 100, editable: true },
      { field: "region", headerName: "region", width: 100, editable: true },
      { field: "megaEvolution", headerName: "メガ進化", width: 100, editable: true },
      { field: "gigantamax", headerName: "キョダイマックス", width: 120, editable: true },
    ],
    []
  );

  const onCellValueChanged = useCallback(
    (event: CellValueChangedEvent<PokedexGridRow>) => {
      const row = event.data;
      if (!row) return;
      onDataChange((prev: ParsedPokedexData) => {
        const newData = JSON.parse(JSON.stringify(prev)) as ParsedPokedexData;
        const entry = newData.pokedex[row.id]?.[row.variantKey];
        if (!entry) return prev;

        const langMap: Record<string, [string, string]> = {
          nameJpn: ["name", "jpn"],
          nameEng: ["name", "eng"],
          nameFra: ["name", "fra"],
          nameIta: ["name", "ita"],
          nameGer: ["name", "ger"],
          nameSpa: ["name", "spa"],
          nameKor: ["name", "kor"],
          nameChs: ["name", "chs"],
          nameCht: ["name", "cht"],
          formsJpn: ["forms", "jpn"],
          formsEng: ["forms", "eng"],
          classificationJpn: ["classification", "jpn"],
          classificationEng: ["classification", "eng"],
        };

        const field = event.colDef.field as string;
        if (field in langMap) {
          const [obj, lang] = langMap[field];
          const target = entry[obj as keyof PokedexEntry];
          if (target && typeof target === "object" && !Array.isArray(target)) {
            (target as Record<string, string>)[lang] = event.newValue ?? "";
          }
        } else if (field === "egg") {
          entry.egg = (event.newValue ?? "")
            .split(",")
            .map((s: string) => s.trim())
            .filter(Boolean);
        } else if (field === "height") {
          entry.height = event.newValue ?? "";
        } else if (field === "weight") {
          entry.weight = event.newValue ?? "";
        } else if (field === "form") {
          entry.form = event.newValue ?? "";
        } else if (field === "region") {
          entry.region = event.newValue ?? "";
        } else if (field === "megaEvolution") {
          entry.mega_evolution = event.newValue ?? "";
        } else if (field === "gigantamax") {
          entry.gigantamax = event.newValue ?? "";
        }

        return newData;
      });
    },
    [onDataChange]
  );

  return (
    <div className="ag-theme-alpine-dark" style={{ height: "100%", width: "100%" }}>
      <AgGridReact<PokedexGridRow>
        rowData={rows}
        columnDefs={columnDefs}
        defaultColDef={{
          sortable: true,
          filter: true,
          resizable: true,
        }}
        onCellValueChanged={onCellValueChanged}
        getRowId={(params) => `${params.data.id}_${params.data.variantKey}`}
        animateRows={false}
      />
    </div>
  );
}
