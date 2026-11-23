// Prevents additional console window on Windows in release
#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

mod commands;

use commands::{
    detach_exhibit_window, detach_scratchpad_window, get_desktop_runtime_info, inspect_package_file,
};
use tauri::{Emitter, Manager};

fn main() {
    tauri::Builder::default()
        .plugin(tauri_plugin_fs::init())
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_shell::init())
        .invoke_handler(tauri::generate_handler![
            detach_exhibit_window,
            detach_scratchpad_window,
            inspect_package_file,
            get_desktop_runtime_info
        ])
        .setup(|app| {
            // Check command line arguments for opened files (e.g., double-clicking .psmk or .vce file)
            let args: Vec<String> = std::env::args().collect();
            if args.len() > 1 {
                let potential_file = &args[1];
                if potential_file.ends_with(".psmk")
                    || potential_file.ends_with(".psme")
                    || potential_file.ends_with(".vce")
                {
                    let app_handle = app.handle().clone();
                    let file_arg = potential_file.clone();
                    tauri::async_runtime::spawn(async move {
                        tokio::time::sleep(tokio::time::Duration::from_millis(600)).await;
                        let _ = app_handle.emit(
                            "open-package-file",
                            serde_json::json!({ "path": file_arg }),
                        );
                    });
                }
            }

            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("error while running Passimark Desktop application");
}
