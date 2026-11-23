import React, { useRef } from 'react';
import { Crosshair, RotateCcw } from 'lucide-react';

export default function QuestionHotspot({
  payload = {},
  value = null,
  onChange,
  disabled = false,
}) {
  const imageRef = useRef(null);
  const imageSrc = payload.image_src || '';

  const handleClick = (e) => {
    if (disabled || !imageRef.current) return;
    const rect = imageRef.current.getBoundingClientRect();
    const clickX = e.clientX - rect.left;
    const clickY = e.clientY - rect.top;

    // Normalised coordinate relative to natural image dimensions if available
    const naturalWidth = imageRef.current.naturalWidth || rect.width;
    const naturalHeight = imageRef.current.naturalHeight || rect.height;

    const scaleX = naturalWidth / rect.width;
    const scaleY = naturalHeight / rect.height;

    const mappedX = Math.round(clickX * scaleX);
    const mappedY = Math.round(clickY * scaleY);

    onChange({
      x: mappedX,
      y: mappedY,
      client_x_pct: (clickX / rect.width) * 100,
      client_y_pct: (clickY / rect.height) * 100,
    });
  };

  const handleReset = () => {
    if (disabled) return;
    onChange(null);
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between text-xs text-slate-400">
        <p>Click or tap on the diagram to select your target coordinate.</p>
        {value && !disabled && (
          <button
            type="button"
            onClick={handleReset}
            className="inline-flex items-center gap-1 text-slate-400 hover:text-white"
          >
            <RotateCcw className="h-3 w-3" /> Clear mark
          </button>
        )}
      </div>

      <div className="relative inline-block overflow-hidden rounded-xl border border-slate-800 bg-slate-950 shadow-inner">
        {imageSrc ? (
          <img
            ref={imageRef}
            src={imageSrc}
            alt="Exhibit Diagram"
            onClick={handleClick}
            className={`max-h-[500px] w-auto max-w-full cursor-crosshair select-none ${
              disabled ? 'pointer-events-none' : ''
            }`}
          />
        ) : (
          <div
            ref={imageRef}
            onClick={handleClick}
            className="flex h-64 w-96 cursor-crosshair items-center justify-center border border-dashed border-slate-700 bg-slate-900 text-xs text-slate-500"
          >
            Exhibit diagram target area (Click to mark)
          </div>
        )}

        {/* Selected target marker pin */}
        {value && (
          <div
            className="pointer-events-none absolute -translate-x-1/2 -translate-y-1/2 transform text-brand-400 drop-shadow-md transition-all duration-150"
            style={{
              left: value.client_x_pct !== undefined ? `${value.client_x_pct}%` : `${value.x}px`,
              top: value.client_y_pct !== undefined ? `${value.client_y_pct}%` : `${value.y}px`,
            }}
          >
            <Crosshair className="h-6 w-6 animate-pulse" />
          </div>
        )}
      </div>

      {value && (
        <div className="flex items-center gap-2 text-xs text-slate-400 font-mono">
          <Crosshair className="h-3.5 w-3.5 text-brand-400" />
          <span>
            Selected coordinate: ({value.x}, {value.y})
          </span>
        </div>
      )}
    </div>
  );
}
