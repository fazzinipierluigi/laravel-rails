@php
$_editorLocale = app()->getLocale();
$_editorTrans  = trans('laravel-rails::editor') ?: [];
if (!is_array($_editorTrans)) $_editorTrans = [];
@endphp
<!DOCTYPE html>
<html lang="{{ $_editorLocale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Rails — {{ $workflowSlug }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litegraph.js@0.7.18/css/litegraph.css">
    <link rel="stylesheet" href="{{ route('laravel-rails.assets', ['file' => 'jsonlogic_ui.css']) }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── Root Layout: 3 columns ── */
        #rail-editor {
            display: flex;
            width: 100%;
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            overflow: hidden;
        }

        /* ── Left Panel (palette + tools) ── */
        #rail-left {
            width: 180px; min-width: 180px;
            height: 100%;
            background: #0f172a;
            border-right: 1px solid #1e293b;
            display: flex; flex-direction: column;
            overflow: hidden;
        }
        #rail-left-header {
            padding: 14px 12px 10px;
            font-size: 11px; font-weight: 700; color: #3b82f6;
            letter-spacing: .1em; text-transform: uppercase;
            border-bottom: 1px solid #1e293b;
            flex-shrink: 0;
        }
        #rail-left-body {
            flex: 1; overflow-y: auto; padding: 10px 8px;
        }
        .lp-section {
            font-size: 10px; font-weight: 700; color: #475569;
            text-transform: uppercase; letter-spacing: .08em;
            margin: 14px 0 6px 4px;
        }
        .lp-node-item {
            display: flex; align-items: center; gap: 7px;
            padding: 8px 10px;
            background: #1e293b; border: 1px solid #334155; border-radius: 6px;
            cursor: grab; margin-bottom: 5px;
            font-size: 12px; color: #cbd5e1; user-select: none;
            transition: border-color .15s, background .15s;
        }
        .lp-node-item:hover { border-color: #3b82f6; color: #93c5fd; background: #1e3a5f; }
        .lp-node-item:active { cursor: grabbing; }
        .lp-node-icon { font-size: 14px; }
        .lp-btn {
            display: block; width: 100%; margin-bottom: 5px;
            padding: 7px 10px; border: none; border-radius: 6px;
            font-size: 12px; font-weight: 600; cursor: pointer;
            transition: opacity .15s; text-align: left;
        }
        .lp-btn:hover { opacity: .85; }
        .lp-btn-primary  { background: #22c55e; color: #fff; }
        .lp-btn-secondary{ background: #1e293b; color: #cbd5e1; border: 1px solid #334155; }
        .lp-btn-vars     { background: #7c3aed; color: #ede9fe; border: 1px solid #6d28d9; }

        /* ── Canvas ── */
        #rail-canvas-wrap {
            flex: 1; position: relative; overflow: hidden;
        }
        #rail-canvas {
            display: block; width: 100%; height: 100%;
        }

        /* ── Status badge ── */
        #rail-status {
            position: absolute; top: 16px; left: 50%; transform: translateX(-50%);
            background: #1e293b;
            border: 1px solid #475569; border-radius: 8px;
            padding: 10px 20px; font-size: 14px; font-weight: 600;
            color: #94a3b8; z-index: 100;
            box-shadow: 0 4px 24px rgba(0,0,0,.6);
            transition: opacity .3s; pointer-events: none;
            white-space: nowrap;
        }
        #rail-status.ok  { color: #22c55e; border-color: #166534; background: #052e16; }
        #rail-status.err { color: #fca5a5; border-color: #ef4444; background: #450a0a;
                           font-size: 15px; pointer-events: auto; }
        #rail-status.hidden { opacity: 0; }

        /* ── Right Panel ── */
        #rail-panel {
            width: 380px; min-width: 380px; height: 100%;
            background: #1e293b; border-left: 1px solid #334155;
            display: flex; flex-direction: column; overflow: hidden;
        }
        #rail-panel-header {
            padding: 16px; border-bottom: 1px solid #334155;
            background: #0f172a;
            font-size: 13px; font-weight: 700; color: #94a3b8;
            letter-spacing: .05em; text-transform: uppercase;
        }
        #rail-panel-body {
            flex: 1; overflow-y: auto; padding: 16px;
        }
        #rail-panel-body p.placeholder {
            color: #64748b; font-size: 13px; text-align: center; margin-top: 40px;
        }

        /* ── Form Controls ── */
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
        .field input[type=text], .field textarea, .field select {
            width: 100%; padding: 7px 10px;
            background: #0f172a; border: 1px solid #334155; border-radius: 6px;
            color: #e2e8f0; font-size: 13px; transition: border-color .15s;
        }
        .field input[type=text]:focus, .field textarea:focus, .field select:focus {
            outline: none; border-color: #3b82f6;
        }
        .field textarea { resize: vertical; min-height: 60px; font-family: 'Courier New', monospace; font-size: 12px; }
        .field-check { display: flex; align-items: center; gap: 8px; }
        .field-check input { width: 16px; height: 16px; cursor: pointer; accent-color: #3b82f6; }
        .field-check label { font-size: 13px; color: #cbd5e1; text-transform: none; letter-spacing: 0; margin: 0; }

        .section-title {
            font-size: 11px; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: .08em;
            margin: 18px 0 10px; padding-bottom: 6px;
            border-bottom: 1px solid #334155;
        }

        /* ── Buttons ── */
        .btn { padding: 6px 12px; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: opacity .15s; }
        .btn:hover { opacity: .85; }
        .btn-primary   { background: #3b82f6; color: #fff; }
        .btn-success   { background: #22c55e; color: #fff; }
        .btn-danger    { background: #ef4444; color: #fff; }
        .btn-secondary { background: #475569; color: #e2e8f0; }

        /* ── Action item ── */
        .action-item {
            background: #0f172a; border: 1px solid #334155; border-radius: 6px;
            padding: 9px 10px; margin-bottom: 8px;
        }
        .action-item-row { display: flex; align-items: center; gap: 8px; }
        .action-item select { flex: 1; background: #1e293b; border: 1px solid #475569; border-radius: 4px; padding: 4px 6px; color: #e2e8f0; font-size: 12px; }
        .action-item .btn-remove { padding: 3px 7px; font-size: 11px; background: #7f1d1d; color: #fca5a5; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0; }
        .action-item .btn-remove:hover { background: #ef4444; color: #fff; }
        .action-config-field { margin-top: 6px; }
        .action-config-field label { display: block; font-size: 10px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 3px; }
        .action-config-field input, .action-config-field textarea {
            width: 100%; padding: 4px 8px; background: #1e293b; border: 1px solid #334155; border-radius: 4px;
            color: #e2e8f0; font-size: 12px; outline: none;
        }
        .action-config-field input:focus, .action-config-field textarea:focus { border-color: #3b82f6; }
        .action-config-field textarea { resize: vertical; min-height: 44px; font-family: monospace; font-size: 11px; }

        /* ── Priority row ── */
        .priority-row { display: flex; align-items: center; gap: 6px; }
        .priority-badge { background: #1e3a5f; color: #93c5fd; border: 1px solid #1d4ed8; border-radius: 4px; padding: 2px 8px; font-size: 12px; font-weight: 700; min-width: 28px; text-align: center; }
        .priority-btn { background: #1e293b; border: 1px solid #334155; color: #94a3b8; border-radius: 4px; padding: 2px 7px; font-size: 13px; cursor: pointer; line-height: 1.4; }
        .priority-btn:hover { border-color: #3b82f6; color: #93c5fd; }

        hr { border: none; border-top: 1px solid #334155; margin: 14px 0; }

        /* ── Conditional branch ── */
        .cond-branch {
            display: flex; align-items: center; gap: 5px; margin-bottom: 5px;
            padding: 5px 7px; background: #0f172a; border: 1px solid #334155; border-radius: 5px;
        }
        .cond-branch-num { font-size: 10px; font-weight: 700; color: #fbbf24; background: #1c1100; border: 1px solid #78350f; border-radius: 3px; padding: 1px 5px; flex-shrink: 0; }
        .cond-branch input { flex: 1; padding: 4px 6px; background: #1e293b; border: 1px solid #475569; border-radius: 4px; color: #e2e8f0; font-size: 11px; }
        .cond-branch .btn-remove { padding: 3px 7px; font-size: 11px; background: #7f1d1d; color: #fca5a5; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0; }
        .cond-branch .cond-indicator { font-size: 10px; color: #22c55e; flex-shrink: 0; }
        .cond-branch .cond-indicator.empty { color: #475569; }

        /* ── jsonlogic_ui ── */
        .jl-editor-host { margin-top: 4px; }
        .field .jl-inp, .field .jl-sel { width: auto; padding: 4px 6px; font-size: 11px; }

        /* ── Collapsible section ── */
        .coll-header {
            display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none;
            font-size: 11px; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: .08em;
            margin: 18px 0 0; padding-bottom: 6px; border-bottom: 1px solid #334155;
        }
        .coll-header .coll-arrow { transition: transform .2s; }
        .coll-header.open .coll-arrow { transform: rotate(90deg); }
        .coll-body { display: none; padding-top: 8px; }
        .coll-body.open { display: block; }

        /* ── Form Builder Modal ── */
        #fb-modal { position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.75);display:flex;align-items:center;justify-content:center;backdrop-filter:blur(3px); }
        #fb-dialog { background:#1e293b;border:1px solid #334155;border-radius:12px;width:93vw;max-width:1280px;height:88vh;display:flex;flex-direction:column;overflow:hidden; }
        #fb-dialog-header { padding:14px 20px;border-bottom:1px solid #334155;display:flex;align-items:center;justify-content:space-between;flex-shrink:0; }
        #fb-dialog-body { flex:1;display:flex;overflow:hidden;min-height:0; }
        #fb-palette { width:160px;flex-shrink:0;border-right:1px solid #334155;padding:10px 8px;overflow-y:auto; }
        .fb-palette-item { background:#0f172a;border:1px solid #334155;border-radius:6px;padding:7px 10px;margin-bottom:5px;cursor:grab;font-size:12px;color:#cbd5e1;user-select:none;display:flex;align-items:center;gap:7px; }
        .fb-palette-item:hover { border-color:#3b82f6;color:#93c5fd; }
        #fb-canvas-col { flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0; }
        #fb-canvas-hint { padding:8px 14px;border-bottom:1px solid #1e293b;font-size:11px;color:#475569;flex-shrink:0; }
        #fb-canvas { flex:1;overflow-y:auto;padding:14px; }
        #fb-grid { display:grid;grid-template-columns:repeat(12,1fr);gap:8px;min-height:100px;padding:10px;border:2px dashed #334155;border-radius:8px;transition:border-color .15s; }
        #fb-grid.fb-drag-over { border-color:#3b82f6;background:rgba(59,130,246,.04); }
        #fb-grid-empty { grid-column:span 12;text-align:center;color:#475569;padding:32px 0;font-size:13px;pointer-events:none; }
        .fb-field-card { background:#0f172a;border:2px solid #334155;border-radius:6px;padding:7px 9px;cursor:pointer;position:relative;font-size:11px;transition:border-color .15s;user-select:none;overflow:hidden;min-height:58px; }
        .fb-field-card:hover { border-color:#475569; }
        .fb-field-card.fb-selected { border-color:#3b82f6;background:#0a1628; }
        .fb-field-card.fb-drag-src { opacity:.35;border-style:dashed; }
        .fb-field-card .fbc-type { font-size:9px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px; }
        .fb-field-card .fbc-label { color:#e2e8f0;font-weight:600;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
        .fb-field-card .fbc-name { color:#64748b;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px; }
        .fb-field-card .fbc-cols { position:absolute;bottom:4px;right:6px;font-size:9px;color:#334155;font-weight:700; }
        .fb-field-card .fbc-del { position:absolute;top:4px;right:4px;background:#7f1d1d;color:#fca5a5;border:none;border-radius:3px;padding:1px 5px;font-size:9px;cursor:pointer;display:none;line-height:1.4; }
        .fb-field-card:hover .fbc-del { display:block; }
        .fb-field-card .fbc-req { color:#f87171; }
        #fb-props { width:280px;flex-shrink:0;border-left:1px solid #334155;overflow-y:auto;padding:12px; }
        .fb-prop-field { margin-bottom:9px; }
        .fb-prop-field label { display:block;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px; }
        .fb-prop-field input,.fb-prop-field select,.fb-prop-field textarea { width:100%;padding:5px 8px;background:#0f172a;border:1px solid #334155;border-radius:4px;color:#e2e8f0;font-size:12px;outline:none; }
        .fb-prop-field input:focus,.fb-prop-field select:focus,.fb-prop-field textarea:focus { border-color:#3b82f6; }
        .fb-prop-field textarea { resize:vertical;min-height:52px;font-family:monospace;font-size:11px; }
        .fb-cols-grid { display:grid;grid-template-columns:repeat(12,1fr);gap:3px;margin-top:4px; }
        .fb-col-btn { background:#0f172a;border:1px solid #334155;border-radius:3px;padding:4px 0;text-align:center;font-size:10px;color:#64748b;cursor:pointer;transition:background .1s; }
        .fb-col-btn.fb-col-active { background:#1e40af;border-color:#3b82f6;color:#fff; }
        .fb-prop-sep { font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin:12px 0 8px;padding-bottom:5px;border-bottom:1px solid #1e293b; }
        /* Options table */
        .fb-opt-row { display:grid;grid-template-columns:1fr 1fr auto;gap:4px;margin-bottom:4px;align-items:center; }
        .fb-opt-row input { padding:4px 6px;background:#0f172a;border:1px solid #334155;border-radius:3px;color:#e2e8f0;font-size:11px;outline:none; }
        .fb-opt-row input:focus { border-color:#3b82f6; }
        .fb-opt-del { background:#7f1d1d;color:#fca5a5;border:none;border-radius:3px;padding:3px 6px;font-size:10px;cursor:pointer;line-height:1; }
        #fb-dialog-footer { padding:10px 20px;border-top:1px solid #334155;display:flex;gap:10px;align-items:center;flex-shrink:0; }

        /* ── Variables Modal ── */
        #vars-modal { position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.75);display:flex;align-items:center;justify-content:center;backdrop-filter:blur(3px); }
        #vars-dialog { background:#1e293b;border:1px solid #334155;border-radius:12px;width:600px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden; }
        #vars-dialog-header { padding:16px 20px;border-bottom:1px solid #334155;display:flex;align-items:center;justify-content:space-between;flex-shrink:0; }
        #vars-dialog-body { flex:1;overflow-y:auto;padding:16px 20px; }
        #vars-dialog-footer { padding:12px 20px;border-top:1px solid #334155;display:flex;gap:10px;align-items:center;flex-shrink:0; }
        .var-row { display:grid;grid-template-columns:1fr 1fr auto auto auto;gap:6px;margin-bottom:8px;align-items:center; }
        .var-row input,.var-row select { padding:5px 8px;background:#0f172a;border:1px solid #334155;border-radius:4px;color:#e2e8f0;font-size:12px;outline:none;width:100%; }
        .var-row input:focus,.var-row select:focus { border-color:#3b82f6; }
        .var-row-del { background:#7f1d1d;color:#fca5a5;border:none;border-radius:4px;padding:5px 8px;font-size:11px;cursor:pointer; }
    </style>
</head>
<body>
<div id="rail-editor">

    {{-- ── Left panel ── --}}
    <div id="rail-left">
        <div id="rail-left-header">⚡ Rails</div>
        <div id="rail-left-body">
            <div class="lp-section" id="lp-nodes-title">Nodi</div>
            <div class="lp-node-item" draggable="true" data-ntype="state">
                <span class="lp-node-icon">◻</span> <span id="lp-state-label">Stato</span>
            </div>
            <div class="lp-node-item" draggable="true" data-ntype="conditional" style="color:#fde68a;border-color:#78350f;">
                <span class="lp-node-icon">◇</span> <span id="lp-cond-label">Condizionale</span>
            </div>

            <div class="lp-section" id="lp-tools-title">Strumenti</div>
            <button class="lp-btn lp-btn-primary" id="btn-save" onclick="RailEditor.save()">💾 Salva</button>
            <button class="lp-btn lp-btn-secondary" id="btn-fit" onclick="RailEditor.fitGraph()">⊡ Adatta</button>
            <button class="lp-btn lp-btn-vars" id="btn-vars" onclick="openVarsModal()">⚙ Variabili</button>
        </div>
    </div>

    {{-- ── Canvas ── --}}
    <div id="rail-canvas-wrap">
        <canvas id="rail-canvas"></canvas>
        <div id="rail-status">Caricamento...</div>
    </div>

    {{-- ── Right panel ── --}}
    <div id="rail-panel">
        <div id="rail-panel-header">Proprietà</div>
        <div id="rail-panel-body">
            <p class="placeholder">Seleziona un nodo o una connessione per modificarne le proprietà.</p>
        </div>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="{{ route('laravel-rails.assets', ['file' => 'jsonlogic_ui.js']) }}"></script>
<script src="https://cdn.jsdelivr.net/npm/litegraph.js@0.7.18/build/litegraph.js"></script>
<script>
(function () {
    'use strict';

    // ── i18n ──────────────────────────────────────────────────────────────────
    const TRANS = @json($_editorTrans);
    function t(key, fallback) {
        return (TRANS && TRANS[key] !== undefined) ? TRANS[key] : (fallback !== undefined ? fallback : key);
    }

    // Apply translations to static DOM elements
    document.getElementById('lp-nodes-title').textContent  = t('nodes_palette', 'Nodi');
    document.getElementById('lp-tools-title').textContent  = t('tools', 'Strumenti');
    document.getElementById('lp-state-label').textContent  = t('add_state', 'Stato');
    document.getElementById('lp-cond-label').textContent   = t('add_conditional', 'Condizionale');
    document.getElementById('btn-save').textContent        = '💾 ' + t('save', 'Salva');
    document.getElementById('btn-fit').textContent         = '⊡ ' + t('fit_graph', 'Adatta');
    document.getElementById('btn-vars').textContent        = '⚙ ' + t('workflow_vars', 'Variabili');
    document.getElementById('rail-panel-header').textContent = t('properties', 'Proprietà');

    // ── Constants ─────────────────────────────────────────────────────────────
    const WORKFLOW_SLUG   = @json($workflowSlug);
    const API_URL         = @json(route('laravel-rails.workflow.show', ['slug' => $workflowSlug]));
    const API_ACTIONS_URL = @json(route('laravel-rails.registered-actions'));
    let   csrfToken       = @json(csrf_token());

    // ── State ─────────────────────────────────────────────────────────────────
    const transitionMeta = {};  // linkId → meta
    let registeredActions = [];
    let workflowVars      = [];  // workflow-level variable declarations
    let selectedNode      = null;
    let selectedLinkId    = null;
    let _wpDrag           = null; // {linkId, idx} while dragging a waypoint handle

    // ── Action list registry (for config panel callbacks) ─────────────────────
    const _actionRegs = {};
    let   _actionRegId = 0;

    function regActionList(getActions, rerender, removeFn) {
        const id = ++_actionRegId;
        _actionRegs[id] = { getActions, rerender, removeFn };
        return id;
    }

    // ── Status ────────────────────────────────────────────────────────────────
    let _statusTimer = null;
    function setStatus(msg, type = '') {
        const el = document.getElementById('rail-status');
        el.textContent = msg;
        el.className = type;
        el.classList.remove('hidden');
        clearTimeout(_statusTimer);
        if (type === 'ok') {
            _statusTimer = setTimeout(() => el.classList.add('hidden'), 3000);
        }
    }

    // ── CSRF token refresh (keep session alive, avoid mismatch on long edits) ──
    const CSRF_PING_URL = @json(route('laravel-rails.csrf'));
    async function refreshCsrf() {
        try {
            const r = await fetch(CSRF_PING_URL, { headers: { 'Accept': 'application/json' } });
            if (r.ok) { const d = await r.json(); csrfToken = d.token; }
        } catch (_) {}
    }
    setInterval(refreshCsrf, 4 * 60 * 1000); // every 4 minutes

    // ── LiteGraph setup ───────────────────────────────────────────────────────
    LiteGraph.debug = false;
    LiteGraph.node_images_path = '';

    // ─── WorkflowStateNode ────────────────────────────────────────────────────
    // Perpendicular distance from point p=[x,y] to segment a→b (graph coords)
    function _distToSeg(p, a, b) {
        const dx = b[0]-a[0], dy = b[1]-a[1];
        const lenSq = dx*dx + dy*dy;
        if (lenSq === 0) return Math.hypot(p[0]-a[0], p[1]-a[1]);
        const t = Math.max(0, Math.min(1, ((p[0]-a[0])*dx + (p[1]-a[1])*dy) / lenSq));
        return Math.hypot(p[0]-(a[0]+t*dx), p[1]-(a[1]+t*dy));
    }

    // Wraps text into lines that fit maxWidth, returns array of strings
    function wrapText(ctx, text, maxWidth) {
        const words = (text || '').split(' ');
        const lines = [];
        let cur = '';
        for (const word of words) {
            const test = cur ? cur + ' ' + word : word;
            if (ctx.measureText(test).width > maxWidth && cur) {
                lines.push(cur);
                cur = word;
            } else {
                cur = test;
            }
        }
        if (cur) lines.push(cur);
        return lines.length ? lines : [''];
    }

    // NODE_SLOT_HEIGHT=20, slot y = (slotIndex+0.7)*20 → slot 0 at y=14
    // All custom content starts at SLOT_ZONE (below last slot) to avoid overlap.
    const STATE_SLOT_ZONE = 28; // safe clearance below slot labels
    const STATE_SEP_Y     = STATE_SLOT_ZONE + 2; // separator line y
    const CONTENT_TOP     = STATE_SEP_Y + 10;    // first content row y baseline
    const LINE_H          = 19;
    const BADGE_H         = 20;
    const PAD_X           = 14;
    const PAD_BOT         = 12;

    function WorkflowStateNode() {
        this.size     = [350, 88];
        this.addInput('in', 'flow');
        this.addOutput('out', 'flow');
        this.properties = {
            id: null, name: 'Nuovo Stato', code: '',
            slug: '', is_start: false, is_end: false,
            on_enter_actions: [], on_exit_actions: [],
            view_permissions: [], view_operator: 'OR',
        };
        this.color   = '#1e3a5f';
        this.bgColor = '#1e293b';
        this.shape   = LiteGraph.ROUND_SHAPE;
    }
    WorkflowStateNode.title = 'State';
    WorkflowStateNode.prototype.onExecute = function () {};

    WorkflowStateNode.prototype.onDrawForeground = function (ctx) {
        const p    = this.properties;
        const w    = this.size[0];
        const textW = w - PAD_X * 2;

        // ── measure ──────────────────────────────────────────────────────────────
        ctx.font = 'bold 14px sans-serif';
        const nameLines = wrapText(ctx, p.name || 'State', textW);
        const nameBlock = nameLines.length * LINE_H;
        const hasBadge  = p.is_start || p.is_end;

        // height: slot zone + sep + name + [code] + [badge] + bottom padding
        const neededH = CONTENT_TOP
                      + nameBlock
                      + (p.code   ? 4 + 14 : 0)
                      + (hasBadge ? 8 + BADGE_H : 0)
                      + PAD_BOT;
        if (this.size[1] !== neededH) this.size[1] = neededH;

        const h = this.size[1];

        // ── background tint + left accent bar ────────────────────────────────────
        if (p.is_start) {
            ctx.fillStyle = 'rgba(34,197,94,.12)';
            ctx.fillRect(0, 0, w, h);
            ctx.fillStyle = '#22c55e';
            ctx.fillRect(0, 0, 4, h);
        } else if (p.is_end) {
            ctx.fillStyle = 'rgba(239,68,68,.10)';
            ctx.fillRect(0, 0, w, h);
            ctx.fillStyle = '#ef4444';
            ctx.fillRect(0, 0, 4, h);
        }

        // ── separator line below slot zone ───────────────────────────────────────
        ctx.strokeStyle = '#334155';
        ctx.lineWidth   = 1;
        ctx.beginPath();
        ctx.moveTo(8, STATE_SEP_Y); ctx.lineTo(w - 8, STATE_SEP_Y);
        ctx.stroke();

        // ── state name (wrapped) ─────────────────────────────────────────────────
        ctx.font      = 'bold 14px sans-serif';
        ctx.fillStyle = p.is_start ? '#4ade80' : p.is_end ? '#f87171' : '#e2e8f0';
        nameLines.forEach((line, i) => ctx.fillText(line, PAD_X, CONTENT_TOP + LINE_H * i + LINE_H - 4));

        // ── code slug ────────────────────────────────────────────────────────────
        let cursorY = CONTENT_TOP + nameBlock;
        if (p.code) {
            cursorY += 4;
            ctx.font      = '11px monospace';
            ctx.fillStyle = '#64748b';
            ctx.fillText(p.code, PAD_X, cursorY + 12);
            cursorY += 14;
        }

        // ── START / END badge (below content, left-aligned) ──────────────────────
        if (hasBadge) {
            const badgeY = cursorY + 8;
            if (p.is_start) {
                ctx.fillStyle = '#166534';
                roundRect(ctx, PAD_X, badgeY, 52, BADGE_H, 4); ctx.fill();
                ctx.fillStyle = '#4ade80'; ctx.font = 'bold 10px sans-serif';
                ctx.fillText('● START', PAD_X + 7, badgeY + 13);
            } else if (p.is_end) {
                ctx.fillStyle = '#7f1d1d';
                roundRect(ctx, PAD_X, badgeY, 44, BADGE_H, 4); ctx.fill();
                ctx.fillStyle = '#fca5a5'; ctx.font = 'bold 10px sans-serif';
                ctx.fillText('● END', PAD_X + 7, badgeY + 13);
            }
        }
    };

    WorkflowStateNode.prototype.onSelected = function () {
        selectedNode = this; selectedLinkId = null;
        renderNodePanel(this);
    };
    WorkflowStateNode.prototype.onDeselected = function () {};

    LiteGraph.registerNodeType('workflow/state', WorkflowStateNode);

    // ─── WorkflowConditionalNode ──────────────────────────────────────────────
    function WorkflowConditionalNode() {
        this.addInput('in', 'flow');
        this.addOutput('Ramo 1', 'flow');
        this.addOutput('Ramo 2', 'flow');
        this.properties = {
            id: null, name: 'Condizione', code: '',
            slug: '', type: 'conditional',
            is_start: false, is_end: false,
            on_enter_actions: [], on_exit_actions: [],
            view_permissions: [], view_operator: 'OR',
        };
        this.color   = '#422006';
        this.bgColor = '#1c1100';
        this.shape   = LiteGraph.ROUND_SHAPE;
        this._resizeToSlots();
    }
    WorkflowConditionalNode.title = 'Conditional';

    WorkflowConditionalNode.prototype._resizeToSlots = function () {
        const slots = this.outputs ? this.outputs.length : 2;
        this.size = [230, 56 + slots * 24];
    };

    WorkflowConditionalNode.prototype.onExecute = function () {};

    WorkflowConditionalNode.prototype.onDrawForeground = function (ctx) {
        const p = this.properties;
        const cx = this.size[0] / 2;
        ctx.fillStyle = '#fbbf24';
        ctx.beginPath(); ctx.moveTo(cx,8); ctx.lineTo(cx+12,20); ctx.lineTo(cx,32); ctx.lineTo(cx-12,20); ctx.closePath(); ctx.fill();
        ctx.font = 'bold 13px sans-serif'; ctx.fillStyle = '#fde68a';
        ctx.fillText((p.name || 'Condizione').substring(0, 24), 8, 48);
        if (p.code) {
            ctx.font = '10px monospace'; ctx.fillStyle = '#92400e';
            ctx.fillText(p.code, 8, 60);
        }
    };

    WorkflowConditionalNode.prototype.onSelected = function () {
        selectedNode = this; selectedLinkId = null;
        renderConditionalNodePanel(this);
    };
    WorkflowConditionalNode.prototype.onDeselected = function () {};

    LiteGraph.registerNodeType('workflow/conditional', WorkflowConditionalNode);

    // ── Prototype patches — MUST run before new LGraphCanvas() ───────────────────
    //    bindEvents() captures these via .bind(this) at construction time.

    // renderLink: sharp-corner polyline through waypoints
    const _origRL = LGraphCanvas.prototype.renderLink;
    LGraphCanvas.prototype.renderLink = function (ctx, a, b, link, skip_border, flow, color, start_dir, end_dir, num_sublines) {
        if (!link || !transitionMeta?.[link.id]?.waypoints?.length) {
            return _origRL.call(this, ctx, a, b, link, skip_border, flow, color, start_dir, end_dir, num_sublines);
        }
        this.visible_links.push(link);
        if (!color) color = link.color || this.default_link_color;
        if (this.highlighted_links[link.id]) color = '#FFF';
        const wps  = transitionMeta[link.id].waypoints;
        const pts  = [a, ...wps.map(w => [w.x, w.y]), b];

        // arc-length midpoint → link._pos (keeps selection dot on path)
        let total = 0;
        const sL = [];
        for (let i = 0; i < pts.length - 1; i++) {
            const d = Math.hypot(pts[i+1][0]-pts[i][0], pts[i+1][1]-pts[i][1]);
            sL.push(d); total += d;
        }
        let half = total / 2, acc = 0, px = pts[0][0], py = pts[0][1];
        for (let i = 0; i < sL.length; i++) {
            if (acc + sL[i] >= half) {
                const t = (half - acc) / sL[i];
                px = pts[i][0] + (pts[i+1][0]-pts[i][0]) * t;
                py = pts[i][1] + (pts[i+1][1]-pts[i][1]) * t;
                break;
            }
            acc += sL[i];
        }
        if (!link._pos) link._pos = [0, 0];
        link._pos[0] = px; link._pos[1] = py;

        // sharp polyline (squared corners, handles lie exactly on path)
        const drawPts = () => {
            ctx.beginPath();
            ctx.moveTo(pts[0][0], pts[0][1]);
            for (let i = 1; i < pts.length; i++) ctx.lineTo(pts[i][0], pts[i][1]);
        };
        ctx.lineJoin = 'miter';
        if (this.render_connections_border && this.ds.scale > 0.6 && !skip_border) {
            drawPts(); ctx.lineWidth = this.connections_width + 4; ctx.strokeStyle = 'rgba(0,0,0,.5)'; ctx.stroke();
        }
        drawPts(); ctx.lineWidth = this.connections_width; ctx.strokeStyle = color; ctx.stroke();
    };

    // processMouseDown: waypoint hit-test first, then multi-select fix
    const _origPMD = LGraphCanvas.prototype.processMouseDown;
    LGraphCanvas.prototype.processMouseDown = function (e) {
        // adjustMouseEvent must run first so e.canvasX/Y are populated
        this.adjustMouseEvent(e);
        // ── Waypoint interaction ────────────────────────────────────────────────
        const hitR = 8 / Math.max(this.ds?.scale || 1, 0.1);
        for (const id in (this.graph?.links || {})) {
            const link = this.graph.links[id];
            if (!link || !transitionMeta?.[id]?.waypoints?.length) continue;
            const wps = transitionMeta[id].waypoints;
            for (let i = 0; i < wps.length; i++) {
                if (Math.hypot(wps[i].x - e.canvasX, wps[i].y - e.canvasY) < hitR) {
                    if (e.button === 2) {
                        wps.splice(i, 1);
                        this.setDirty(true, true);
                    } else {
                        _wpDrag = { linkId: id, idx: i };
                    }
                    return false;
                }
            }
        }
        // ── Multi-select drag fix ───────────────────────────────────────────────
        const prevSel   = this.selected_nodes ? Object.assign({}, this.selected_nodes) : {};
        const prevCount = Object.keys(prevSel).length;
        _origPMD.call(this, e);
        const nowKeys = Object.keys(this.selected_nodes || {});
        if (prevCount > 1 && nowKeys.length === 1 && prevSel[nowKeys[0]] !== undefined) {
            this.selected_nodes = Object.assign({}, prevSel);
            for (const id in prevSel) prevSel[id].is_selected = true;
        }
    };

    // processMouseMove: move waypoint during drag
    const _origPMM = LGraphCanvas.prototype.processMouseMove;
    LGraphCanvas.prototype.processMouseMove = function (e) {
        if (_wpDrag) {
            this.adjustMouseEvent(e);
            const wps = transitionMeta?.[_wpDrag.linkId]?.waypoints;
            if (wps?.[_wpDrag.idx]) { wps[_wpDrag.idx].x = e.canvasX; wps[_wpDrag.idx].y = e.canvasY; }
            this.setDirty(true, true);
            return false;
        }
        return _origPMM.call(this, e);
    };

    // processMouseUp: end waypoint drag
    const _origPMU = LGraphCanvas.prototype.processMouseUp;
    LGraphCanvas.prototype.processMouseUp = function (e) {
        if (_wpDrag) { _wpDrag = null; this.setDirty(true, true); return false; }
        return _origPMU.call(this, e);
    };

    // ─── Graph & Canvas ───────────────────────────────────────────────────────
    const graph  = new LGraph();
    const cvs    = document.getElementById('rail-canvas');
    const canvas = new LGraphCanvas(cvs, graph);

    canvas.background_image      = null;
    canvas.clear_background      = true;
    canvas.clear_background_color = '#0f172a';
    canvas.render_connection_arrows = false; // we draw our own midpoint arrows
    canvas.connections_width     = 2;
    canvas.default_link_color    = '#3b82f6';
    canvas.allow_searchbox       = false;

    // ── Polyfill: not present in litegraph 0.7.18 ────────────────────────────────
    LGraphCanvas.prototype.fitToContents = function () {
        const nodes = this.graph._nodes;
        if (!nodes.length) return;
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        for (const n of nodes) {
            if (n.pos[0] < minX) minX = n.pos[0];
            if (n.pos[1] < minY) minY = n.pos[1];
            if (n.pos[0] + n.size[0] > maxX) maxX = n.pos[0] + n.size[0];
            if (n.pos[1] + n.size[1] > maxY) maxY = n.pos[1] + n.size[1];
        }
        const pad = 60;
        minX -= pad; minY -= pad; maxX += pad; maxY += pad;
        const cw = this.canvas.width, ch = this.canvas.height;
        const scale = Math.min(cw / (maxX - minX), ch / (maxY - minY), 1.5);
        this.ds.scale = scale;
        this.ds.offset[0] = cw / scale / 2 - (minX + maxX) / 2;
        this.ds.offset[1] = ch / scale / 2 - (minY + maxY) / 2;
        this.setDirty(true, true);
    };

    // ── Direction arrows + waypoint handles ──────────────────────────────────
    // onDrawForeground is called inside the graph-space transform (pan/zoom applied)
    canvas.onDrawForeground = function (ctx) {
        if (!ctx) return;
        for (const id in graph.links) {
            const link = graph.links[id];
            if (!link) continue;
            const srcNode = graph.getNodeById(link.origin_id);
            const dstNode = graph.getNodeById(link.target_id);
            if (!srcNode || !dstNode) continue;
            const srcPos = srcNode.getConnectionPos(false, link.origin_slot);
            const dstPos = dstNode.getConnectionPos(true,  link.target_slot);
            if (!srcPos || !dstPos) continue;

            const wps = transitionMeta[id]?.waypoints || [];
            const pts = [srcPos, ...wps.map(w => [w.x, w.y]), dstPos];

            // find midpoint along path by arc-length
            let totalLen = 0;
            const segLens = [];
            for (let i = 0; i < pts.length - 1; i++) {
                const d = Math.hypot(pts[i+1][0]-pts[i][0], pts[i+1][1]-pts[i][1]);
                segLens.push(d); totalLen += d;
            }
            let target = totalLen / 2, acc = 0, mx = pts[0][0], my = pts[0][1], angle = 0;
            for (let i = 0; i < segLens.length; i++) {
                if (acc + segLens[i] >= target) {
                    const t = (target - acc) / segLens[i];
                    mx    = pts[i][0] + (pts[i+1][0]-pts[i][0]) * t;
                    my    = pts[i][1] + (pts[i+1][1]-pts[i][1]) * t;
                    angle = Math.atan2(pts[i+1][1]-pts[i][1], pts[i+1][0]-pts[i][0]);
                    break;
                }
                acc += segLens[i];
            }

            // direction arrow
            const sz = 9;
            ctx.save();
            ctx.translate(mx, my); ctx.rotate(angle);
            ctx.fillStyle = link.color || canvas.default_link_color || '#3b82f6';
            ctx.globalAlpha = 0.85;
            ctx.beginPath();
            ctx.moveTo(sz, 0); ctx.lineTo(-sz*.55, -sz*.5); ctx.lineTo(-sz*.55, sz*.5);
            ctx.closePath(); ctx.fill();
            ctx.restore();

            // waypoint handles — small diamonds (path passes exactly through them)
            const r = 4;
            for (const wp of wps) {
                ctx.save();
                ctx.translate(wp.x, wp.y); ctx.rotate(Math.PI / 4);
                ctx.beginPath();
                ctx.rect(-r, -r, r * 2, r * 2);
                ctx.fillStyle   = '#1e40af'; ctx.fill();
                ctx.strokeStyle = '#93c5fd'; ctx.lineWidth = 1; ctx.stroke();
                ctx.restore();
            }
        }
    };

    // ── Link metadata init ────────────────────────────────────────────────────
    graph.onConnectionChange = function () {
        for (const id in this.links) {
            if (!transitionMeta[id]) {
                transitionMeta[id] = {
                    label: null,
                    show_condition: null, execute_condition: null, exit_condition: null,
                    permission: null, redirect: null,
                    actions: [], form_type: null, form_data: null,
                    view_permissions: [], view_operator: 'OR',
                    advance_permissions: [], advance_operator: 'OR',
                };
            }
        }
        for (const id in transitionMeta) {
            if (!this.links[id]) delete transitionMeta[id];
        }
    };

    // ── Click on link → show link panel ───────────────────────────────────────
    canvas.showLinkMenu = function (link, e) {
        selectedLinkId = link.id;
        selectedNode   = null;
        renderLinkPanel(link);
        return false;
    };

    // ─────────────────────────────────────────────────────────────────────────
    // Panel helpers
    // ─────────────────────────────────────────────────────────────────────────

    function panel(html) {
        document.getElementById('rail-panel-body').innerHTML = html;
    }

    // ── Node panel ────────────────────────────────────────────────────────────
    function renderNodePanel(node) {
        const p = node.properties;
        const listIdEnter = regActionList(
            () => node.properties.on_enter_actions,
            () => renderNodePanel(node)
        );
        const listIdExit = regActionList(
            () => node.properties.on_exit_actions,
            () => renderNodePanel(node)
        );
        panel(`
            <div class="section-title">${t('state','Stato')}</div>
            <div class="field">
                <label>${t('name','Nome')}</label>
                <input type="text" value="${esc(p.name)}"
                    oninput="RailEditor.updateNode(${node.id},'name',this.value)">
            </div>
            <div class="field">
                <label>${t('code','Codice')}</label>
                <input type="text" value="${esc(p.code)}"
                    oninput="RailEditor.updateNode(${node.id},'code',this.value)">
            </div>
            <div class="field field-check">
                <input type="checkbox" id="cb-start" ${p.is_start ? 'checked' : ''}
                    onchange="RailEditor.updateNode(${node.id},'is_start',this.checked)">
                <label for="cb-start">${t('is_start','Stato iniziale')}</label>
            </div>
            <div class="field field-check">
                <input type="checkbox" id="cb-end" ${p.is_end ? 'checked' : ''}
                    onchange="RailEditor.updateNode(${node.id},'is_end',this.checked)">
                <label for="cb-end">${t('is_end','Stato finale')}</label>
            </div>

            <div class="section-title">${t('view_perms','Permessi visualizzazione stato')}</div>
            <div class="field">
                <label>${t('perms_csv','Permessi richiesti (separati da virgola)')}</label>
                <input type="text" placeholder="es. admin, view-orders"
                    value="${esc((p.view_permissions||[]).join(','))}"
                    oninput="RailEditor.updateNode(${node.id},'view_permissions',this.value.split(',').map(s=>s.trim()).filter(Boolean))">
            </div>
            <div class="field">
                <label>${t('operator','Operatore')}</label>
                <select onchange="RailEditor.updateNode(${node.id},'view_operator',this.value)">
                    <option value="OR" ${(p.view_operator||'OR')==='OR'?'selected':''}>${t('op_or','OR – basta uno')}</option>
                    <option value="AND" ${(p.view_operator||'OR')==='AND'?'selected':''}>${t('op_and','AND – tutti richiesti')}</option>
                </select>
            </div>

            <div class="section-title">${t('on_enter','Azioni on_enter')}</div>
            <div id="ae-enter">${renderActionList(listIdEnter)}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;"
                onclick="RailEditor.addStateAction(${node.id},'on_enter_actions',${listIdEnter})">${t('add_action','+ Azione')}</button>

            <div class="section-title">${t('on_exit','Azioni on_exit')}</div>
            <div id="ae-exit">${renderActionList(listIdExit)}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;"
                onclick="RailEditor.addStateAction(${node.id},'on_exit_actions',${listIdExit})">${t('add_action','+ Azione')}</button>

            <hr>
            <button class="btn btn-danger" style="width:100%;"
                onclick="RailEditor.deleteNode(${node.id})">${t('delete_state','🗑 Elimina stato')}</button>
        `);
    }

    // ── Conditional node panel ────────────────────────────────────────────────
    function renderConditionalNodePanel(node) {
        const p = node.properties;
        const listIdEnter = regActionList(
            () => node.properties.on_enter_actions,
            () => renderConditionalNodePanel(node)
        );
        const branchRows = (node.outputs || []).map((slot, i) => {
            const linkId    = slot.links && slot.links[0];
            const hasCond   = linkId && transitionMeta[linkId]?.execute_condition;
            const indicator = hasCond
                ? '<span class="cond-indicator">✓ cond</span>'
                : '<span class="cond-indicator empty">— no cond</span>';
            const canRemove = node.outputs.length > 2;
            return `
                <div class="cond-branch" id="cb-${node.id}-${i}">
                    <span class="cond-branch-num">${i + 1}</span>
                    <input type="text" value="${esc(slot.name || t('branch_n','Ramo ') + (i+1))}"
                        oninput="RailEditor.renameConditionalBranch(${node.id},${i},this.value)"
                        placeholder="${t('branch_n','Ramo ')}${i+1}">
                    ${indicator}
                    ${canRemove ? `<button class="btn-remove" onclick="RailEditor.removeConditionalBranch(${node.id},${i})">✕</button>` : ''}
                </div>`;
        }).join('');

        panel(`
            <div class="section-title">${t('conditional_node','⬦ Nodo Condizionale')}</div>
            <div class="field">
                <label>${t('name','Nome')}</label>
                <input type="text" value="${esc(p.name)}"
                    oninput="RailEditor.updateNode(${node.id},'name',this.value)">
            </div>
            <div class="field">
                <label>${t('code','Codice')}</label>
                <input type="text" value="${esc(p.code)}"
                    oninput="RailEditor.updateNode(${node.id},'code',this.value)">
            </div>
            <div class="field field-check">
                <input type="checkbox" id="cb-cond-start" ${p.is_start ? 'checked' : ''}
                    onchange="RailEditor.updateNode(${node.id},'is_start',this.checked)">
                <label for="cb-cond-start">${t('is_start','Stato iniziale')}</label>
            </div>

            <div class="section-title">${t('branches','Rami uscita')}</div>
            <p style="color:#64748b;font-size:11px;margin-bottom:8px;">${t('branches_hint','I rami vengono valutati in ordine — vince il primo la cui condizione è vera.')}</p>
            <div id="cond-branches-${node.id}">${branchRows}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;margin-top:4px;"
                onclick="RailEditor.addConditionalBranch(${node.id})">${t('add_branch','+ Ramo')}</button>

            <div class="section-title">${t('on_enter','Azioni on_enter')}</div>
            <div id="ae-cond-enter">${renderActionList(listIdEnter)}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;"
                onclick="RailEditor.addStateAction(${node.id},'on_enter_actions',${listIdEnter})">${t('add_action','+ Azione')}</button>

            <hr>
            <button class="btn btn-danger" style="width:100%;"
                onclick="RailEditor.deleteNode(${node.id})">${t('delete_node','🗑 Elimina nodo')}</button>
        `);
    }

    // ── Link panel ────────────────────────────────────────────────────────────
    function renderLinkPanel(link) {
        const m = transitionMeta[link.id] || {};

        // Compute current sort/priority among siblings
        const srcNode   = graph.getNodeById(link.origin_id);
        const slotLinks = srcNode?.outputs?.[link.origin_slot]?.links || [];
        const sortIdx   = slotLinks.indexOf(link.id);
        const isFirst   = sortIdx === 0;
        const isLast    = sortIdx === slotLinks.length - 1;
        const sortLabel = sortIdx >= 0 ? (sortIdx + 1) + ' / ' + slotLinks.length : '–';

        const listIdPre  = regActionList(
            () => (transitionMeta[link.id]?.actions || []).filter(a => a.phase === 'pre'),
            () => renderLinkPanel(link),
            (idx) => {
                const src = transitionMeta[link.id]?.actions || [];
                const sub = src.filter(a => a.phase === 'pre');
                const gi  = src.indexOf(sub[idx]);
                if (gi !== -1) src.splice(gi, 1);
            }
        );
        const listIdPost = regActionList(
            () => (transitionMeta[link.id]?.actions || []).filter(a => a.phase === 'post'),
            () => renderLinkPanel(link),
            (idx) => {
                const src = transitionMeta[link.id]?.actions || [];
                const sub = src.filter(a => a.phase === 'post');
                const gi  = src.indexOf(sub[idx]);
                if (gi !== -1) src.splice(gi, 1);
            }
        );

        panel(`
            <div class="section-title">${t('transition','Transizione')}</div>
            <div class="field">
                <label>${t('label','Label')}</label>
                <input type="text" value="${esc(m.label||'')}"
                    oninput="RailEditor.updateLinkMeta(${link.id},'label',this.value||null)">
            </div>
            <div class="field">
                <label>${t('priority','Priorità (ordine valutazione)')}</label>
                <div class="priority-row">
                    <span class="priority-badge">${sortLabel}</span>
                    ${!isFirst ? `<button class="priority-btn" onclick="RailEditor.reorderLink(${link.id},-1)" title="Aumenta priorità">↑</button>` : ''}
                    ${!isLast  ? `<button class="priority-btn" onclick="RailEditor.reorderLink(${link.id},+1)" title="Diminuisci priorità">↓</button>` : ''}
                </div>
            </div>

            <div class="field">
                <label>${t('show_cond','Condizione visibilità')}</label>
                <div id="jl-show-${link.id}" class="jl-editor-host"></div>
            </div>
            <div class="field">
                <label>${t('exec_cond','Condizione esecuzione')}</label>
                <div id="jl-exec-${link.id}" class="jl-editor-host"></div>
            </div>
            <div class="field">
                <label>${t('exit_cond','Condizione uscita stato')}</label>
                <div id="jl-exit-${link.id}" class="jl-editor-host"></div>
            </div>
            <div class="field">
                <label>${t('redirect','Redirect (route name)')}</label>
                <input type="text" value="${esc(m.redirect || '')}"
                    onchange="RailEditor.updateLinkMeta(${link.id},'redirect',this.value||null)">
            </div>

            <div class="section-title">${t('view_perms_t','Permessi visualizzazione')}</div>
            <div class="field">
                <label>${t('perms_view_csv','Permessi view (separati da virgola)')}</label>
                <input type="text" value="${esc((m.view_permissions||[]).join(','))}"
                    oninput="RailEditor.updateLinkMeta(${link.id},'view_permissions',this.value.split(',').map(s=>s.trim()).filter(Boolean))">
            </div>
            <div class="field">
                <label>${t('operator','Operatore')}</label>
                <select onchange="RailEditor.updateLinkMeta(${link.id},'view_operator',this.value)">
                    <option value="OR" ${(m.view_operator||'OR')==='OR'?'selected':''}>${t('op_or','OR – basta uno')}</option>
                    <option value="AND" ${(m.view_operator||'OR')==='AND'?'selected':''}>${t('op_and','AND – tutti richiesti')}</option>
                </select>
            </div>

            <div class="section-title">${t('adv_perms','Permessi avanzamento')}</div>
            <div class="field">
                <label>${t('perms_adv_csv','Permessi advance (separati da virgola)')}</label>
                <input type="text" value="${esc((m.advance_permissions||[]).join(','))}"
                    oninput="RailEditor.updateLinkMeta(${link.id},'advance_permissions',this.value.split(',').map(s=>s.trim()).filter(Boolean))">
            </div>
            <div class="field">
                <label>${t('operator','Operatore')}</label>
                <select onchange="RailEditor.updateLinkMeta(${link.id},'advance_operator',this.value)">
                    <option value="OR" ${(m.advance_operator||'OR')==='OR'?'selected':''}>${t('op_or','OR – basta uno')}</option>
                    <option value="AND" ${(m.advance_operator||'OR')==='AND'?'selected':''}>${t('op_and','AND – tutti richiesti')}</option>
                </select>
            </div>

            <div class="section-title">${t('pre_actions','Azioni pre-transizione')}</div>
            <div id="al-pre">${renderActionList(listIdPre)}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;"
                onclick="RailEditor.addLinkAction(${link.id},'pre',${listIdPre})">${t('add_pre','+ Azione pre')}</button>

            <div class="section-title">${t('post_actions','Azioni post-transizione')}</div>
            <div id="al-post">${renderActionList(listIdPost)}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;"
                onclick="RailEditor.addLinkAction(${link.id},'post',${listIdPost})">${t('add_post','+ Azione post')}</button>

            <hr>
            ${renderFormSection(link.id)}

            <hr>
            <button class="btn btn-danger" style="width:100%;"
                onclick="RailEditor.deleteLink(${link.id})">${t('delete_trans','🗑 Elimina transizione')}</button>
        `);

        const jlVars = buildJLVarList();
        initJLEditor('jl-show-' + link.id, link.id, 'show_condition',    m.show_condition,    jlVars);
        initJLEditor('jl-exec-' + link.id, link.id, 'execute_condition', m.execute_condition, jlVars);
        initJLEditor('jl-exit-' + link.id, link.id, 'exit_condition',    m.exit_condition,    jlVars);
    }

    // ── Action list renderer ──────────────────────────────────────────────────
    function renderActionList(listId) {
        const reg     = _actionRegs[listId];
        const actions = reg ? reg.getActions() : [];
        if (!actions || !actions.length) {
            return `<p style="color:#64748b;font-size:12px;margin-bottom:8px;">${t('no_actions','Nessuna azione')}</p>`;
        }
        return actions.map((a, i) => {
            const ra     = registeredActions.find(r => r.action === a.action) || {};
            const schema = ra.configuration_schema || [];
            const opts   = registeredActions.map(r =>
                `<option value="${esc(r.action)}" ${r.action === a.action ? 'selected' : ''}>${esc(r.display_name)}</option>`
            ).join('');
            const cfgFields = schema.map(field => {
                const val = esc((a.configuration || {})[field.name] ?? (field.default ?? ''));
                if (field.type === 'textarea') {
                    return `<div class="action-config-field">
                        <label>${esc(field.label || field.name)}</label>
                        <textarea placeholder="${esc(field.placeholder||'')}" rows="2"
                            oninput="actionUpdateCfg(${listId},${i},'${field.name}',this.value)">${val}</textarea>
                    </div>`;
                }
                return `<div class="action-config-field">
                    <label>${esc(field.label || field.name)}</label>
                    <input type="text" value="${val}" placeholder="${esc(field.placeholder||'')}"
                        oninput="actionUpdateCfg(${listId},${i},'${field.name}',this.value)">
                </div>`;
            }).join('');
            return `<div class="action-item">
                <div class="action-item-row">
                    <select onchange="actionChangeClass(${listId},${i},this.value)">${opts}</select>
                    <button class="btn-remove" onclick="actionRemove(${listId},${i})">✕</button>
                </div>
                ${cfgFields}
            </div>`;
        }).join('');
    }

    // ── Form builder helpers ──────────────────────────────────────────────────
    function getFormSchema(linkId) {
        const meta = transitionMeta[linkId] || {};
        if (meta.form_type !== 'json' || !meta.form_data) return [];
        try { return JSON.parse(meta.form_data); } catch (_) { return []; }
    }

    function setFormSchema(linkId, schema) {
        if (!transitionMeta[linkId]) transitionMeta[linkId] = {};
        transitionMeta[linkId].form_data = JSON.stringify(schema);
    }

    function renderFormSection(linkId) {
        const meta    = transitionMeta[linkId] || {};
        const hasForm = meta.form_type === 'json';
        const count   = hasForm ? getFormSchema(linkId).length : 0;
        return `
            <div class="coll-header open" id="coll-h-form-${linkId}" onclick="toggleColl('form-${linkId}')">
                <span class="coll-arrow">▶</span> ${t('form_transition','Form Transizione')}
            </div>
            <div class="coll-body open" id="coll-b-form-${linkId}">
                <div class="field" style="margin-top:8px;">
                    <label>${t('form_transition','Form')}</label>
                    <select onchange="RailEditor.setFormType(${linkId},this.value)">
                        <option value=""     ${!hasForm?'selected':''}>${t('no_form','Nessuna form')}</option>
                        <option value="json" ${hasForm ?'selected':''}>${t('with_form','Con form')}</option>
                    </select>
                </div>
                ${hasForm ? `<button class="btn btn-secondary" style="width:100%;font-size:12px;padding:7px;margin-top:5px;"
                    onclick="openFormModal(${linkId})">${t('configure_fields','📋 Configura campi')}
                    <span id="fb-badge-${linkId}" style="margin-left:6px;background:#1e40af;color:#93c5fd;border-radius:10px;padding:1px 7px;font-size:10px;">${count} campi</span>
                </button>` : ''}
            </div>
        `;
    }

    // ── Form Builder Modal ────────────────────────────────────────────────────
    const FB_TYPES = [
        {type:'text',     icon:'🔤', label:'Testo'},
        {type:'textarea', icon:'📝', label:'Textarea'},
        {type:'number',   icon:'🔢', label:'Numero'},
        {type:'email',    icon:'📧', label:'Email'},
        {type:'date',     icon:'📅', label:'Data'},
        {type:'select',   icon:'⊞',  label:'Select'},
        {type:'checkbox', icon:'☑',  label:'Checkbox'},
        {type:'radio',    icon:'◉',  label:'Radio'},
        {type:'password', icon:'🔒', label:'Password'},
        {type:'hidden',   icon:'🔵', label:'Hidden'},
    ];
    const FB_DV_SOURCES = [
        {value:'none',           label: () => t('dv_none','Nessun default')},
        {value:'literal',        label: () => t('dv_literal','Valore fisso')},
        {value:'entity_field',   label: () => t('dv_entity','Campo entità')},
        {value:'relation_field', label: () => t('dv_relation','Campo relazione')},
        {value:'variable',       label: () => t('dv_variable','Variabile flusso')},
    ];

    let fbState   = {linkId:null, schema:[], selId:null};
    let fbDragSrc = null;

    function fbGenId() { return 'f' + Math.random().toString(36).slice(2,9); }
    function fbe(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function openFormModal(linkId) {
        fbState.linkId = linkId;
        fbState.selId  = null;
        const raw = getFormSchema(linkId);
        fbState.schema = raw.map(f => ({
            id:  f.id  || fbGenId(),
            type: f.type || 'text', name: f.name || '', label: f.label || '',
            placeholder: f.placeholder || '', required: !!f.required,
            validation: f.validation || '', cols: f.cols || 12,
            options: f.options || [],
            default_value: f.default_value || {source:'none',value:'',relation:'',field:''},
        }));
        const existing = document.getElementById('fb-modal');
        if (existing) existing.remove();
        const el = document.createElement('div');
        el.id = 'fb-modal';
        el.innerHTML = fbBuildHTML();
        document.body.appendChild(el);
        el.addEventListener('click', e => { if (e.target === el) closeFormModal(); });
        fbWireDnD();
        fbRenderGrid();
    }

    function closeFormModal() {
        const m = document.getElementById('fb-modal');
        if (m) m.remove();
    }

    function fbSave() {
        const clean = fbState.schema.map(f => {
            const o = {id:f.id,type:f.type,name:f.name,label:f.label,cols:f.cols,required:f.required};
            if (f.placeholder) o.placeholder = f.placeholder;
            if (f.validation)  o.validation  = f.validation;
            if (f.options && f.options.length) o.options = f.options;
            if (f.default_value && f.default_value.source !== 'none') o.default_value = f.default_value;
            return o;
        });
        setFormSchema(fbState.linkId, clean);
        const badge = document.getElementById('fb-badge-' + fbState.linkId);
        if (badge) badge.textContent = clean.length + ' campi';
        closeFormModal();
    }

    function fbBuildHTML() {
        const meta      = transitionMeta[fbState.linkId] || {};
        const linkLabel = fbe(meta.label || ('Link #' + fbState.linkId));
        const palette   = FB_TYPES.map(ft =>
            `<div class="fb-palette-item" draggable="true" data-ptype="${ft.type}">
                <span>${ft.icon}</span><span>${ft.label}</span>
             </div>`
        ).join('');
        return `<div id="fb-dialog">
            <div id="fb-dialog-header">
                <div>
                    <div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">${t('form_builder','Form Builder')}</div>
                    <div style="font-size:15px;font-weight:700;color:#e2e8f0;margin-top:2px;">${linkLabel}</div>
                </div>
                <button onclick="closeFormModal()" style="background:none;border:none;color:#64748b;font-size:22px;cursor:pointer;line-height:1;padding:0 4px;">×</button>
            </div>
            <div id="fb-dialog-body">
                <div id="fb-palette">
                    <div style="font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px;">${t('field_types','Tipi campo')}</div>
                    ${palette}
                </div>
                <div id="fb-canvas-col">
                    <div id="fb-canvas-hint">${t('drag_hint','Trascina campi dalla palette nel canvas • Clicca un campo per modificarlo • Trascina per riordinare')}</div>
                    <div id="fb-canvas"><div id="fb-grid"></div></div>
                </div>
                <div id="fb-props"><div id="fb-props-inner"><p style="color:#475569;font-size:12px;margin-top:16px;text-align:center;">${t('select_field','Seleziona un campo')}</p></div></div>
            </div>
            <div id="fb-dialog-footer">
                <span id="fb-count" style="font-size:12px;color:#64748b;flex:1;">0 campi</span>
                <button onclick="fbPreview()" class="btn btn-secondary" style="font-size:12px;">${t('preview','👁 Anteprima')}</button>
                <button onclick="fbSave()" class="btn btn-success" style="font-size:13px;padding:8px 20px;">${t('save_form','✓ Salva Form')}</button>
            </div>
        </div>`;
    }

    function fbRenderGrid() {
        const grid = document.getElementById('fb-grid');
        if (!grid) return;
        if (!fbState.schema.length) {
            grid.innerHTML = `<div id="fb-grid-empty">${t('drag_here','Trascina qui i campi dalla palette')}</div>`;
        } else {
            grid.innerHTML = fbState.schema.map(f => {
                const sel = f.id === fbState.selId ? ' fb-selected' : '';
                return `<div class="fb-field-card${sel}" style="grid-column:span ${f.cols||12};"
                             data-fid="${f.id}" draggable="true"
                             onclick="fbSelect('${f.id}')">
                    <div class="fbc-type">${fbe(f.type)}${f.required?' <span class="fbc-req">*</span>':''}</div>
                    <div class="fbc-label">${fbe(f.label||f.name||'(no label)')}</div>
                    <div class="fbc-name">${fbe(f.name||'(no name)')}</div>
                    <div class="fbc-cols">${f.cols||12}/12</div>
                    <button class="fbc-del" onclick="event.stopPropagation();fbDel('${f.id}')">✕</button>
                </div>`;
            }).join('');
            fbWireCardDnD();
        }
        const cnt = document.getElementById('fb-count');
        if (cnt) cnt.textContent = fbState.schema.length + ' campo/i';
    }

    function fbSelect(id) {
        fbState.selId = id;
        document.querySelectorAll('.fb-field-card').forEach(c => c.classList.toggle('fb-selected', c.dataset.fid === id));
        fbRenderProps();
    }

    function fbDel(id) {
        fbState.schema = fbState.schema.filter(f => f.id !== id);
        if (fbState.selId === id) { fbState.selId = null; fbRenderProps(); }
        fbRenderGrid();
    }

    function fbRenderProps() {
        const pi = document.getElementById('fb-props-inner');
        if (!pi) return;
        const f = fbState.schema.find(x => x.id === fbState.selId);
        if (!f) { pi.innerHTML = `<p style="color:#475569;font-size:12px;margin-top:16px;text-align:center;">${t('select_field','Seleziona un campo')}</p>`; return; }

        const hasOpts = ['select','radio'].includes(f.type);
        const colBtns = Array.from({length:12},(_,i)=>i+1).map(n=>
            `<div class="fb-col-btn${(f.cols||12)===n?' fb-col-active':''}" onclick="fbUpd('${f.id}','cols',${n})">${n}</div>`
        ).join('');

        const dvSrc  = f.default_value?.source || 'none';
        const dvVal  = fbe(f.default_value?.value||'');
        const dvRel  = fbe(f.default_value?.relation||'');
        const dvFld  = fbe(f.default_value?.field||'');
        const dvOpts = FB_DV_SOURCES.map(s=>`<option value="${s.value}"${dvSrc===s.value?' selected':''}>${s.label()}</option>`).join('');

        let dvExtra = '';
        if (dvSrc==='literal')
            dvExtra = `<div class="fb-prop-field"><label>${t('dv_value','Valore')}</label><input type="text" value="${dvVal}" oninput="fbUpdDV('${f.id}','value',this.value)"></div>`;
        else if (dvSrc==='entity_field')
            dvExtra = `<div class="fb-prop-field"><label>${t('dv_entity_field','Campo entità')}</label><input type="text" value="${dvVal}" placeholder="es. email, name, status" oninput="fbUpdDV('${f.id}','value',this.value)"></div>`;
        else if (dvSrc==='relation_field')
            dvExtra = `
                <div class="fb-prop-field"><label>${t('dv_rel_name','Relazione')}</label><input type="text" value="${dvRel}" placeholder="es. user, company" oninput="fbUpdDV('${f.id}','relation',this.value)"></div>
                <div class="fb-prop-field"><label>${t('dv_rel_field','Campo')}</label><input type="text" value="${dvFld}" placeholder="es. name, email" oninput="fbUpdDV('${f.id}','field',this.value)"></div>`;
        else if (dvSrc==='variable')
            dvExtra = `<div class="fb-prop-field"><label>${t('dv_var_key','Chiave variabile')}</label><input type="text" value="${dvVal}" placeholder="es. amount, approved" oninput="fbUpdDV('${f.id}','value',this.value)"></div>`;

        const typeOpts = FB_TYPES.map(tt=>`<option value="${tt.type}"${f.type===tt.type?' selected':''}>${tt.label} (${tt.type})</option>`).join('');

        // Options editor for select/radio: proper value|label table
        let optsEditor = '';
        if (hasOpts) {
            const rows = (f.options||[]).map((o, i) => `
                <div class="fb-opt-row">
                    <input type="text" value="${fbe(o.value)}" placeholder="${t('opt_value','Valore')}"
                        oninput="fbUpdOptRow('${f.id}',${i},'value',this.value)">
                    <input type="text" value="${fbe(o.label)}" placeholder="${t('opt_label','Label')}"
                        oninput="fbUpdOptRow('${f.id}',${i},'label',this.value)">
                    <button class="fb-opt-del" onclick="fbRemOpt('${f.id}',${i})">✕</button>
                </div>
            `).join('');
            optsEditor = `
                <div class="fb-prop-sep">${t('options','Opzioni')}</div>
                <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:4px;margin-bottom:5px;">
                    <div style="font-size:9px;color:#64748b;font-weight:700;text-transform:uppercase;">${t('opt_value','VALORE')}</div>
                    <div style="font-size:9px;color:#64748b;font-weight:700;text-transform:uppercase;">${t('opt_label','LABEL')}</div>
                    <div></div>
                </div>
                <div id="fb-opts-${f.id}">${rows}</div>
                <button onclick="fbAddOpt('${f.id}')" class="btn btn-secondary" style="width:100%;font-size:11px;padding:4px;margin-top:4px;">${t('add_option','+ Aggiungi opzione')}</button>
            `;
        }

        pi.innerHTML = `
            <div class="fb-prop-sep">${t('field_props','Proprietà campo')}</div>
            <div class="fb-prop-field"><label>${t('field_type','Tipo')}</label><select onchange="fbUpd('${f.id}','type',this.value)">${typeOpts}</select></div>
            <div class="fb-prop-field"><label>${t('field_name','Name')}</label><input type="text" value="${fbe(f.name)}" placeholder="es. amount" oninput="fbUpd('${f.id}','name',this.value)"></div>
            <div class="fb-prop-field"><label>${t('field_label','Label')}</label><input type="text" value="${fbe(f.label)}" placeholder="es. Importo" oninput="fbUpd('${f.id}','label',this.value)"></div>
            <div class="fb-prop-field"><label>${t('field_placeholder','Placeholder')}</label><input type="text" value="${fbe(f.placeholder)}" oninput="fbUpd('${f.id}','placeholder',this.value)"></div>
            <div class="fb-prop-field"><label>${t('field_validation','Validazione Laravel')}</label><input type="text" value="${fbe(f.validation)}" placeholder="es. min:3|max:255" oninput="fbUpd('${f.id}','validation',this.value)"></div>
            <div class="fb-prop-field"><label style="display:flex;align-items:center;gap:6px;text-transform:none;letter-spacing:0;">
                <input type="checkbox" ${f.required?'checked':''} style="width:14px;height:14px;accent-color:#3b82f6;" onchange="fbUpd('${f.id}','required',this.checked)"> ${t('field_required','Obbligatorio')}
            </label></div>
            <div class="fb-prop-field"><label>${t('field_cols','Larghezza (colonne 1–12)')}</label><div class="fb-cols-grid">${colBtns}</div></div>
            ${optsEditor}
            <div class="fb-prop-sep">${t('default_value','Valore di default')}</div>
            <div class="fb-prop-field"><label>${t('dv_source','Sorgente')}</label><select onchange="fbUpdDV('${f.id}','source',this.value)">${dvOpts}</select></div>
            ${dvExtra}
        `;
    }

    function fbUpd(id, key, val) {
        const f = fbState.schema.find(x=>x.id===id);
        if (!f) return;
        f[key] = val;
        if (key==='cols') {
            const c = document.querySelector(`.fb-field-card[data-fid="${id}"]`);
            if (c) { c.style.gridColumn=`span ${val}`; const cc=c.querySelector('.fbc-cols'); if(cc)cc.textContent=val+'/12'; }
            document.querySelectorAll('.fb-col-btn').forEach((b,i)=>b.classList.toggle('fb-col-active',(i+1)===val));
        } else if (key==='type') {
            fbRenderGrid(); fbRenderProps();
        } else {
            const c = document.querySelector(`.fb-field-card[data-fid="${id}"]`);
            if (c) {
                const lbl=c.querySelector('.fbc-label'); if(lbl&&key==='label')lbl.textContent=val||f.name||'(no label)';
                const nm=c.querySelector('.fbc-name');   if(nm&&key==='name')nm.textContent=val||'(no name)';
                if(key==='required'){const tp=c.querySelector('.fbc-type');if(tp)tp.innerHTML=f.type+(val?' <span class="fbc-req">*</span>':'');}
            }
        }
    }

    function fbUpdDV(id, key, val) {
        const f = fbState.schema.find(x=>x.id===id);
        if (!f) return;
        if (!f.default_value) f.default_value={source:'none',value:'',relation:'',field:''};
        f.default_value[key]=val;
        if (key==='source') fbRenderProps();
    }

    function fbUpdOptRow(fid, idx, key, val) {
        const f = fbState.schema.find(x=>x.id===fid);
        if (!f || !f.options || !f.options[idx]) return;
        f.options[idx][key] = val;
    }

    function fbRemOpt(fid, idx) {
        const f = fbState.schema.find(x=>x.id===fid);
        if (!f || !f.options) return;
        f.options.splice(idx, 1);
        fbRenderProps();
    }

    function fbAddOpt(fid) {
        const f = fbState.schema.find(x=>x.id===fid);
        if (!f) return;
        if (!f.options) f.options = [];
        f.options.push({value:'', label:''});
        fbRenderProps();
    }

    function fbWireDnD() {
        const grid = document.getElementById('fb-grid');
        if (!grid) return;
        document.querySelectorAll('.fb-palette-item').forEach(el => {
            el.addEventListener('dragstart', e => {
                fbDragSrc = {from:'palette', type:el.dataset.ptype};
                e.dataTransfer.effectAllowed = 'copy';
            });
        });
        grid.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = fbDragSrc?.from==='palette'?'copy':'move'; grid.classList.add('fb-drag-over'); });
        grid.addEventListener('dragleave', e => { if (!grid.contains(e.relatedTarget)) grid.classList.remove('fb-drag-over'); });
        grid.addEventListener('drop', e => {
            e.preventDefault(); grid.classList.remove('fb-drag-over');
            if (!fbDragSrc) return;
            if (fbDragSrc.from==='palette') {
                const nf = {id:fbGenId(),type:fbDragSrc.type,name:'',label:'',placeholder:'',required:false,validation:'',cols:12,options:[],default_value:{source:'none',value:'',relation:'',field:''}};
                fbState.schema.splice(fbDropIdx(e,grid),0,nf);
                fbState.selId = nf.id;
                fbRenderGrid(); fbRenderProps();
            } else if (fbDragSrc.from==='canvas') {
                const si = fbState.schema.findIndex(f=>f.id===fbDragSrc.fid);
                if (si===-1) return;
                const di = fbDropIdx(e,grid);
                const [mv] = fbState.schema.splice(si,1);
                fbState.schema.splice(di>si?di-1:di,0,mv);
                fbRenderGrid();
                if (fbState.selId===mv.id) fbSelect(mv.id);
            }
            fbDragSrc = null;
        });
    }

    function fbWireCardDnD() {
        document.querySelectorAll('.fb-field-card').forEach(c=>{
            c.addEventListener('dragstart', e => {
                e.stopPropagation();
                fbDragSrc = {from:'canvas', fid:c.dataset.fid};
                e.dataTransfer.effectAllowed = 'move';
                setTimeout(()=>c.classList.add('fb-drag-src'),0);
            });
            c.addEventListener('dragend',()=>{ c.classList.remove('fb-drag-src'); fbDragSrc=null; });
        });
    }

    function fbDropIdx(e, grid) {
        const cards=[...grid.querySelectorAll('.fb-field-card')];
        if (!cards.length) return 0;
        for (let i=0;i<cards.length;i++) {
            const r=cards[i].getBoundingClientRect();
            if (e.clientY < r.top+r.height/2) return i;
        }
        return cards.length;
    }

    function fbPreview() {
        const prev = document.getElementById('fb-preview-wrap');
        if (prev) { prev.remove(); return; }
        if (!fbState.schema.length) return;
        const wrap = document.createElement('div');
        wrap.id='fb-preview-wrap';
        wrap.style.cssText='margin:0 14px 14px;padding:16px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;';
        wrap.innerHTML = `<div style="font-size:11px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;">${t('preview_title','Anteprima')}</div>`
            + fbBuildPreview()
            + `<button onclick="document.getElementById('fb-preview-wrap').remove()" style="margin-top:10px;font-size:11px;color:#6b7280;background:none;border:none;cursor:pointer;text-decoration:underline;">${t('close_preview','Chiudi')}</button>`;
        document.getElementById('fb-canvas').appendChild(wrap);
    }

    function fbBuildPreview() {
        return `<div style="display:grid;grid-template-columns:repeat(12,1fr);gap:8px;margin-bottom:8px;">` +
            fbState.schema.map(f => fbPreviewField(f)).join('') + `</div>`;
    }

    function fbPreviewField(f) {
        const cs=`grid-column:span ${f.cols||12};`;
        const lbl=fbe(f.label||f.name||''); const req=f.required?'<span style="color:#ef4444;">*</span>':'';
        const lh=`<label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px;">${lbl} ${req}</label>`;
        const ia=`style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px;background:#fff;"`;
        switch (f.type) {
            case 'textarea': return `<div style="${cs}">${lh}<textarea rows="2" ${ia} disabled placeholder="${fbe(f.placeholder||'')}"></textarea></div>`;
            case 'select':   return `<div style="${cs}">${lh}<select ${ia} disabled><option>— seleziona —</option>${(f.options||[]).map(o=>`<option>${fbe(o.label||o.value)}</option>`).join('')}</select></div>`;
            case 'checkbox': return `<div style="${cs}"><label style="display:flex;align-items:center;gap:6px;font-size:12px;"><input type="checkbox" disabled> ${lbl} ${req}</label></div>`;
            case 'radio':    return `<div style="${cs}">${lh}${(f.options||[]).map(o=>`<label style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:3px;"><input type="radio" disabled> ${fbe(o.label||o.value)}</label>`).join('')}</div>`;
            case 'hidden':   return `<div style="${cs};font-size:10px;color:#9ca3af;">[hidden: ${fbe(f.name)}]</div>`;
            default:         return `<div style="${cs}">${lh}<input type="${fbe(f.type||'text')}" ${ia} disabled placeholder="${fbe(f.placeholder||'')}"></div>`;
        }
    }

    // ── Workflow Variables Modal ───────────────────────────────────────────────
    function openVarsModal() {
        const existing = document.getElementById('vars-modal');
        if (existing) existing.remove();
        const el = document.createElement('div');
        el.id = 'vars-modal';
        el.innerHTML = buildVarsHTML();
        document.body.appendChild(el);
        el.addEventListener('click', e => { if (e.target === el) closeVarsModal(); });
    }

    function closeVarsModal() {
        const m = document.getElementById('vars-modal');
        if (m) m.remove();
    }

    function buildVarsHTML() {
        const rows = workflowVars.map((v, i) => {
            const typeOpts = ['string','number','boolean','date'].map(typ =>
                `<option value="${typ}"${v.type===typ?' selected':''}>${t('var_type_'+typ, typ)}</option>`
            ).join('');
            return `<div class="var-row">
                <input type="text" value="${fbe(v.name)}" placeholder="${t('var_name','Nome')}"
                    oninput="varsUpdRow(${i},'name',this.value)">
                <input type="text" value="${fbe(v.label||'')}" placeholder="${t('var_label','Label')}"
                    oninput="varsUpdRow(${i},'label',this.value)">
                <select onchange="varsUpdRow(${i},'type',this.value)">${typeOpts}</select>
                <input type="text" value="${fbe(v.default!==undefined?String(v.default):'')}" placeholder="${t('var_default','Default')}"
                    oninput="varsUpdRow(${i},'default',this.value)">
                <button class="var-row-del" onclick="varsDelRow(${i})">✕</button>
            </div>`;
        }).join('');

        return `<div id="vars-dialog">
            <div id="vars-dialog-header">
                <div>
                    <div style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;">Workflow</div>
                    <div style="font-size:15px;font-weight:700;color:#e2e8f0;margin-top:2px;">${t('vars_title','Variabili del Workflow')}</div>
                </div>
                <button onclick="closeVarsModal()" style="background:none;border:none;color:#64748b;font-size:22px;cursor:pointer;line-height:1;padding:0 4px;">×</button>
            </div>
            <div id="vars-dialog-body">
                <p style="font-size:12px;color:#64748b;margin-bottom:14px;">${t('vars_desc','Dichiara le variabili disponibili durante l\'esecuzione del workflow.')}</p>
                <div style="display:grid;grid-template-columns:1fr 1fr auto auto auto;gap:6px;margin-bottom:6px;">
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">${t('var_name','NOME')}</div>
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">${t('var_label','LABEL')}</div>
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">${t('var_type','TIPO')}</div>
                    <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;">${t('var_default','DEFAULT')}</div>
                    <div></div>
                </div>
                <div id="vars-rows">${rows || `<p style="color:#475569;font-size:12px;padding:12px 0;">${t('no_vars','Nessuna variabile dichiarata.')}</p>`}</div>
                <button onclick="varsAddRow()" class="btn btn-secondary" style="margin-top:10px;font-size:12px;">${t('add_var','+ Aggiungi variabile')}</button>
            </div>
            <div id="vars-dialog-footer">
                <span style="flex:1;font-size:12px;color:#64748b;">${workflowVars.length} variabili</span>
                <button onclick="closeVarsModal()" class="btn btn-secondary" style="font-size:12px;">Annulla</button>
                <button onclick="varsSave()" class="btn btn-success" style="font-size:13px;padding:8px 18px;">${t('save_vars','✓ Salva variabili')}</button>
            </div>
        </div>`;
    }

    function varsUpdRow(idx, key, val) {
        if (!workflowVars[idx]) return;
        workflowVars[idx][key] = val;
    }

    function varsDelRow(idx) {
        workflowVars.splice(idx, 1);
        const m = document.getElementById('vars-modal');
        if (m) { m.remove(); openVarsModal(); }
    }

    function varsAddRow() {
        workflowVars.push({name:'', label:'', type:'string', default:''});
        const m = document.getElementById('vars-modal');
        if (m) { m.remove(); openVarsModal(); }
    }

    function varsSave() {
        closeVarsModal();
        setStatus(t('saving','Salvataggio...'));
        RailEditor.save();
    }

    // ── Collapsible ───────────────────────────────────────────────────────────
    function toggleColl(id) {
        const h = document.getElementById('coll-h-' + id);
        const b = document.getElementById('coll-b-' + id);
        if (!h || !b) return;
        h.classList.toggle('open');
        b.classList.toggle('open');
    }

    // ── Public API ────────────────────────────────────────────────────────────
    window.RailEditor = {

        addState() {
            const node   = LiteGraph.createNode('workflow/state');
            const center = canvas.convertOffsetToCanvas([cvs.width / 2, cvs.height / 2]);
            node.pos     = [center[0] - 120 + Math.random() * 40 - 20, center[1] - 44 + Math.random() * 40 - 20];
            node.properties.name = t('add_state','Nuovo Stato') + ' ' + (graph._nodes.length + 1);
            graph.add(node);
            canvas.selectNode(node);
            setStatus(t('add_state','Nodo aggiunto'));
        },

        updateNode(nodeId, prop, value) {
            const node = graph.getNodeById(nodeId);
            if (!node) return;

            // Single initial state enforcement
            if (prop === 'is_start' && value === true) {
                graph._nodes.forEach(n => {
                    if (n.id !== nodeId && n.properties && n.properties.is_start) {
                        n.properties.is_start = false;
                    }
                });
            }

            node.properties[prop] = value;
            graph.setDirtyCanvas(true);
        },

        deleteNode(nodeId) {
            const node = graph.getNodeById(nodeId);
            if (node && confirm(t('confirm_del_state','Eliminare lo stato') + ' "' + node.properties.name + '"?')) {
                graph.remove(node);
                panel('<p class="placeholder">' + t('select_element','Seleziona un nodo o una connessione per modificarne le proprietà.') + '</p>');
                setStatus(t('delete_state','Nodo eliminato'));
            }
        },

        updateLinkMeta(linkId, prop, value) {
            if (!transitionMeta[linkId]) transitionMeta[linkId] = {};
            transitionMeta[linkId][prop] = value;
        },

        deleteLink(linkId) {
            const link = graph.links[linkId];
            if (link && confirm(t('confirm_del_link','Eliminare questa transizione?'))) {
                graph.removeLink(linkId);
                panel('<p class="placeholder">' + t('select_element','Seleziona un nodo o una connessione per modificarne le proprietà.') + '</p>');
                setStatus(t('delete_trans','Transizione eliminata'));
            }
        },

        addStateAction(nodeId, phase, listId) {
            const node = graph.getNodeById(nodeId);
            if (!node || !registeredActions.length) {
                alert(t('no_actions_reg','Nessuna azione registrata disponibile.'));
                return;
            }
            node.properties[phase].push({ action: registeredActions[0].action, configuration: null });
            const reg = _actionRegs[listId];
            if (reg) reg.rerender();
        },

        removeStateAction(nodeId, phase, idx) {
            const node = graph.getNodeById(nodeId);
            if (node) {
                node.properties[phase].splice(idx, 1);
                if (selectedNode && selectedNode.id === nodeId) {
                    node.type === 'workflow/conditional' ? renderConditionalNodePanel(node) : renderNodePanel(node);
                }
            }
        },

        addLinkAction(linkId, phase, listId) {
            if (!registeredActions.length) {
                alert(t('no_actions_reg','Nessuna azione registrata disponibile.'));
                return;
            }
            if (!transitionMeta[linkId]) transitionMeta[linkId] = { actions: [] };
            if (!transitionMeta[linkId].actions) transitionMeta[linkId].actions = [];
            transitionMeta[linkId].actions.push({ phase, action: registeredActions[0].action, configuration: null });
            const reg = _actionRegs[listId];
            if (reg) reg.rerender();
        },

        removeLinkAction(linkId, phase, idx) {
            const meta = transitionMeta[linkId];
            if (!meta || !meta.actions) return;
            const phaseActions = meta.actions.filter(a => a.phase === phase);
            const globalIdx    = meta.actions.indexOf(phaseActions[idx]);
            if (globalIdx !== -1) meta.actions.splice(globalIdx, 1);
            if (selectedLinkId === linkId) renderLinkPanel(graph.links[linkId]);
        },

        // ── Transition priority reorder ──
        reorderLink(linkId, dir) {
            const link = graph.links[linkId];
            if (!link) return;
            const srcNode   = graph.getNodeById(link.origin_id);
            const slotLinks = srcNode?.outputs?.[link.origin_slot]?.links;
            if (!slotLinks) return;
            const idx    = slotLinks.indexOf(linkId);
            const newIdx = idx + dir;
            if (newIdx < 0 || newIdx >= slotLinks.length) return;
            [slotLinks[idx], slotLinks[newIdx]] = [slotLinks[newIdx], slotLinks[idx]];
            renderLinkPanel(link);
        },

        // ── Conditional node ──
        addConditional() {
            const node   = LiteGraph.createNode('workflow/conditional');
            const center = canvas.convertOffsetToCanvas([cvs.width / 2, cvs.height / 2]);
            node.pos     = [center[0] - 115 + Math.random() * 40 - 20, center[1] - 50 + Math.random() * 40 - 20];
            node.properties.name = t('add_conditional','Condizione') + ' ' + (graph._nodes.length + 1);
            graph.add(node);
            canvas.selectNode(node);
            setStatus(t('add_conditional','Nodo condizionale aggiunto'));
        },

        addConditionalBranch(nodeId) {
            const node = graph.getNodeById(nodeId);
            if (!node) return;
            const n = node.outputs ? node.outputs.length + 1 : 3;
            node.addOutput(t('branch_n','Ramo ') + n, 'flow');
            node._resizeToSlots();
            graph.setDirtyCanvas(true);
            renderConditionalNodePanel(node);
        },

        removeConditionalBranch(nodeId, slotIdx) {
            const node = graph.getNodeById(nodeId);
            if (!node || !node.outputs || node.outputs.length <= 2) return;
            const slot = node.outputs[slotIdx];
            if (slot && slot.links) [...slot.links].forEach(id => graph.removeLink(id));
            node.removeOutput(slotIdx);
            node._resizeToSlots();
            graph.setDirtyCanvas(true);
            renderConditionalNodePanel(node);
        },

        renameConditionalBranch(nodeId, slotIdx, newName) {
            const node = graph.getNodeById(nodeId);
            if (!node || !node.outputs || !node.outputs[slotIdx]) return;
            node.outputs[slotIdx].name = newName || (t('branch_n','Ramo ') + (slotIdx + 1));
            graph.setDirtyCanvas(true);
        },

        // ── Form editor ──
        setFormType(linkId, type) {
            if (!transitionMeta[linkId]) transitionMeta[linkId] = {};
            transitionMeta[linkId].form_type = type || null;
            if (!type) transitionMeta[linkId].form_data = null;
            const link = graph.links[linkId];
            if (link) renderLinkPanel(link);
        },

        fitGraph() {
            canvas.ds.reset();
            if (graph._nodes.length) canvas.fitToContents();
        },

        async save() {
            setStatus(t('saving','Salvataggio...'));
            const states = buildPayload();
            try {
                const resp = await fetch(API_URL, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ states, variables: workflowVars }),
                });
                if (!resp.ok) {
                    const err = await resp.json().catch(() => ({}));
                    throw new Error(err.message || 'HTTP ' + resp.status);
                }
                const result = await resp.json();
                for (const savedState of (result.states || [])) {
                    const node = findNodeByName(savedState.name);
                    if (node) node.properties.id = savedState.id;
                }
                // Sync variables from server
                if (result.variables) workflowVars = result.variables;
                setStatus(t('saved','✓ Salvato'), 'ok');
            } catch (e) {
                setStatus('✗ ' + e.message, 'err');
            }
        },
    };

    // ── Action callbacks (global, called from rendered HTML) ──────────────────
    window.actionChangeClass = function (listId, idx, cls) {
        const reg = _actionRegs[listId];
        if (!reg) return;
        const actions = reg.getActions();
        if (actions && actions[idx]) {
            actions[idx].action = cls;
            // reset config when action class changes
            actions[idx].configuration = null;
            reg.rerender();
        }
    };

    window.actionUpdateCfg = function (listId, idx, key, val) {
        const reg = _actionRegs[listId];
        if (!reg) return;
        const actions = reg.getActions();
        if (actions && actions[idx]) {
            if (!actions[idx].configuration) actions[idx].configuration = {};
            actions[idx].configuration[key] = val;
        }
    };

    window.actionRemove = function (listId, idx) {
        const reg = _actionRegs[listId];
        if (!reg) return;
        if (reg.removeFn) {
            reg.removeFn(idx);
        } else {
            const actions = reg.getActions();
            if (actions) actions.splice(idx, 1);
        }
        reg.rerender();
    };

    // ── Serialization ─────────────────────────────────────────────────────────
    function buildPayload() {
        const TYPES = ['workflow/state', 'workflow/conditional'];
        return graph._nodes
            .filter(n => TYPES.includes(n.type))
            .map(node => {
                const p             = node.properties;
                const isConditional = node.type === 'workflow/conditional';
                const transitions   = [];

                if (isConditional) {
                    (node.outputs || []).forEach((slot, slotIdx) => {
                        if (!slot.links || !slot.links.length) return;
                        const linkId   = slot.links[0];
                        const link     = graph.links[linkId];
                        if (!link) return;
                        const destNode = graph.getNodeById(link.target_id);
                        if (!destNode) return;
                        const meta = transitionMeta[linkId] || {};
                        transitions.push({
                            to_id:               destNode.properties.id || destNode.properties.name,
                            sort:                slotIdx,
                            label:               meta.label || slot.name || (t('branch_n','Ramo ') + (slotIdx + 1)),
                            show_condition:      null,
                            execute_condition:   meta.execute_condition   || null,
                            exit_condition:      null,
                            permission:          meta.permission          || null,
                            redirect:            meta.redirect            || null,
                            form_type:           meta.form_type           || null,
                            form_data:           meta.form_data           || null,
                            view_permissions:    meta.view_permissions    || [],
                            view_operator:       meta.view_operator       || 'OR',
                            advance_permissions: meta.advance_permissions || [],
                            advance_operator:    meta.advance_operator    || 'OR',
                            waypoints:           meta.waypoints?.length   ? meta.waypoints : null,
                            actions:             (meta.actions || []).map((a, i) => ({
                                sort: i, phase: a.phase, action: a.action,
                                configuration: a.configuration || null,
                            })),
                        });
                    });
                } else {
                    if (node.outputs && node.outputs[0] && node.outputs[0].links) {
                        node.outputs[0].links.forEach((linkId, sort) => {
                            const link = graph.links[linkId];
                            if (!link) return;
                            const destNode = graph.getNodeById(link.target_id);
                            if (!destNode) return;
                            const meta = transitionMeta[linkId] || {};
                            transitions.push({
                                to_id:               destNode.properties.id || destNode.properties.name,
                                sort,
                                label:               meta.label               || null,
                                show_condition:      meta.show_condition      || null,
                                execute_condition:   meta.execute_condition   || null,
                                exit_condition:      meta.exit_condition      || null,
                                permission:          meta.permission          || null,
                                redirect:            meta.redirect            || null,
                                form_type:           meta.form_type           || null,
                                form_data:           meta.form_data           || null,
                                view_permissions:    meta.view_permissions    || [],
                                view_operator:       meta.view_operator       || 'OR',
                                advance_permissions: meta.advance_permissions || [],
                                advance_operator:    meta.advance_operator    || 'OR',
                                waypoints:           meta.waypoints?.length   ? meta.waypoints : null,
                                actions:             (meta.actions || []).map((a, i) => ({
                                    sort: i, phase: a.phase, action: a.action,
                                    configuration: a.configuration || null,
                                })),
                            });
                        });
                    }
                }

                return {
                    id:               p.id || null,
                    type:             isConditional ? 'conditional' : 'simple',
                    name:             p.name,
                    code:             p.code || null,
                    slug:             p.slug || null,
                    is_start:         !!p.is_start,
                    is_end:           !!p.is_end,
                    x:                node.pos[0],
                    y:                node.pos[1],
                    view_permissions: p.view_permissions || [],
                    view_operator:    p.view_operator    || 'OR',
                    on_enter_actions: (p.on_enter_actions || []).map((a, i) => ({ sort: i, action: a.action, configuration: a.configuration || null })),
                    on_exit_actions:  (p.on_exit_actions  || []).map((a, i) => ({ sort: i, action: a.action, configuration: a.configuration || null })),
                    transitions,
                };
            });
    }

    function findNodeByName(name) {
        return graph._nodes.find(n => n.properties && n.properties.name === name) || null;
    }

    // ── Load workflow ─────────────────────────────────────────────────────────
    async function loadWorkflow() {
        setStatus(t('loading','Caricamento...'));
        try {
            const raResp = await fetch(API_ACTIONS_URL, { headers: { Accept: 'application/json' } });
            if (raResp.ok) registeredActions = await raResp.json();

            const resp = await fetch(API_URL, { headers: { Accept: 'application/json' } });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const data = await resp.json();

            workflowVars = data.variables || [];

            graph.clear();
            const nodeById = {};

            for (const state of (data.states || [])) {
                const isConditional = (state.type === 'conditional');
                const node          = LiteGraph.createNode(isConditional ? 'workflow/conditional' : 'workflow/state');
                node.pos = [state.x || 0, state.y || 0];
                node.properties = {
                    id: state.id, name: state.name, type: state.type || 'simple',
                    code: state.code || '', slug: state.slug || '',
                    is_start: !!state.is_start, is_end: !!state.is_end,
                    on_enter_actions: state.on_enter_actions || [],
                    on_exit_actions:  state.on_exit_actions  || [],
                    view_permissions: state.view_permissions || [],
                    view_operator:    state.view_operator    || 'OR',
                };

                if (isConditional) {
                    const tCount = (state.transitions || []).length;
                    while ((node.outputs || []).length < Math.max(2, tCount)) {
                        const n = (node.outputs || []).length + 1;
                        node.addOutput(t('branch_n','Ramo ') + n, 'flow');
                    }
                    (state.transitions || []).forEach((tr, i) => {
                        if (node.outputs[i]) node.outputs[i].name = tr.label || (t('branch_n','Ramo ') + (i + 1));
                    });
                    node._resizeToSlots();
                }

                graph.add(node);
                nodeById[state.id] = node;
            }

            for (const state of (data.states || [])) {
                const srcNode       = nodeById[state.id];
                const isConditional = (state.type === 'conditional');
                if (!srcNode) continue;
                const transitions = [...(state.transitions || [])].sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0));

                for (const [idx, tr] of transitions.entries()) {
                    const dstNode = nodeById[tr.to_id];
                    if (!dstNode) continue;
                    const srcSlot = isConditional ? idx : 0;
                    srcNode.connect(srcSlot, dstNode, 0);
                    const newLink = getNewestLink(graph);
                    if (newLink) {
                        transitionMeta[newLink.id] = {
                            label:               tr.label               || null,
                            show_condition:      tr.show_condition      || null,
                            execute_condition:   tr.execute_condition   || null,
                            exit_condition:      tr.exit_condition      || null,
                            permission:          tr.permission          || null,
                            redirect:            tr.redirect            || null,
                            actions:             tr.actions             || [],
                            form_type:           tr.form_type           || null,
                            form_data:           tr.form_data           || null,
                            view_permissions:    tr.view_permissions    || [],
                            view_operator:       tr.view_operator       || 'OR',
                            advance_permissions: tr.advance_permissions || [],
                            advance_operator:    tr.advance_operator    || 'OR',
                            waypoints:           tr.waypoints?.length   ? tr.waypoints : [],
                        };
                    }
                }
            }

            const allZero = (data.states || []).every(s => !s.x && !s.y);
            if (allZero) autoLayout();
            canvas.ds.reset();
            if (graph._nodes.length) canvas.fitToContents();
            setStatus(t('loaded','✓ Workflow caricato'), 'ok');
        } catch (e) {
            setStatus('✗ ' + e.message, 'err');
        }
    }

    function getNewestLink(g) {
        const ids = Object.keys(g.links).map(Number);
        if (!ids.length) return null;
        return g.links[Math.max(...ids)];
    }

    function autoLayout() {
        const cols = Math.ceil(Math.sqrt(graph._nodes.length));
        const padX = 290, padY = 140;
        graph._nodes.forEach((n, i) => {
            n.pos = [(i % cols) * padX + 50, Math.floor(i / cols) * padY + 50];
        });
    }

    // ── Utilities ─────────────────────────────────────────────────────────────
    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    // ── JsonLogic helpers ─────────────────────────────────────────────────────
    function buildJLVarList() {
        const base = [
            { value: 'entity.id',      label: 'entity.id' },
            { value: 'entity.status',  label: 'entity.status' },
            { value: 'request.action', label: 'request.action' },
        ];
        // Add declared workflow variables
        workflowVars.forEach(v => {
            base.push({ value: 'variables.' + v.name, label: 'variables.' + v.name + (v.label ? ' (' + v.label + ')' : '') });
        });
        return base;
    }

    function initJLEditor(divId, linkId, condField, currentValue, vars) {
        if (typeof jQuery === 'undefined') return;
        const $el = jQuery('#' + divId);
        if (!$el.length) return;
        $el.jsonlogicUI({ variables: vars, value: currentValue || null, input_name: divId })
           .on('jsonlogicui:change', function () {
               const raw = jQuery(this).find('> input[type=hidden]').val() || '';
               let parsed = null;
               try { if (raw) parsed = JSON.parse(raw); } catch (_) {}
               RailEditor.updateLinkMeta(linkId, condField, parsed);
           });
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y); ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        ctx.lineTo(x + w, y + h - r); ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        ctx.lineTo(x + r, y + h); ctx.quadraticCurveTo(x, y + h, x, y + h - r);
        ctx.lineTo(x, y + r); ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    // ── Left panel: drag nodes to canvas ─────────────────────────────────────
    document.querySelectorAll('.lp-node-item[data-ntype]').forEach(el => {
        el.addEventListener('dragstart', e => {
            e.dataTransfer.setData('application/rail-ntype', el.dataset.ntype);
            e.dataTransfer.effectAllowed = 'copy';
        });
    });

    const canvasWrap = document.getElementById('rail-canvas-wrap');
    canvasWrap.addEventListener('dragover', e => e.preventDefault());
    canvasWrap.addEventListener('drop', e => {
        e.preventDefault();
        const ntype = e.dataTransfer.getData('application/rail-ntype');
        if (!ntype) return;
        const rect     = cvs.getBoundingClientRect();
        const mouseX   = e.clientX - rect.left;
        const mouseY   = e.clientY - rect.top;
        const graphPos = canvas.convertCanvasToOffset([mouseX, mouseY]);
        const litegraphType = ntype === 'conditional' ? 'workflow/conditional' : 'workflow/state';
        const node = LiteGraph.createNode(litegraphType);
        node.pos   = [graphPos[0] - node.size[0] / 2, graphPos[1] - node.size[1] / 2];
        node.properties.name = ntype === 'conditional'
            ? (t('add_conditional','Condizione') + ' ' + (graph._nodes.length + 1))
            : (t('add_state','Nuovo Stato')    + ' ' + (graph._nodes.length + 1));
        graph.add(node);
        canvas.selectNode(node);
        setStatus(t('add_state','Nodo aggiunto'));
    });

    // ── Double-click on link segment → add waypoint ───────────────────────────
    cvs.addEventListener('dblclick', e => {
        const rect = cvs.getBoundingClientRect();
        const gx   = (e.clientX - rect.left) / canvas.ds.scale - canvas.ds.offset[0];
        const gy   = (e.clientY - rect.top)  / canvas.ds.scale - canvas.ds.offset[1];
        if (graph.getNodeOnPos(gx, gy, canvas.visible_nodes)) return; // on a node, skip
        const threshold = 12 / canvas.ds.scale;
        for (const id in graph.links) {
            const link = graph.links[id];
            if (!link) continue;
            const src = graph.getNodeById(link.origin_id);
            const dst = graph.getNodeById(link.target_id);
            if (!src || !dst) continue;
            const a = src.getConnectionPos(false, link.origin_slot);
            const b = dst.getConnectionPos(true,  link.target_slot);
            if (!transitionMeta[id]) transitionMeta[id] = {};
            const wps = transitionMeta[id].waypoints || [];
            const pts = [a, ...wps.map(w => [w.x, w.y]), b];
            let minD = Infinity, insertAt = -1;
            for (let i = 0; i < pts.length - 1; i++) {
                const d = _distToSeg([gx, gy], pts[i], pts[i+1]);
                if (d < minD) { minD = d; insertAt = i; }
            }
            if (minD < threshold && insertAt >= 0) {
                wps.splice(insertAt, 0, { x: gx, y: gy });
                transitionMeta[id].waypoints = wps;
                canvas.setDirty(true, true);
                e.stopPropagation();
                break;
            }
        }
    });

    // ── Canvas resize ─────────────────────────────────────────────────────────
    function resizeCanvas() {
        const wrap  = document.getElementById('rail-canvas-wrap');
        cvs.width   = wrap.clientWidth;
        cvs.height  = wrap.clientHeight;
        canvas.dirty_canvas = true;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    graph.start(30);
    loadWorkflow();

    // ── Window exports (called from inline onclick handlers) ──────────────────
    window.openFormModal  = openFormModal;
    window.closeFormModal = closeFormModal;
    window.fbSave         = fbSave;
    window.fbSelect       = fbSelect;
    window.fbDel          = fbDel;
    window.fbUpd          = fbUpd;
    window.fbUpdDV        = fbUpdDV;
    window.fbUpdOptRow    = fbUpdOptRow;
    window.fbRemOpt       = fbRemOpt;
    window.fbAddOpt       = fbAddOpt;
    window.fbPreview      = fbPreview;
    window.toggleColl     = toggleColl;
    window.openVarsModal  = openVarsModal;
    window.closeVarsModal = closeVarsModal;
    window.varsUpdRow     = varsUpdRow;
    window.varsDelRow     = varsDelRow;
    window.varsAddRow     = varsAddRow;
    window.varsSave       = varsSave;

})();
</script>
</body>
</html>
