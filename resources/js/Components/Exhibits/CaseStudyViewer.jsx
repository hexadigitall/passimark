import React, { useState } from 'react';
import PinchZoomDiagram from './PinchZoomDiagram';
import { TauriBridge } from '../../Services/Desktop/TauriBridge';
import {
  BookOpen,
  Copy,
  Check,
  ExternalLink,
  FileText,
  Image as ImageIcon,
  Maximize2,
  Minimize2,
  Network,
  PenTool,
  Shield,
  Terminal,
  ZoomIn,
} from 'lucide-react';

export default function CaseStudyViewer({
  scenario = null,
  exhibits = [],
  notes = '',
  onNotesChange,
  className = '',
}) {
  // Normalize tabs from scenario or exhibits
  const scenarioTabs = scenario?.tabs || [];
  const exhibitTabs = exhibits.map((ex, idx) => ({
    id: `exhibit-${idx}`,
    title: ex.title || `Exhibit ${idx + 1}`,
    type: ex.type || 'image',
    content: ex.content || '',
    src: ex.src || '',
  }));

  const allTabs = [
    ...(scenario?.title
      ? [
          {
            id: 'scenario-overview',
            title: scenario.title || 'Case Overview',
            type: 'overview',
            content: scenario.overview || scenario.content || '',
          },
        ]
      : []),
    ...scenarioTabs.map((tab, idx) => ({
      id: `scenario-tab-${idx}`,
      title: tab.title || `Tab ${idx + 1}`,
      type: tab.type || 'text',
      content: tab.content || '',
      src: tab.src || '',
    })),
    ...exhibitTabs,
    {
      id: 'candidate-notes',
      title: 'Scratch Notes',
      type: 'notes',
    },
  ];

  const [activeTabId, setActiveTabId] = useState(allTabs[0]?.id || 'candidate-notes');
  const [copied, setCopied] = useState(false);
  const [zoomedImage, setZoomedImage] = useState(null);

  if (allTabs.length === 0) return null;

  const activeTab = allTabs.find((t) => t.id === activeTabId) || allTabs[0];

  const handleCopy = (text) => {
    navigator.clipboard?.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const getTabIcon = (tab) => {
    switch (tab.type) {
      case 'overview':
        return <BookOpen className="h-3.5 w-3.5" />;
      case 'image':
      case 'topology':
        return <Network className="h-3.5 w-3.5" />;
      case 'code':
      case 'logs':
        return <Terminal className="h-3.5 w-3.5" />;
      case 'policy':
        return <Shield className="h-3.5 w-3.5" />;
      case 'notes':
        return <PenTool className="h-3.5 w-3.5" />;
      default:
        return <FileText className="h-3.5 w-3.5" />;
    }
  };

  return (
    <div className={`rounded-xl border border-slate-700 bg-slate-900 overflow-hidden shadow-lg ${className}`}>
      {/* Header and Tabs */}
      <div className="border-b border-slate-700 bg-slate-950/80 px-4 pt-3">
        <div className="flex items-center justify-between pb-2">
          <div className="flex items-center gap-2">
            <span className="text-[11px] font-bold uppercase tracking-wider text-brand-300">
              Case Study Exhibit Dossier
            </span>
            {activeTab.type === 'notes' && (
              <span className="text-[11px] text-slate-500">Auto-saved to session</span>
            )}
          </div>

          <button
            type="button"
            onClick={() => {
              if (activeTab.type === 'notes') {
                TauriBridge.detachScratchpad(notes, onNotesChange);
              } else {
                TauriBridge.detachExhibit(activeTab);
              }
            }}
            className="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-semibold text-slate-400 hover:bg-slate-800 hover:text-brand-300 transition"
            title="Detach exhibit to a secondary floating desktop window (Dual-Monitor setup)"
          >
            <ExternalLink className="h-3 w-3" />
            <span className="hidden sm:inline">Detach to Window</span>
          </button>
        </div>

        <div className="flex space-x-1 overflow-x-auto scrollbar-none py-1">
          {allTabs.map((tab) => {
            const isActive = tab.id === activeTabId;

            return (
              <button
                key={tab.id}
                type="button"
                onClick={() => setActiveTabId(tab.id)}
                className={`flex items-center gap-1.5 whitespace-nowrap rounded-t-lg px-3.5 py-2 text-xs font-semibold transition border-b-2 ${
                  isActive
                    ? 'border-brand-500 bg-slate-850 text-slate-100 shadow-sm'
                    : 'border-transparent text-slate-400 hover:border-slate-600 hover:text-slate-200'
                }`}
              >
                {getTabIcon(tab)}
                <span>{tab.title}</span>
              </button>
            );
          })}
        </div>
      </div>

      {/* Exhibit Content Body */}
      <div className="p-5 min-h-60 max-h-[32rem] overflow-y-auto">
        {activeTab.type === 'notes' ? (
          <div className="space-y-2">
            <p className="text-xs text-slate-400">
              Use this scratchpad to record notes, calculate subnets, or track case facts. Notes are retained across questions within this assessment.
            </p>
            <textarea
              value={notes}
              onChange={(e) => onNotesChange && onNotesChange(e.target.value)}
              placeholder="Jot down notes or calculations here..."
              rows={10}
              className="w-full rounded-lg border border-slate-700 bg-slate-950 p-3 text-sm font-mono text-slate-200 placeholder-slate-600 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
            />
          </div>
        ) : activeTab.type === 'image' || activeTab.src ? (
          <div className="space-y-3">
            <PinchZoomDiagram
              src={activeTab.src}
              alt={activeTab.title}
              title={activeTab.title}
            />
            {activeTab.content && (
              <p className="text-xs text-slate-400 leading-relaxed">{activeTab.content}</p>
            )}
          </div>
        ) : activeTab.type === 'code' || activeTab.type === 'logs' ? (
          <div className="space-y-2">
            <div className="flex items-center justify-between text-xs text-slate-400">
              <span className="font-mono">Output / Log Stream</span>
              <button
                type="button"
                onClick={() => handleCopy(activeTab.content)}
                className="inline-flex items-center gap-1 rounded bg-slate-800 px-2 py-1 text-slate-300 hover:text-slate-100 transition"
              >
                {copied ? <Check className="h-3 w-3 text-brand-300" /> : <Copy className="h-3 w-3" />}
                <span>{copied ? 'Copied' : 'Copy'}</span>
              </button>
            </div>
            <pre className="overflow-x-auto rounded-lg border border-slate-800 bg-slate-950 p-4 font-mono text-xs leading-relaxed text-slate-300">
              {activeTab.content}
            </pre>
          </div>
        ) : (
          <div className="prose prose-invert max-w-none text-sm leading-relaxed text-slate-300 space-y-3">
            {activeTab.content ? (
              <div className="whitespace-pre-wrap">{activeTab.content}</div>
            ) : (
              <p className="text-slate-500 italic">No text provided for this tab.</p>
            )}
          </div>
        )}
      </div>

      {/* Fullscreen Image Lightbox Modal */}
      {zoomedImage && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
          onClick={() => setZoomedImage(null)}
        >
          <div className="relative max-h-[90vh] max-w-[90vw] overflow-auto rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-2xl">
            <button
              type="button"
              onClick={() => setZoomedImage(null)}
              className="absolute right-3 top-3 rounded-full bg-slate-800/90 p-2 text-slate-300 hover:text-slate-100 transition"
            >
              <Minimize2 className="h-5 w-5" />
            </button>
            <img src={zoomedImage} alt="Zoomed Exhibit" className="max-h-[85vh] w-auto rounded object-contain" />
          </div>
        </div>
      )}
    </div>
  );
}
