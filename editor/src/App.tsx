import { useState, useEffect, useCallback, useRef } from "react";
import { invoke } from "@tauri-apps/api/core";
import { open } from "@tauri-apps/plugin-dialog";
import { PokedexGrid } from "./PokedexGrid";
import { RegionalGrid } from "./RegionalGrid";
import { NewDexModal } from "./NewDexModal";

type FileEntry = string;
type PokemonNames = Record<string, string>;

interface ParsedRegionalData {
  update: string;
  game_version: string;
  pokedex: Record<string, Record<string, Record<string, RegionalEntry>>>;
}

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

interface ParsedPokedexData {
  update: string;
  pokedex: Record<string, Record<string, PokedexEntry>>;
}

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

export default function App() {
  const [pokedexDir, setPokedexDir] = useState<string>("");
  const [files, setFiles] = useState<FileEntry[]>([]);
  const [selectedFile, setSelectedFile] = useState<string>("");
  const [pokemonNames, setPokemonNames] = useState<PokemonNames>({});
  const [fileData, setFileData] = useState<ParsedPokedexData | ParsedRegionalData | null>(null);
  const [activeRegion, setActiveRegion] = useState<string>("");
  const [showNewDexModal, setShowNewDexModal] = useState(false);
  const [status, setStatus] = useState("ディレクトリを選択してください");
  const [modified, setModified] = useState(false);
  const currentDataRef = useRef<ParsedPokedexData | ParsedRegionalData | null>(null);

  useEffect(() => {
    currentDataRef.current = fileData;
  }, [fileData]);

  const selectDirectory = useCallback(async () => {
    const dir = await open({ directory: true, multiple: false, title: "pokedex ディレクトリを選択" });
    if (dir && typeof dir === "string") {
      setPokedexDir(dir);
      setStatus(`ディレクトリ: ${dir}`);
      try {
        const fileList = await invoke<string[]>("list_json_files", { pokedexDir: dir });
        setFiles(fileList);
        const names = await invoke<PokemonNames>("get_pokemon_names", {
          pokedexJsonPath: `${dir}/pokedex.json`,
        });
        setPokemonNames(names);
        setSelectedFile("");
        setFileData(null);
        setModified(false);
      } catch (e) {
        setStatus(`エラー: ${e}`);
      }
    }
  }, []);

  const loadFile = useCallback(
    async (file: string) => {
      if (!pokedexDir) return;
      setSelectedFile(file);
      setModified(false);
      try {
        const fullPath = `${pokedexDir}/${file}`;
        const content = await invoke<string>("read_json_file", { filePath: fullPath });
        const parsed = JSON.parse(content);
        setFileData(parsed);

        if (file !== "pokedex.json" && parsed.pokedex) {
          const regions = Object.keys(parsed.pokedex);
          setActiveRegion(regions[0] || "");
        } else {
          setActiveRegion("");
        }
        setStatus(`読み込み完了: ${file}`);
      } catch (e) {
        setStatus(`読み込みエラー: ${e}`);
      }
    },
    [pokedexDir]
  );

  const handleExport = useCallback(async () => {
    if (!pokedexDir || !selectedFile || !currentDataRef.current) return;
    try {
      const fullPath = `${pokedexDir}/${selectedFile}`;
      const content = JSON.stringify(currentDataRef.current, null, 2);
      await invoke("write_json_file", { filePath: fullPath, content });
      setModified(false);
      setStatus(`出力完了: ${selectedFile}`);
    } catch (e) {
      setStatus(`出力エラー: ${e}`);
    }
  }, [pokedexDir, selectedFile]);

  const handlePokedexDataChange = useCallback(
    (updater: (prev: ParsedPokedexData) => ParsedPokedexData) => {
      setFileData((prev) => {
        if (!prev) return prev;
        return updater(prev as ParsedPokedexData);
      });
      setModified(true);
    },
    []
  );

  const handleRegionalDataChange = useCallback(
    (updater: (prev: ParsedRegionalData) => ParsedRegionalData) => {
      setFileData((prev) => {
        if (!prev) return prev;
        return updater(prev as ParsedRegionalData);
      });
      setModified(true);
    },
    []
  );

  const handleNewDexCreated = useCallback(
    async (gameVersion: string) => {
      if (!pokedexDir) return;
      try {
        const fileList = await invoke<string[]>("list_json_files", { pokedexDir });
        setFiles(fileList);
        const newFile = `${gameVersion}/${gameVersion}.json`;
        await loadFile(newFile);
      } catch (e) {
        setStatus(`エラー: ${e}`);
      }
    },
    [pokedexDir, loadFile]
  );

  const isPokedexJson = selectedFile === "pokedex.json";
  const regions = fileData && !isPokedexJson && "pokedex" in fileData ? Object.keys(fileData.pokedex) : [];

  return (
    <>
      <div className="app-header">
        <h1>Pokédex Editor</h1>
        <button onClick={selectDirectory}>フォルダ選択</button>
        {files.length > 0 && (
          <select value={selectedFile} onChange={(e) => loadFile(e.target.value)}>
            <option value="">-- ファイル選択 --</option>
            {files.map((f) => (
              <option key={f} value={f}>
                {f}
              </option>
            ))}
          </select>
        )}
        {selectedFile && (
          <button className="primary" onClick={handleExport}>
            出力 {modified ? "(変更あり)" : ""}
          </button>
        )}
        {pokedexDir && (
          <button onClick={() => setShowNewDexModal(true)}>新規図鑑作成</button>
        )}
      </div>

      <div className="app-body">
        {!isPokedexJson && regions.length > 0 && (
          <div className="region-tabs">
            {regions.map((r) => (
              <button
                key={r}
                className={r === activeRegion ? "active" : ""}
                onClick={() => setActiveRegion(r)}
              >
                {r}
              </button>
            ))}
          </div>
        )}

        <div className="grid-container">
          {!selectedFile && <div className="loading">ファイルを選択してください</div>}
          {selectedFile && !fileData && <div className="loading">読み込み中...</div>}
          {selectedFile && fileData && isPokedexJson && (
            <PokedexGrid
              data={fileData as ParsedPokedexData}
              onDataChange={handlePokedexDataChange}
            />
          )}
          {selectedFile && fileData && !isPokedexJson && activeRegion && (
            <RegionalGrid
              data={fileData as ParsedRegionalData}
              regionName={activeRegion}
              pokemonNames={pokemonNames}
              onDataChange={handleRegionalDataChange}
            />
          )}
        </div>
      </div>

      <div className="status-bar">{status}</div>

      {showNewDexModal && (
        <NewDexModal
          pokedexDir={pokedexDir}
          onClose={() => setShowNewDexModal(false)}
          onCreated={handleNewDexCreated}
        />
      )}
    </>
  );
}
