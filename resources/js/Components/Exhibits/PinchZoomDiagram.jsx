import React, { useRef, useState, useEffect } from 'react';
import { ZoomIn, ZoomOut, RotateCcw, Maximize2 } from 'lucide-react';

export default function PinchZoomDiagram({ src, alt = "Exhibit Diagram", title = "Exhibit Diagram" }) {
  const containerRef = useRef(null);
  const [scale, setScale] = useState(1);
  const [position, setPosition] = useState({ x: 0, y: 0 });
  const [isPanning, setIsPanning] = useState(false);
  const [startPos, setStartPos] = useState({ x: 0, y: 0 });
  const [lastDist, setLastDist] = useState(null);
  const [lastTap, setLastTap] = useState(0);

  const resetZoom = () => {
    setScale(1);
    setPosition({ x: 0, y: 0 });
  };

  const handleZoomIn = () => {
    setScale(prev => Math.min(prev + 0.5, 4.0));
  };

  const handleZoomOut = () => {
    setScale(prev => {
      const next = Math.max(prev - 0.5, 1.0);
      if (next === 1.0) setPosition({ x: 0, y: 0 });
      return next;
    });
  };

  // Touch Handlers for Pinch & Pan
  const handleTouchStart = (e) => {
    if (e.touches.length === 2) {
      // Pinch start
      const dist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      setLastDist(dist);
    } else if (e.touches.length === 1) {
      // Check for double-tap
      const now = Date.now();
      if (now - lastTap < 300) {
        // Double tap toggles zoom
        if (scale > 1.2) {
          resetZoom();
        } else {
          setScale(2.5);
        }
        setLastTap(0);
        return;
      }
      setLastTap(now);

      // Pan start if zoomed
      if (scale > 1) {
        setIsPanning(true);
        setStartPos({
          x: e.touches[0].clientX - position.x,
          y: e.touches[0].clientY - position.y,
        });
      }
    }
  };

  const handleTouchMove = (e) => {
    if (e.touches.length === 2 && lastDist !== null) {
      // Pinch zoom in progress
      e.preventDefault();
      const dist = Math.hypot(
        e.touches[0].clientX - e.touches[1].clientX,
        e.touches[0].clientY - e.touches[1].clientY
      );
      const factor = dist / lastDist;
      setScale(prev => Math.min(Math.max(prev * factor, 1.0), 4.0));
      setLastDist(dist);
    } else if (e.touches.length === 1 && isPanning && scale > 1) {
      // Panning while zoomed
      e.preventDefault();
      setPosition({
        x: e.touches[0].clientX - startPos.x,
        y: e.touches[0].clientY - startPos.y,
      });
    }
  };

  const handleTouchEnd = (e) => {
    if (e.touches.length < 2) {
      setLastDist(null);
    }
    if (e.touches.length === 0) {
      setIsPanning(false);
      if (scale <= 1) {
        setPosition({ x: 0, y: 0 });
      }
    }
  };

  return (
    <div className="relative overflow-hidden rounded-xl border border-slate-800 bg-slate-950 p-2 select-none touch-none">
      {/* Top Controls Overlay */}
      <div className="absolute top-3 right-3 z-10 flex items-center gap-1 rounded-lg bg-slate-900/90 border border-slate-700/80 p-1 shadow-md backdrop-blur">
        <button
          type="button"
          onClick={handleZoomIn}
          className="p-1.5 text-slate-300 hover:text-white rounded hover:bg-slate-800 transition"
          title="Zoom In"
        >
          <ZoomIn className="h-4 w-4" />
        </button>
        <button
          type="button"
          onClick={handleZoomOut}
          disabled={scale <= 1}
          className="p-1.5 text-slate-300 hover:text-white rounded hover:bg-slate-800 disabled:opacity-40 transition"
          title="Zoom Out"
        >
          <ZoomOut className="h-4 w-4" />
        </button>
        {scale > 1 && (
          <button
            type="button"
            onClick={resetZoom}
            className="p-1.5 text-brand-400 hover:text-brand-300 rounded hover:bg-slate-800 transition"
            title="Reset Zoom (1x)"
          >
            <RotateCcw className="h-4 w-4" />
          </button>
        )}
        <span className="px-1.5 text-[11px] font-mono text-slate-400">
          {scale.toFixed(1)}x
        </span>
      </div>

      {/* Touch Pan/Zoom Container */}
      <div
        ref={containerRef}
        onTouchStart={handleTouchStart}
        onTouchMove={handleTouchMove}
        onTouchEnd={handleTouchEnd}
        className="flex items-center justify-center min-h-[220px] max-h-[420px] w-full overflow-hidden cursor-grab active:cursor-grabbing"
      >
        <img
          src={src}
          alt={alt}
          draggable={false}
          className="max-h-full max-w-full object-contain transition-transform duration-75 ease-out"
          style={{
            transform: `translate(${position.x}px, ${position.y}px) scale(${scale})`,
            transformOrigin: 'center center',
          }}
        />
      </div>

      <div className="mt-1 flex items-center justify-between px-2 text-[11px] text-slate-500">
        <span>{title}</span>
        <span>Pinch or double-tap to zoom diagram</span>
      </div>
    </div>
  );
}
