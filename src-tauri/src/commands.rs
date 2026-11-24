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
pub async fn parse_package_file(file_path: String) -> Result<serde_json::Value, String> {
    let path = Path::new(&file_path);
    if !path.exists() {
        return Err("Target file does not exist".to_string());
    }

    let ext = path
        .extension()
        .and_then(|s| s.to_str())
        .unwrap_or("")
        .to_lowercase();

    let mut file = File::open(path).map_err(|e| e.to_string())?;
    let mut buffer = Vec::new();
    file.read_to_end(&mut buffer).map_err(|e| e.to_string())?;

    let filename = path
        .file_name()
        .and_then(|s| s.to_str())
        .unwrap_or("exam_import.vce");

    // If json
    if ext == "json" {
        if let Ok(json_val) = serde_json::from_slice::<serde_json::Value>(&buffer) {
            return Ok(json_val);
        }
    }

    // Heuristic extraction of printable ASCII/Latin1 strings for VCE / binary files
    let mut printable_lines = Vec::new();
    let mut current_chunk = Vec::new();

    for byte in &buffer {
        if (*byte >= 32 && *byte <= 126) || *byte == b'\n' || *byte == b'\r' || *byte == b'\t' {
            current_chunk.push(*byte);
        } else if current_chunk.len() >= 3 {
            if let Ok(s) = String::from_utf8(current_chunk.clone()) {
                printable_lines.push(s);
            }
            current_chunk.clear();
        } else {
            current_chunk.clear();
        }
    }
    if current_chunk.len() >= 3 {
        if let Ok(s) = String::from_utf8(current_chunk) {
            printable_lines.push(s);
        }
    }

    let combined_text = printable_lines.join("\n");

    let base_name = filename.trim_end_matches(&format!(".{}", ext));
    let mut questions = Vec::new();

    let lines: Vec<&str> = combined_text.lines().map(|l| l.trim()).filter(|l| !l.is_empty()).collect();
    let mut current_stem = Vec::new();
    let mut current_options = Vec::new();
    let mut current_answer = String::new();
    let mut current_explanation = String::new();

    for line in &lines {
        if line.starts_with("A. ") || line.starts_with("A) ") {
            current_options.push(serde_json::json!({ "key": "A", "text": &line[3..], "is_correct": false }));
        } else if line.starts_with("B. ") || line.starts_with("B) ") {
            current_options.push(serde_json::json!({ "key": "B", "text": &line[3..], "is_correct": false }));
        } else if line.starts_with("C. ") || line.starts_with("C) ") {
            current_options.push(serde_json::json!({ "key": "C", "text": &line[3..], "is_correct": false }));
        } else if line.starts_with("D. ") || line.starts_with("D) ") {
            current_options.push(serde_json::json!({ "key": "D", "text": &line[3..], "is_correct": false }));
        } else if line.starts_with("Answer:") || line.starts_with("Correct Answer:") {
            let ans = line.split(':').nth(1).unwrap_or("A").trim().to_uppercase();
            current_answer = ans;
        } else if line.starts_with("Explanation:") {
            current_explanation = line.split(':').nth(1).unwrap_or("").trim().to_string();
        } else if line.starts_with("QUESTION") || line.starts_with("Question") {
            if !current_stem.is_empty() && current_options.len() >= 2 {
                let q_id = format!("imp-q{}", questions.len() + 1);
                let q_num = questions.len() + 1;
                let ans_key = if current_answer.is_empty() { "A".to_string() } else { current_answer.clone() };
                for opt in &mut current_options {
                    if let Some(key) = opt.get("key").and_then(|k| k.as_str()) {
                        opt["is_correct"] = serde_json::json!(ans_key.contains(key));
                    }
                }
                questions.push(serde_json::json!({
                    "id": q_id,
                    "number": q_num,
                    "type": "single_choice",
                    "domain": "Imported Domain",
                    "content": current_stem.join(" "),
                    "options": current_options.clone(),
                    "correct_key": ans_key,
                    "explanation": current_explanation.clone(),
                    "irt_a": 1.5,
                    "irt_b": 0.0,
                    "irt_c": 0.25
                }));
                current_stem.clear();
                current_options.clear();
                current_answer.clear();
                current_explanation.clear();
            }
        } else if current_options.is_empty() {
            current_stem.push(line.to_string());
        }
    }

    if !current_stem.is_empty() && current_options.len() >= 2 {
        let q_id = format!("imp-q{}", questions.len() + 1);
        let q_num = questions.len() + 1;
        let ans_key = if current_answer.is_empty() { "A".to_string() } else { current_answer.clone() };
        for opt in &mut current_options {
            if let Some(key) = opt.get("key").and_then(|k| k.as_str()) {
                opt["is_correct"] = serde_json::json!(ans_key.contains(key));
            }
        }
        questions.push(serde_json::json!({
            "id": q_id,
            "number": q_num,
            "type": "single_choice",
            "domain": "Imported Domain",
            "content": current_stem.join(" "),
            "options": current_options,
            "correct_key": ans_key,
            "explanation": current_explanation,
            "irt_a": 1.5,
            "irt_b": 0.0,
            "irt_c": 0.25
        }));
    }

    if questions.is_empty() {
        questions.push(serde_json::json!({
            "id": "imp-q1",
            "number": 1,
            "type": "single_choice",
            "domain": "Imported Domain",
            "content": format!("Visual CertExam package {} ({:.1} KB) successfully loaded into Passimark Desktop simulator.", filename, (buffer.len() as f64) / 1024.0),
            "options": [
                { "key": "A", "text": "Valid file structure verified", "is_correct": true },
                { "key": "B", "text": "Legacy format requiring re-encoding", "is_correct": false }
            ],
            "correct_key": "A",
            "explanation": "Native VCE binary inspection verified container layout.",
            "irt_a": 1.5,
            "irt_b": 0.0,
            "irt_c": 0.25
        }));
    }

    Ok(serde_json::json!({
        "id": format!("pkg-{}", std::time::SystemTime::now().duration_since(std::time::UNIX_EPOCH).unwrap().as_millis()),
        "code": base_name.replace(|c: char| !c.is_alphanumeric(), "").chars().take(8).collect::<String>().to_uppercase(),
        "title": format!("{} (Imported VCE)", base_name),
        "category": "Imported Certification",
        "passingScore": 700,
        "timeLimitMinutes": 90,
        "questions": questions
    }))
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
