/**
 * Passimark Desktop Bridge — Tauri 2.0 Rust Core Interop & Dual-Monitor Exhibit Detachment
 */

export class TauriBridge {
  static isTauri() {
    return typeof window !== 'undefined' && Boolean(
      window.__TAURI_INTERNALS__ || window.__TAURI__ || window.__TAURI_METADATA__
    );
  }

  /**
   * Detach scenario exhibit, network diagram, or topology to a secondary floating window.
   * Works on dual-monitor desktop setups, allowing multi-tasking without tab switching.
   */
  static async detachExhibit(exhibitData) {
    if (this.isTauri() && window.__TAURI__?.core?.invoke) {
      try {
        return await window.__TAURI__.core.invoke('detach_exhibit_window', {
          exhibit: exhibitData,
        });
      } catch (err) {
        console.warn('Tauri invoke failed, falling back to desktop popup:', err);
      }
    }

    // Desktop browser fallback: Multi-window popup with BroadcastChannel sync
    const windowName = `Passimark_Exhibit_${exhibitData.id || 'Active'}`;
    const left = window.screen.availWidth ? window.screen.availWidth - 750 : 200;
    const features = `width=720,height=600,left=${left},top=100,resizable=yes,scrollbars=yes,status=no`;

    try {
      localStorage.setItem('pmk_detached_exhibit', JSON.stringify({
        ...exhibitData,
        detached_at: Date.now(),
      }));

      const popup = window.open('', windowName, features);
      if (popup) {
        popup.document.title = `${exhibitData.title || 'Exhibit'} — Passimark Exhibit Viewer`;
        popup.document.body.innerHTML = `
          <!DOCTYPE html>
          <html>
            <head>
              <title>${exhibitData.title || 'Exhibit'}</title>
              <meta name="viewport" content="width=device-width, initial-scale=1">
              <link rel="stylesheet" href="/build/assets/app.css">
              <style>
                body { margin: 0; background: #030712; color: #f9fafb; font-family: ui-sans-serif, system-ui, sans-serif; }
                .container { padding: 1.5rem; max-width: 1000px; margin: 0 auto; }
                .header { border-bottom: 1px solid #1f2937; padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
                .badge { background: rgba(59, 130, 246, 0.15); color: #93c5fd; font-size: 11px; padding: 2px 8px; border-radius: 4px; font-weight: bold; text-transform: uppercase; }
                .content { font-size: 14px; line-height: 1.6; color: #d1d5db; }
                pre { background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; padding: 1rem; overflow-x: auto; color: #38bdf8; font-family: monospace; }
                img { max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #1e293b; }
              </style>
            </head>
            <body>
              <div class="container">
                <div class="header">
                  <div>
                    <span class="badge">Secondary Exhibit Detached Window</span>
                    <h2 style="margin: 4px 0 0; font-size: 18px; font-weight: bold;">${exhibitData.title || 'Exhibit Dossier'}</h2>
                  </div>
                  <button onclick="window.close()" style="background:#1f2937;color:#9ca3af;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:12px;">Close</button>
                </div>
                <div class="content">
                  ${exhibitData.src ? `<img src="${exhibitData.src}" alt="${exhibitData.title || 'Diagram'}"/>` : ''}
                  ${exhibitData.content ? (exhibitData.type === 'code' || exhibitData.type === 'logs' ? `<pre>${exhibitData.content.replace(/</g, '&lt;')}</pre>` : `<div>${exhibitData.content}</div>`) : ''}
                </div>
              </div>
            </body>
          </html>
        `;
        popup.focus();
        return true;
      }
    } catch (e) {
      console.error('Failed to open detached exhibit popup', e);
    }
    return false;
  }

  /**
   * Detach candidate scratchpad to a floating utility window.
   */
  static async detachScratchpad(initialNotes = '', onNotesUpdate = null) {
    if (this.isTauri() && window.__TAURI__?.core?.invoke) {
      try {
        return await window.__TAURI__.core.invoke('detach_scratchpad_window', {
          notes: initialNotes,
        });
      } catch (err) {
        console.warn('Tauri scratchpad invoke fallback', err);
      }
    }

    const windowName = 'Passimark_Scratchpad';
    const features = 'width=520,height=480,left=150,top=150,resizable=yes,scrollbars=yes';
    const popup = window.open('', windowName, features);
    if (popup) {
      popup.document.title = 'Candidate Scratchpad — Passimark';
      popup.document.body.innerHTML = `
        <!DOCTYPE html>
        <html>
          <head>
            <title>Scratchpad</title>
            <style>
              body { margin: 0; padding: 1rem; background: #030712; color: #fff; font-family: ui-sans-serif, system-ui; }
              h3 { margin: 0 0 8px; font-size: 14px; color: #38bdf8; }
              textarea { width: 100%; height: 380px; box-sizing: border-box; background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; color: #f1f5f9; padding: 12px; font-family: monospace; font-size: 13px; resize: none; }
            </style>
          </head>
          <body>
            <h3>Candidate Scratchpad</h3>
            <textarea id="notes-area" placeholder="Jot down notes or subnet calculations here...">${initialNotes}</textarea>
            <script>
              const area = document.getElementById('notes-area');
              area.addEventListener('input', () => {
                if (window.opener && !window.opener.closed) {
                  window.opener.postMessage({ type: 'PMK_NOTES_UPDATE', notes: area.value }, '*');
                }
              });
            </script>
          </body>
        </html>
      `;
      popup.focus();

      if (onNotesUpdate) {
        window.addEventListener('message', (event) => {
          if (event.data?.type === 'PMK_NOTES_UPDATE') {
            onNotesUpdate(event.data.notes);
          }
        });
      }
      return true;
    }
    return false;
  }

  /**
   * Listen for native OS file associations (.psmk, .psme, .vce)
   */
  static async onFileOpen(callback) {
    if (this.isTauri() && window.__TAURI__?.event?.listen) {
      try {
        return await window.__TAURI__.event.listen('open-package-file', (event) => {
          if (event.payload?.path) {
            callback(event.payload.path);
          }
        });
      } catch (err) {
        console.warn('Tauri file association listener unavailable', err);
      }
    }
  }

  /**
   * Register VCE-parity physical keyboard shortcuts for desktop power users.
   * Single-key option selection (1-9, A-I), Ctrl+F flag, Ctrl+S strike, Ctrl+P palette, Esc pause.
   */
  static registerDesktopShortcuts({
    onSelectOption,
    onToggleFlag,
    onToggleStrike,
    onTogglePalette,
    onToggleCalculator,
    onPause,
    onNext,
    onPrevious,
    onSubmit,
  }) {
    const handleKeyDown = (e) => {
      // Don't intercept when candidate is typing inside inputs or textareas
      const tag = e.target?.tagName?.toLowerCase();
      if (tag === 'input' || tag === 'textarea' || e.target?.isContentEditable) {
        return;
      }

      const ctrlOrCmd = e.ctrlKey || e.metaKey;

      // Ctrl + F: Flag question
      if (ctrlOrCmd && (e.key === 'f' || e.key === 'F')) {
        e.preventDefault();
        onToggleFlag && onToggleFlag();
        return;
      }

      // Ctrl + S: Strikethrough active option
      if (ctrlOrCmd && (e.key === 's' || e.key === 'S')) {
        e.preventDefault();
        onToggleStrike && onToggleStrike();
        return;
      }

      // Ctrl + P: Toggle Question Palette
      if (ctrlOrCmd && (e.key === 'p' || e.key === 'P')) {
        e.preventDefault();
        onTogglePalette && onTogglePalette();
        return;
      }

      // Ctrl + K or Alt + C: Open Calculator
      if ((ctrlOrCmd && (e.key === 'k' || e.key === 'K')) || (e.altKey && (e.key === 'c' || e.key === 'C'))) {
        e.preventDefault();
        onToggleCalculator && onToggleCalculator();
        return;
      }

      // Escape: Pause Assessment
      if (e.key === 'Escape') {
        e.preventDefault();
        onPause && onPause();
        return;
      }

      // Enter / Ctrl+Enter: Submit Answer
      if (e.key === 'Enter' && ctrlOrCmd) {
        e.preventDefault();
        onSubmit && onSubmit();
        return;
      }

      // ArrowLeft / ArrowRight: Previous / Next
      if (e.key === 'ArrowLeft' && !ctrlOrCmd) {
        e.preventDefault();
        onPrevious && onPrevious();
        return;
      }
      if (e.key === 'ArrowRight' && !ctrlOrCmd) {
        e.preventDefault();
        onNext && onNext();
        return;
      }

      // Single-Key Numeric Option Selection: 1 to 9 -> Option index 0 to 8
      if (/^[1-9]$/.test(e.key) && !ctrlOrCmd && !e.altKey) {
        const optionIndex = parseInt(e.key, 10) - 1;
        onSelectOption && onSelectOption(optionIndex);
        return;
      }

      // Single-Key Alphabetic Option Selection: A to I -> Option index 0 to 8
      if (/^[a-iA-I]$/.test(e.key) && !ctrlOrCmd && !e.altKey) {
        const optionIndex = e.key.toUpperCase().charCodeAt(0) - 65; // 'A' is 65
        onSelectOption && onSelectOption(optionIndex);
        return;
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }
}
