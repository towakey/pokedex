use serde_json::Value;
use std::collections::HashMap;
use std::fs;
use std::path::PathBuf;

#[tauri::command]
fn list_json_files(pokedex_dir: String) -> Result<Vec<String>, String> {
    let path = PathBuf::from(&pokedex_dir);
    if !path.exists() {
        return Err(format!("Directory not found: {}", pokedex_dir));
    }

    let mut files = Vec::new();

    // Add pokedex.json
    let pokedex_json = path.join("pokedex.json");
    if pokedex_json.exists() {
        files.push("pokedex.json".to_string());
    }

    // Scan subdirectories for game version JSONs
    if let Ok(entries) = fs::read_dir(&path) {
        for entry in entries.flatten() {
            let entry_path = entry.path();
            if entry_path.is_dir() {
                let dir_name = entry.file_name().to_string_lossy().to_string();
                let json_file = entry_path.join(format!("{}.json", dir_name));
                if json_file.exists() {
                    files.push(format!("{}/{}.json", dir_name, dir_name));
                }
            }
        }
    }

    files.sort();
    Ok(files)
}

#[tauri::command]
fn read_json_file(file_path: String) -> Result<String, String> {
    fs::read_to_string(&file_path).map_err(|e| format!("Failed to read file: {}", e))
}

#[tauri::command]
fn write_json_file(file_path: String, content: String) -> Result<(), String> {
    fs::write(&file_path, &content).map_err(|e| format!("Failed to write file: {}", e))
}

#[tauri::command]
fn get_pokemon_names(pokedex_json_path: String) -> Result<HashMap<String, String>, String> {
    let content =
        fs::read_to_string(&pokedex_json_path).map_err(|e| format!("Failed to read: {}", e))?;
    let data: Value =
        serde_json::from_str(&content).map_err(|e| format!("Failed to parse JSON: {}", e))?;

    let mut names: HashMap<String, String> = HashMap::new();

    if let Some(pokedex) = data.get("pokedex").and_then(|p| p.as_object()) {
        for (id, variants) in pokedex {
            if let Some(variants_obj) = variants.as_object() {
                for (_, variant_data) in variants_obj {
                    if let Some(name) = variant_data
                        .get("name")
                        .and_then(|n| n.get("jpn"))
                        .and_then(|n| n.as_str())
                    {
                        names.insert(id.clone(), name.to_string());
                        break;
                    }
                }
            }
        }
    }

    Ok(names)
}

#[tauri::command]
fn create_new_regional_dex(
    dir_path: String,
    game_version: String,
    region_name: String,
) -> Result<String, String> {
    let dir = PathBuf::from(&dir_path);
    fs::create_dir_all(&dir).map_err(|e| format!("Failed to create directory: {}", e))?;

    let file_path = dir.join(format!("{}.json", game_version));

    let mut pokedex_map = serde_json::Map::new();
    pokedex_map.insert(region_name, Value::Object(serde_json::Map::new()));

    let data = serde_json::json!({
        "update": chrono_today(),
        "game_version": game_version,
        "pokedex": pokedex_map
    });

    let content = serde_json::to_string_pretty(&data)
        .map_err(|e| format!("Failed to serialize: {}", e))?;

    fs::write(&file_path, &content).map_err(|e| format!("Failed to write file: {}", e))?;

    Ok(file_path.to_string_lossy().to_string())
}

fn chrono_today() -> String {
    let now = std::time::SystemTime::now();
    let since_epoch = now
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap_or_default();
    let secs = since_epoch.as_secs();
    let days = secs / 86400;
    let years = 1970 + days / 365;
    let remaining = days % 365;
    let month = remaining / 30 + 1;
    let day = remaining % 30 + 1;
    format!("{}{:02}{:02}", years, month.min(12), day.min(31))
}

pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_fs::init())
        .invoke_handler(tauri::generate_handler![
            list_json_files,
            read_json_file,
            write_json_file,
            get_pokemon_names,
            create_new_regional_dex,
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
