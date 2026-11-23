use serde::{Deserialize, Serialize};
use std::fs::File;
use std::io::Read;
use std::path::Path;
use tauri::{AppHandle, Emitter, Manager, WebviewUrl, WebviewWindowBuilder};

#[derive(Debug, Serialize, Deserialize, Clone)]
pub struct ExhibitPayload {
    pub id: String,
    pub title: String,
    pub r#type: String,
    pub content: Option<String>,
    pub src: Option<String>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct PackageMeta {
    pub filename: String,
    pub size_bytes: u64,
    pub format: String,
    pub is_valid: bool,
}

#[tauri::command]
pub async fn detach_exhibit_window(app: AppHandle, exhibit: ExhibitPayload) -> Result<(), String> {
    if let Some(window) = app.get_webview_window("exhibit") {
        window.show().map_err(|e| e.to_string())?;
        window.set_focus().map_err(|e| e.to_string())?;
        window
            .emit("exhibit-updated", &exhibit)
            .map_err(|e| e.to_string())?;
    } else {
        let new_window = WebviewWindowBuilder::new(
            &app,
            "exhibit",
            WebviewUrl::App("/exhibit-detached".into()),
        )
        .title(format!("Exhibit: {}", exhibit.title))
        .inner_size(800.0, 640.0)
        .min_inner_size(480.0, 360.0)
        .resizable(true)
        .build()
        .map_err(|e| e.to_string())?;

        new_window
            .emit("exhibit-updated", &exhibit)
            .map_err(|e| e.to_string())?;
    }
    Ok(())
}

#[tauri::command]
pub async fn detach_scratchpad_window(app: AppHandle, notes: String) -> Result<(), String> {
    if let Some(window) = app.get_webview_window("scratchpad") {
        window.show().map_err(|e| e.to_string())?;
        window.set_focus().map_err(|e| e.to_string())?;
        window
            .emit("scratchpad-updated", &notes)
            .map_err(|e| e.to_string())?;
    } else {
        let new_window = WebviewWindowBuilder::new(
            &app,
            "scratchpad",
            WebviewUrl::App("/scratchpad-detached".into()),
        )
        .title("Candidate Scratchpad")
        .inner_size(520.0, 480.0)
        .always_on_top(true)
        .resizable(true)
        .build()
        .map_err(|e| e.to_string())?;

        new_window
            .emit("scratchpad-updated", &notes)
            .map_err(|e| e.to_string())?;
    }
    Ok(())
}

#[tauri::command]
pub async fn inspect_package_file(file_path: String) -> Result<PackageMeta, String> {
    let path = Path::new(&file_path);
    if !path.exists() {
        return Err("Target file does not exist".to_string());
    }

    let ext = path
        .extension()
        .and_then(|s| s.to_str())
        .unwrap_or("")
        .to_lowercase();
    let metadata = std::fs::metadata(path).map_err(|e| e.to_string())?;
    let size_bytes = metadata.len();

    let mut file = File::open(path).map_err(|e| e.to_string())?;
    let mut header = [0u8; 16];
    let _ = file.read(&mut header);

    let format = match ext.as_str() {
        "psmk" => "passimark_package",
        "psme" => "passimark_encrypted",
        "vce" => "visual_certexam",
        _ => "unknown",
    }
    .to_string();

    let is_valid = match ext.as_str() {
        "psmk" => header.starts_with(&[0x50, 0x4B, 0x03, 0x04]), // ZIP header
        "vce" => size_bytes > 32,
        _ => true,
    };

    Ok(PackageMeta {
        filename: path
            .file_name()
            .and_then(|s| s.to_str())
            .unwrap_or("unknown")
            .to_string(),
        size_bytes,
        format,
        is_valid,
    })
}

#[tauri::command]
pub fn get_desktop_runtime_info() -> serde_json::Value {
    serde_json::json!({
        "app_name": "Passimark Desktop",
        "version": "2.0.0",
        "engine": "Tauri 2.0 Rust Core",
        "capabilities": ["multi_window", "os_file_association", "offline_sqlite", "hardware_acceleration"]
    })
}
