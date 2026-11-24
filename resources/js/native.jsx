import React from 'react';
import { createRoot } from 'react-dom/client';
import DesktopApp from './Native/Desktop/DesktopApp';
import DesktopExhibitWindow from './Native/Desktop/DesktopExhibitWindow';
import DesktopScratchpadWindow from './Native/Desktop/DesktopScratchpadWindow';
import MobileApp from './Native/Mobile/MobileApp';
import ErrorBoundary from './Components/ErrorBoundary';
import '../css/app.css';
import '../css/fault.css';

function resolveNativeComponent() {
  const path = window.location.pathname;
  const hash = window.location.hash;
  const params = new URLSearchParams(window.location.search);
  const forcedMode = params.get('mode');

  // Detached windows for Tauri multi-window desktop
  const windowLabel = typeof window !== 'undefined' && (
    window.__TAURI_INTERNALS__?.metadata?.currentWebview?.windowLabel ||
    window.__TAURI_METADATA__?.currentWindow?.label ||
    params.get('window')
  );

  if (windowLabel === 'exhibit' || path.includes('exhibit-detached') || hash.includes('exhibit')) {
    return <DesktopExhibitWindow />;
  }
  if (windowLabel === 'scratchpad' || path.includes('scratchpad-detached') || hash.includes('scratchpad')) {
    return <DesktopScratchpadWindow />;
  }

  // Force mode via query param if set
  if (forcedMode === 'desktop') return <DesktopApp />;
  if (forcedMode === 'mobile') return <MobileApp />;

  // Detect runtime environment
  const isTauri = typeof window !== 'undefined' && Boolean(
    window.__TAURI_INTERNALS__ || window.__TAURI__ || window.__TAURI_METADATA__
  );

  const isCapacitor = typeof window !== 'undefined' && Boolean(
    window.Capacitor?.isNativePlatform?.() || window.Capacitor
  );

  const isMobileDevice = typeof navigator !== 'undefined' && (
    /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
    (window.innerWidth < 1024 && 'ontouchstart' in window)
  );

  if (isTauri) {
    return <DesktopApp />;
  }

  if (isCapacitor || isMobileDevice) {
    return <MobileApp />;
  }

  // Default to desktop application for larger screens
  return <DesktopApp />;
}

const container = document.getElementById('native-app');
if (container) {
  const root = createRoot(container);
  root.render(
    <ErrorBoundary>
      {resolveNativeComponent()}
    </ErrorBoundary>
  );
}
