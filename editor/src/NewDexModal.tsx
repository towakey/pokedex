import { useState, useCallback } from "react";
import { invoke } from "@tauri-apps/api/core";

interface Props {
  pokedexDir: string;
  onClose: () => void;
  onCreated: (gameVersion: string) => void;
}

export function NewDexModal({ pokedexDir, onClose, onCreated }: Props) {
  const [gameVersion, setGameVersion] = useState("");
  const [regionName, setRegionName] = useState("");
  const [error, setError] = useState("");

  const handleCreate = useCallback(async () => {
    if (!gameVersion.trim()) {
      setError("ゲームバージョン名を入力してください");
      return;
    }
    if (!regionName.trim()) {
      setError("地方名を入力してください");
      return;
    }

    try {
      const dirPath = `${pokedexDir}/${gameVersion}`;
      await invoke("create_new_regional_dex", {
        dirPath,
        gameVersion: gameVersion.trim(),
        regionName: regionName.trim(),
      });
      onCreated(gameVersion.trim());
      onClose();
    } catch (e) {
      setError(`作成エラー: ${e}`);
    }
  }, [gameVersion, regionName, pokedexDir, onClose, onCreated]);

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <h2>新しい地方図鑑を作成</h2>

        <label>ゲームバージョン名（ディレクトリ名・ファイル名）</label>
        <input
          value={gameVersion}
          onChange={(e) => setGameVersion(e.target.value)}
          placeholder="例: Diamond_Pearl_Platinum"
        />

        <label>地方名（図鑑名）</label>
        <input
          value={regionName}
          onChange={(e) => setRegionName(e.target.value)}
          placeholder="例: シンオウ図鑑"
        />

        {error && <p style={{ color: "#e94560", fontSize: 13 }}>{error}</p>}

        <div className="modal-actions">
          <button onClick={onClose}>キャンセル</button>
          <button className="primary" onClick={handleCreate}>
            作成
          </button>
        </div>
      </div>
    </div>
  );
}
