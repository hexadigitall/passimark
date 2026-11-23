import React, { useState } from 'react';
import { GitBranch, Link2, Plus, RotateCcw, Trash2, Unlink } from 'lucide-react';

export default function QuestionCreateTree({
  payload = {},
  value = [],
  onChange,
  disabled = false,
}) {
  const rootLabel = payload.root_label || 'Root Architecture';
  const availableNodes = payload.available_nodes || [];

  // Normalize value to array of edges: [{ parent: string, child: string }]
  const edges = Array.isArray(value)
    ? value
    : typeof value === 'object' && value !== null
    ? Object.entries(value).map(([child, parent]) => ({ parent, child }))
    : [];

  const [selectedChildId, setSelectedChildId] = useState('');
  const [selectedParentId, setSelectedParentId] = useState('root');

  // Map of nodes for quick access
  const nodeMap = new Map(availableNodes.map((n) => [n.id, n.label]));
  nodeMap.set('root', rootLabel);

  // Set of child IDs already linked
  const linkedChildIds = new Set(edges.map((e) => e.child));
  const unlinkedNodes = availableNodes.filter((n) => !linkedChildIds.has(n.id));

  // Potential parents are root + any nodes that have already been attached (or all nodes)
  const availableParents = [
    { id: 'root', label: `${rootLabel} (Root)` },
    ...availableNodes.filter((n) => linkedChildIds.has(n.id)).map((n) => ({
      id: n.id,
      label: n.label,
    })),
  ];

  const handleAddEdge = () => {
    if (disabled || !selectedChildId || !selectedParentId) return;
    if (selectedChildId === selectedParentId) return;

    // Prevent duplicate child assignment (each node has 1 parent in a tree)
    const nextEdges = edges.filter((e) => e.child !== selectedChildId);
    nextEdges.push({ parent: selectedParentId, child: selectedChildId });

    onChange(nextEdges);
    setSelectedChildId('');
  };

  const handleRemoveEdge = (childId) => {
    if (disabled) return;
    // Removing an edge also removes any descendant nodes attached to this child
    const toRemove = new Set([childId]);
    let changed = true;
    while (changed) {
      changed = false;
      for (const e of edges) {
        if (toRemove.has(e.parent) && !toRemove.has(e.child)) {
          toRemove.add(e.child);
          changed = true;
        }
      }
    }

    const nextEdges = edges.filter((e) => !toRemove.has(e.child));
    onChange(nextEdges);
  };

  const handleReset = () => {
    if (disabled) return;
    onChange([]);
  };

  // Build recursive tree representation for visual rendering
  const renderSubtree = (parentId, depth = 0) => {
    const childEdges = edges.filter((e) => e.parent === parentId);
    if (childEdges.length === 0) return null;

    return (
      <div className={`space-y-2 ${depth > 0 ? 'ml-6 border-l border-slate-700 pl-4 mt-2' : ''}`}>
        {childEdges.map((edge) => {
          const childLabel = nodeMap.get(edge.child) || edge.child;

          return (
            <div key={edge.child} className="space-y-1">
              <div className="flex items-center justify-between rounded-lg border border-slate-700 bg-slate-800/90 px-3 py-2 text-sm shadow-sm">
                <div className="flex items-center gap-2">
                  <span className="text-slate-500 font-mono text-xs">└─</span>
                  <span className="font-medium text-slate-100">{childLabel}</span>
                  <span className="rounded bg-brand-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-brand-300">
                    under {nodeMap.get(edge.parent)}
                  </span>
                </div>
                <button
                  type="button"
                  onClick={() => handleRemoveEdge(edge.child)}
                  disabled={disabled}
                  className="rounded p-1 text-slate-400 hover:bg-red-500/20 hover:text-red-300 transition"
                  title="Detach node"
                >
                  <Unlink className="h-3.5 w-3.5" />
                </button>
              </div>
              {renderSubtree(edge.child, depth + 1)}
            </div>
          );
        })}
      </div>
    );
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between text-xs text-slate-400">
        <p>Construct the hierarchical architecture by connecting components to their parents.</p>
        {edges.length > 0 && !disabled && (
          <button
            type="button"
            onClick={handleReset}
            className="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-slate-100 transition"
          >
            <RotateCcw className="h-3 w-3" /> Reset Tree
          </button>
        )}
      </div>

      <div className="grid gap-6 lg:grid-cols-12">
        {/* Node Linker Panel */}
        <div className="lg:col-span-5 space-y-4">
          <section className="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400">
              Attach Component
            </h3>

            <div className="mt-4 space-y-3">
              <div>
                <label className="text-xs text-slate-400">Select Child Node:</label>
                <select
                  value={selectedChildId}
                  onChange={(e) => setSelectedChildId(e.target.value)}
                  disabled={disabled}
                  className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-brand-500 focus:outline-none"
                >
                  <option value="" disabled className="text-slate-500">
                    -- Choose component ({unlinkedNodes.length} available) --
                  </option>
                  {unlinkedNodes.map((n) => (
                    <option key={n.id} value={n.id}>
                      {n.label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="text-xs text-slate-400">Attach Under Parent:</label>
                <select
                  value={selectedParentId}
                  onChange={(e) => setSelectedParentId(e.target.value)}
                  disabled={disabled}
                  className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-brand-500 focus:outline-none"
                >
                  {availableParents.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.label}
                    </option>
                  ))}
                </select>
              </div>

              <button
                type="button"
                onClick={handleAddEdge}
                disabled={disabled || !selectedChildId || !selectedParentId}
                className="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500/15 border border-brand-500/40 px-4 py-2 text-sm font-semibold text-brand-300 transition hover:bg-brand-500/25 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <Plus className="h-4 w-4" /> Link Component
              </button>
            </div>
          </section>

          {/* Unlinked Items Summary */}
          <div className="rounded-xl border border-slate-800 bg-slate-900/40 p-4 text-xs text-slate-400">
            <span className="font-semibold text-slate-300">Unlinked Components: </span>
            {unlinkedNodes.length === 0 ? (
              <span className="text-brand-300">All components connected.</span>
            ) : (
              <span>{unlinkedNodes.map((n) => n.label).join(', ')}</span>
            )}
          </div>
        </div>

        {/* Tree Visualization Panel */}
        <div className="lg:col-span-7">
          <section className="rounded-xl border border-slate-800 bg-slate-900/60 p-5">
            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-4">
              Architecture Hierarchy
            </h3>

            {/* Root Node Banner */}
            <div className="flex items-center gap-3 rounded-lg border border-brand-500/50 bg-brand-500/10 p-3 shadow-sm">
              <GitBranch className="h-5 w-5 text-brand-300" />
              <div>
                <p className="text-xs font-bold uppercase tracking-wider text-brand-300">Root Node</p>
                <p className="text-sm font-semibold text-slate-100">{rootLabel}</p>
              </div>
            </div>

            {/* Tree Branches */}
            <div className="mt-4">
              {edges.length === 0 ? (
                <div className="rounded-lg border border-dashed border-slate-800 py-10 text-center text-xs text-slate-500">
                  No components linked yet. Select a child component and attach it under Root or a parent node.
                </div>
              ) : (
                renderSubtree('root')
              )}
            </div>
          </section>
        </div>
      </div>
    </div>
  );
}
