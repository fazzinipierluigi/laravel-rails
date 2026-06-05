<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Rails — {{ $workflowSlug }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litegraph.js@0.7.18/css/litegraph.css">
    <link rel="stylesheet" href="{{ route('laravel-rails.assets', ['file' => 'jsonlogic_ui.css']) }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            width: 100%; height: 100%;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
        }

        #rail-editor {
            display: flex;
            width: 100vw;
            height: 100vh;
        }

        #rail-canvas-wrap {
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        #rail-canvas {
            display: block;
            width: 100%;
            height: 100%;
        }

        /* ── Toolbar ── */
        #rail-toolbar {
            position: absolute;
            top: 12px; left: 12px;
            display: flex; gap: 8px; align-items: center;
            background: rgba(15,23,42,.85);
            backdrop-filter: blur(8px);
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 8px 12px;
            z-index: 10;
        }
        #rail-toolbar h2 { font-size: 13px; font-weight: 700; color: #94a3b8; margin-right: 8px; letter-spacing: .05em; text-transform: uppercase; }

        /* ── Buttons ── */
        .btn {
            padding: 6px 12px;
            border: none; border-radius: 6px;
            font-size: 12px; font-weight: 600;
            cursor: pointer; transition: opacity .15s;
        }
        .btn:hover { opacity: .85; }
        .btn-primary  { background: #3b82f6; color: #fff; }
        .btn-success  { background: #22c55e; color: #fff; }
        .btn-danger   { background: #ef4444; color: #fff; }
        .btn-secondary{ background: #475569; color: #e2e8f0; }

        /* ── Side Panel ── */
        #rail-panel {
            width: 360px; min-width: 360px;
            height: 100%;
            background: #1e293b;
            border-left: 1px solid #334155;
            display: flex; flex-direction: column;
            overflow: hidden;
        }
        #rail-panel-header {
            padding: 16px;
            border-bottom: 1px solid #334155;
            background: #0f172a;
            font-size: 13px; font-weight: 700; color: #94a3b8;
            letter-spacing: .05em; text-transform: uppercase;
        }
        #rail-panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }
        #rail-panel-body p.placeholder {
            color: #64748b;
            font-size: 13px;
            text-align: center;
            margin-top: 40px;
        }

        /* ── Form Controls ── */
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; }
        .field input[type=text], .field textarea, .field select {
            width: 100%; padding: 7px 10px;
            background: #0f172a; border: 1px solid #334155; border-radius: 6px;
            color: #e2e8f0; font-size: 13px;
            transition: border-color .15s;
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
            margin: 18px 0 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #334155;
        }

        /* ── Action List ── */
        .action-item {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 6px;
            display: flex; align-items: center; gap: 8px;
        }
        .action-item select { flex: 1; background: #1e293b; border: 1px solid #475569; border-radius: 4px; padding: 4px 6px; color: #e2e8f0; font-size: 12px; }
        .action-item .btn-remove { padding: 3px 7px; font-size: 11px; background: #7f1d1d; color: #fca5a5; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0; }
        .action-item .btn-remove:hover { background: #ef4444; color: #fff; }

        /* ── Status badge ── */
        #rail-status {
            position: absolute;
            bottom: 12px; left: 12px;
            background: rgba(15,23,42,.85);
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 11px; color: #64748b;
            z-index: 10;
        }
        #rail-status.ok  { color: #22c55e; border-color: #166534; }
        #rail-status.err { color: #ef4444; border-color: #7f1d1d; }

        hr { border: none; border-top: 1px solid #334155; margin: 14px 0; }

        /* ── Conditional branch list ── */
        .cond-branch {
            display: flex; align-items: center; gap: 5px;
            margin-bottom: 5px;
            padding: 5px 7px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 5px;
        }
        .cond-branch-num {
            font-size: 10px; font-weight: 700; color: #fbbf24;
            background: #1c1100; border: 1px solid #78350f;
            border-radius: 3px; padding: 1px 5px; flex-shrink: 0;
        }
        .cond-branch input {
            flex: 1; padding: 4px 6px;
            background: #1e293b; border: 1px solid #475569; border-radius: 4px;
            color: #e2e8f0; font-size: 11px;
        }
        .cond-branch .btn-remove { padding: 3px 7px; font-size: 11px; background: #7f1d1d; color: #fca5a5; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0; }
        .cond-branch .btn-remove:hover { background: #ef4444; color: #fff; }
        .cond-branch .cond-indicator { font-size: 10px; color: #22c55e; flex-shrink: 0; }
        .cond-branch .cond-indicator.empty { color: #475569; }

        /* jsonlogic_ui host */
        .jl-editor-host { margin-top: 4px; }
        .jl-editor-host .jsonlogic_ui > .jl-root { border-radius: 5px; }
        /* Override panel input widths for jl-sel / jl-inp inside the editor */
        .field .jl-inp,
        .field .jl-sel { width: auto; padding: 4px 6px; font-size: 11px; }

        /* ── Form Field Cards ── */
        .ff-card {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 6px;
        }
        .ff-card input[type=text], .ff-card select, .ff-card textarea {
            width: 100%; padding: 5px 8px;
            background: #1e293b; border: 1px solid #475569; border-radius: 4px;
            color: #e2e8f0; font-size: 11px;
            margin-bottom: 4px;
        }
        .ff-card textarea { resize: vertical; min-height: 48px; font-family: monospace; }
        .ff-card .ff-row { display: flex; gap: 4px; align-items: center; margin-bottom: 4px; }
        .ff-card .ff-row select { flex: 0 0 88px; margin-bottom: 0; }
        .ff-card .ff-row input { flex: 1; margin-bottom: 0; }
        .ff-card .ff-move { padding: 2px 6px; background: #1e3a5f; border: none; border-radius: 3px; color: #93c5fd; cursor: pointer; font-size: 11px; flex-shrink: 0; }
        .ff-card .ff-move:disabled { opacity: .4; cursor: default; }
        .ff-card .btn-remove { padding: 3px 7px; font-size: 11px; background: #7f1d1d; color: #fca5a5; border: none; border-radius: 4px; cursor: pointer; flex-shrink: 0; }
        .ff-card .ff-check { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #94a3b8; margin-bottom: 4px; }
        .ff-card .ff-check input { width: 13px; height: 13px; accent-color: #3b82f6; flex-shrink: 0; }

        /* ── Form Preview ── */
        .form-preview-wrap {
            background: #fff;
            color: #111;
            border-radius: 6px;
            padding: 14px;
            margin-top: 8px;
            font-size: 13px;
        }
        .form-preview-wrap .fp-label { display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px; color: #374151; }
        .form-preview-wrap .fp-req { color: #ef4444; }
        .form-preview-wrap .fp-input { width: 100%; padding: 6px 9px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px; margin-bottom: 10px; background: #f9fafb; }
        .form-preview-wrap .fp-field { margin-bottom: 10px; }

        /* ── Collapsible section ── */
        .coll-header {
            display: flex; align-items: center; gap: 6px;
            cursor: pointer; user-select: none;
            font-size: 11px; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: .08em;
            margin: 18px 0 0;
            padding-bottom: 6px;
            border-bottom: 1px solid #334155;
        }
        .coll-header .coll-arrow { transition: transform .2s; }
        .coll-header.open .coll-arrow { transform: rotate(90deg); }
        .coll-body { display: none; padding-top: 8px; }
        .coll-body.open { display: block; }
    </style>
</head>
<body>
<div id="rail-editor">
    <div id="rail-canvas-wrap">
        <canvas id="rail-canvas"></canvas>

        <div id="rail-toolbar">
            <h2>⚡ Laravel Rails</h2>
            <button class="btn btn-secondary" onclick="RailEditor.addState()">+ Stato</button>
            <button class="btn btn-secondary" style="color:#fde68a;border:1px solid #78350f;" onclick="RailEditor.addConditional()">◇ Condizionale</button>
            <button class="btn btn-success"   onclick="RailEditor.save()">💾 Salva</button>
            <button class="btn btn-secondary" onclick="RailEditor.fitGraph()">⊡ Fit</button>
        </div>

        <div id="rail-status">Caricamento...</div>
    </div>

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

    const WORKFLOW_SLUG   = @json($workflowSlug);
    const API_URL         = @json(route('laravel-rails.workflow.show', ['slug' => $workflowSlug]));
    const API_ACTIONS_URL = @json(route('laravel-rails.registered-actions'));
    const CSRF_TOKEN      = @json(csrf_token());

    // --- Transition metadata store, indexed by litegraph link ID ---
    const transitionMeta = {};
    let registeredActions = [];
    let selectedNode = null;
    let selectedLinkId = null;

    // --- Status bar ---
    function setStatus(msg, type = '') {
        const el = document.getElementById('rail-status');
        el.textContent = msg;
        el.className = type;
    }

    // --- LiteGraph Setup ---
    LiteGraph.debug = false;
    LiteGraph.node_images_path = '';

    // --- WorkflowState Node ---
    function WorkflowStateNode() {
        this.size      = [200, 72];
        this.addInput('', 'flow');
        this.addOutput('', 'flow');
        this.properties = {
            id: null, name: 'Nuovo Stato', code: '',
            slug: '', is_start: false, is_end: false,
            on_enter_actions: [], on_exit_actions: [],
            view_permissions: [], view_operator: 'OR',
        };
        this.color    = '#1e3a5f';
        this.bgColor  = '#1e293b';
        this.shape    = LiteGraph.ROUND_SHAPE;
    }
    WorkflowStateNode.title = 'State';
    WorkflowStateNode.prototype.onExecute = function () {};

    WorkflowStateNode.prototype.onDrawForeground = function (ctx) {
        const p = this.properties;
        // Background colour for start/end
        if (p.is_start) {
            ctx.fillStyle = 'rgba(34,197,94,.12)';
            ctx.fillRect(0, 0, this.size[0], this.size[1]);
        } else if (p.is_end) {
            ctx.fillStyle = 'rgba(239,68,68,.12)';
            ctx.fillRect(0, 0, this.size[0], this.size[1]);
        }

        // Name
        ctx.font = 'bold 13px sans-serif';
        ctx.fillStyle = p.is_start ? '#4ade80' : p.is_end ? '#f87171' : '#e2e8f0';
        ctx.fillText((p.name || 'State').substring(0, 24), 8, 26);

        // Code
        if (p.code) {
            ctx.font = '11px monospace';
            ctx.fillStyle = '#94a3b8';
            ctx.fillText(p.code, 8, 44);
        }

        // Badges
        if (p.is_start) {
            ctx.fillStyle = '#16a34a';
            roundRect(ctx, this.size[0] - 48, 8, 42, 16, 4);
            ctx.fill();
            ctx.fillStyle = '#fff';
            ctx.font = '9px sans-serif';
            ctx.fillText('START', this.size[0] - 43, 19);
        }
        if (p.is_end) {
            ctx.fillStyle = '#dc2626';
            roundRect(ctx, this.size[0] - 42, 8, 36, 16, 4);
            ctx.fill();
            ctx.fillStyle = '#fff';
            ctx.font = '9px sans-serif';
            ctx.fillText('END', this.size[0] - 35, 19);
        }
    };

    WorkflowStateNode.prototype.onSelected = function () {
        selectedNode   = this;
        selectedLinkId = null;
        renderNodePanel(this);
    };
    WorkflowStateNode.prototype.onDeselected = function () {};

    LiteGraph.registerNodeType('workflow/state', WorkflowStateNode);

    // --- WorkflowConditionalNode ---
    function WorkflowConditionalNode() {
        this.addInput('', 'flow');
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
        this.size = [210, 46 + slots * 22];
    };

    WorkflowConditionalNode.prototype.onExecute = function () {};

    WorkflowConditionalNode.prototype.onDrawForeground = function (ctx) {
        const p  = this.properties;
        const cx = this.size[0] / 2;

        // Diamond icon
        ctx.fillStyle = '#fbbf24';
        ctx.beginPath();
        ctx.moveTo(cx, 8);
        ctx.lineTo(cx + 10, 18);
        ctx.lineTo(cx, 28);
        ctx.lineTo(cx - 10, 18);
        ctx.closePath();
        ctx.fill();

        // Name
        ctx.font = 'bold 12px sans-serif';
        ctx.fillStyle = '#fde68a';
        ctx.fillText((p.name || 'Condizione').substring(0, 24), 8, 42);

        if (p.code) {
            ctx.font = '10px monospace';
            ctx.fillStyle = '#92400e';
            ctx.fillText(p.code, 8, 54);
        }
    };

    WorkflowConditionalNode.prototype.onSelected = function () {
        selectedNode   = this;
        selectedLinkId = null;
        renderConditionalNodePanel(this);
    };
    WorkflowConditionalNode.prototype.onDeselected = function () {};

    LiteGraph.registerNodeType('workflow/conditional', WorkflowConditionalNode);

    // --- Graph & Canvas ---
    const graph  = new LGraph();
    const cvs    = document.getElementById('rail-canvas');
    const canvas = new LGraphCanvas(cvs, graph);

    canvas.background_image = null;
    canvas.clear_background = true;
    canvas.clear_background_color = '#0f172a';
    canvas.render_connection_arrows = true;
    canvas.connections_width = 2;
    canvas.default_link_color = '#3b82f6';

    // Intercept link creation/deletion to maintain transitionMeta
    graph.onConnectionChange = function () {
        // Add meta for new links
        for (const id in this.links) {
            if (!transitionMeta[id]) {
                transitionMeta[id] = {
                    show_condition: null,
                    execute_condition: null,
                    exit_condition: null,
                    permission: null,
                    redirect: null,
                    actions: [],
                    form_type: null,
                    form_data: null,
                    view_permissions: [],
                    view_operator: 'OR',
                    advance_permissions: [],
                    advance_operator: 'OR',
                };
            }
        }
        // Remove meta for deleted links
        for (const id in transitionMeta) {
            if (!this.links[id]) delete transitionMeta[id];
        }
    };

    // Click on link → show link panel
    canvas.onShowLinkMenu = function (link, e) {
        selectedLinkId = link.id;
        selectedNode   = null;
        renderLinkPanel(link);
        return false;
    };

    // --- Panel Rendering ---
    function panel(html) {
        document.getElementById('rail-panel-body').innerHTML = html;
    }

    function renderNodePanel(node) {
        const p = node.properties;
        panel(`
            <div class="section-title">Stato</div>
            <div class="field">
                <label>Nome</label>
                <input type="text" value="${esc(p.name)}"
                    oninput="RailEditor.updateNode(${node.id},'name',this.value)">
            </div>
            <div class="field">
                <label>Codice</label>
                <input type="text" value="${esc(p.code)}"
                    oninput="RailEditor.updateNode(${node.id},'code',this.value)">
            </div>
            <div class="field field-check">
                <input type="checkbox" id="cb-start" ${p.is_start ? 'checked' : ''}
                    onchange="RailEditor.updateNode(${node.id},'is_start',this.checked)">
                <label for="cb-start">Stato iniziale</label>
            </div>
            <div class="field field-check">
                <input type="checkbox" id="cb-end" ${p.is_end ? 'checked' : ''}
                    onchange="RailEditor.updateNode(${node.id},'is_end',this.checked)">
                <label for="cb-end">Stato finale</label>
            </div>

            <div class="section-title">Permessi visualizzazione stato</div>
            <div class="field">
                <label>Permessi richiesti (separati da virgola)</label>
                <input type="text" placeholder="es. admin, view-orders"
                    value="${esc((p.view_permissions||[]).join(','))}"
                    oninput="RailEditor.updateNode(${node.id},'view_permissions',this.value.split(',').map(s=>s.trim()).filter(Boolean))">
            </div>
            <div class="field">
                <label>Operatore</label>
                <select onchange="RailEditor.updateNode(${node.id},'view_operator',this.value)">
                    <option value="OR" ${(p.view_operator||'OR')==='OR'?'selected':''}>OR – basta uno</option>
                    <option value="AND" ${(p.view_operator||'OR')==='AND'?'selected':''}>AND – tutti richiesti</option>
                </select>
            </div>

            <div class="section-title">Azioni on_enter</div>
            <div id="ae-enter">${renderActionList(p.on_enter_actions, `RailEditor.removeStateAction(${node.id},'on_enter_actions',IDX)`)}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;"
                onclick="RailEditor.addStateAction(${node.id},'on_enter_actions')">+ Azione</button>

            <div class="section-title">Azioni on_exit</div>
            <div id="ae-exit">${renderActionList(p.on_exit_actions, `RailEditor.removeStateAction(${node.id},'on_exit_actions',IDX)`)}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;"
                onclick="RailEditor.addStateAction(${node.id},'on_exit_actions')">+ Azione</button>

            <hr>
            <button class="btn btn-danger" style="width:100%;"
                onclick="RailEditor.deleteNode(${node.id})">🗑 Elimina stato</button>
        `);
    }

    function renderConditionalNodePanel(node) {
        const p = node.properties;

        // Build branch list: match output slots to their connected links
        const branchRows = (node.outputs || []).map((slot, i) => {
            const linkId  = slot.links && slot.links[0];
            const hasCond = linkId && transitionMeta[linkId]?.execute_condition;
            const indicator = hasCond
                ? '<span class="cond-indicator">✓ cond</span>'
                : '<span class="cond-indicator empty">— no cond</span>';
            const canRemove = node.outputs.length > 2;
            return `
                <div class="cond-branch" id="cb-${node.id}-${i}">
                    <span class="cond-branch-num">${i + 1}</span>
                    <input type="text" value="${esc(slot.name || 'Ramo ' + (i+1))}"
                        oninput="RailEditor.renameConditionalBranch(${node.id},${i},this.value)"
                        placeholder="Ramo ${i+1}">
                    ${indicator}
                    ${canRemove
                        ? `<button class="btn-remove" onclick="RailEditor.removeConditionalBranch(${node.id},${i})">✕</button>`
                        : ''}
                </div>`;
        }).join('');

        panel(`
            <div class="section-title">⬦ Nodo Condizionale</div>
            <div class="field">
                <label>Nome</label>
                <input type="text" value="${esc(p.name)}"
                    oninput="RailEditor.updateNode(${node.id},'name',this.value)">
            </div>
            <div class="field">
                <label>Codice</label>
                <input type="text" value="${esc(p.code)}"
                    oninput="RailEditor.updateNode(${node.id},'code',this.value)">
            </div>
            <div class="field field-check">
                <input type="checkbox" id="cb-cond-start" ${p.is_start ? 'checked' : ''}
                    onchange="RailEditor.updateNode(${node.id},'is_start',this.checked)">
                <label for="cb-cond-start">Stato iniziale</label>
            </div>

            <div class="section-title">Rami uscita</div>
            <p style="color:#64748b;font-size:11px;margin-bottom:8px;">
                I rami vengono valutati in ordine — vince il primo la cui condizione è vera.<br>
                Clicca sulla freccia di un ramo per impostarne la condizione.
            </p>
            <div id="cond-branches-${node.id}">${branchRows}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;margin-top:4px;"
                onclick="RailEditor.addConditionalBranch(${node.id})">+ Ramo</button>

            <div class="section-title">Azioni on_enter</div>
            <div id="ae-cond-enter">${renderActionList(p.on_enter_actions, `RailEditor.removeStateAction(${node.id},'on_enter_actions',IDX)`)}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;"
                onclick="RailEditor.addStateAction(${node.id},'on_enter_actions')">+ Azione</button>

            <hr>
            <button class="btn btn-danger" style="width:100%;"
                onclick="RailEditor.deleteNode(${node.id})">🗑 Elimina nodo</button>
        `);
    }

    function renderLinkPanel(link) {
        const m = transitionMeta[link.id] || {};
        panel(`
            <div class="section-title">Transizione</div>
            <div class="field">
                <label>Condizione visibilità</label>
                <div id="jl-show-${link.id}" class="jl-editor-host"></div>
            </div>
            <div class="field">
                <label>Condizione esecuzione</label>
                <div id="jl-exec-${link.id}" class="jl-editor-host"></div>
            </div>
            <div class="field">
                <label>Condizione uscita stato</label>
                <div id="jl-exit-${link.id}" class="jl-editor-host"></div>
            </div>
            <div class="field">
                <label>Redirect (route name)</label>
                <input type="text" value="${esc(m.redirect || '')}"
                    onchange="RailEditor.updateLinkMeta(${link.id},'redirect',this.value||null)">
            </div>

            <div class="section-title">Permessi visualizzazione</div>
            <div class="field">
                <label>Permessi view (separati da virgola)</label>
                <input type="text" placeholder="es. admin, view-orders"
                    value="${esc((m.view_permissions||[]).join(','))}"
                    oninput="RailEditor.updateLinkMeta(${link.id},'view_permissions',this.value.split(',').map(s=>s.trim()).filter(Boolean))">
            </div>
            <div class="field">
                <label>Operatore view</label>
                <select onchange="RailEditor.updateLinkMeta(${link.id},'view_operator',this.value)">
                    <option value="OR" ${(m.view_operator||'OR')==='OR'?'selected':''}>OR – basta uno</option>
                    <option value="AND" ${(m.view_operator||'OR')==='AND'?'selected':''}>AND – tutti richiesti</option>
                </select>
            </div>

            <div class="section-title">Permessi avanzamento</div>
            <div class="field">
                <label>Permessi advance (separati da virgola)</label>
                <input type="text" placeholder="es. approve-orders"
                    value="${esc((m.advance_permissions||[]).join(','))}"
                    oninput="RailEditor.updateLinkMeta(${link.id},'advance_permissions',this.value.split(',').map(s=>s.trim()).filter(Boolean))">
            </div>
            <div class="field">
                <label>Operatore advance</label>
                <select onchange="RailEditor.updateLinkMeta(${link.id},'advance_operator',this.value)">
                    <option value="OR" ${(m.advance_operator||'OR')==='OR'?'selected':''}>OR – basta uno</option>
                    <option value="AND" ${(m.advance_operator||'OR')==='AND'?'selected':''}>AND – tutti richiesti</option>
                </select>
            </div>

            <div class="section-title">Azioni pre-transizione</div>
            <div id="al-pre">${renderActionList(
                (m.actions||[]).filter(a=>a.phase==='pre'),
                `RailEditor.removeLinkAction(${link.id},'pre',IDX)`
            )}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;"
                onclick="RailEditor.addLinkAction(${link.id},'pre')">+ Azione pre</button>

            <div class="section-title">Azioni post-transizione</div>
            <div id="al-post">${renderActionList(
                (m.actions||[]).filter(a=>a.phase==='post'),
                `RailEditor.removeLinkAction(${link.id},'post',IDX)`
            )}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;"
                onclick="RailEditor.addLinkAction(${link.id},'post')">+ Azione post</button>

            <hr>
            ${renderFormSection(link.id)}

            <hr>
            <button class="btn btn-danger" style="width:100%;"
                onclick="RailEditor.deleteLink(${link.id})">🗑 Elimina transizione</button>
        `);

        // Initialise jsonlogic editors after panel DOM is ready
        const jlVars = buildJLVarList();
        initJLEditor('jl-show-' + link.id, link.id, 'show_condition',    m.show_condition,    jlVars);
        initJLEditor('jl-exec-' + link.id, link.id, 'execute_condition', m.execute_condition, jlVars);
        initJLEditor('jl-exit-' + link.id, link.id, 'exit_condition',    m.exit_condition,    jlVars);
    }

    function renderActionList(actions, removeCallbackTemplate) {
        if (!actions || !actions.length) return '<p style="color:#64748b;font-size:12px;margin-bottom:8px;">Nessuna azione</p>';
        return actions.map((a, i) => {
            const cb = removeCallbackTemplate.replace('IDX', i);
            const opts = registeredActions.map(ra =>
                `<option value="${esc(ra.action)}" ${ra.action === a.action ? 'selected' : ''}>${esc(ra.display_name)}</option>`
            ).join('');
            return `<div class="action-item">
                <select onchange="this._actionRef.action=this.value">${opts}</select>
                <button class="btn-remove" onclick="${cb}">✕</button>
            </div>`;
        }).join('');
    }

    // ─────────────────────────────────────────
    // Form Builder helpers
    // ─────────────────────────────────────────

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
        const meta     = transitionMeta[linkId] || {};
        const formType = meta.form_type || '';
        const formData = meta.form_data || '';

        let inner = '';
        if (formType === 'json') inner = renderJsonBuilder(linkId);
        if (formType === 'html') inner = renderHtmlEditor(linkId, formData);

        const previewBtn = formType
            ? `<button class="btn btn-secondary" style="width:100%;font-size:11px;padding:4px;margin-top:6px;"
                   onclick="RailEditor.toggleFormPreview(${linkId})">👁 Anteprima</button>
               <div id="fp-${linkId}" style="display:none;"></div>`
            : '';

        return `
            <div class="coll-header open" id="coll-h-form-${linkId}" onclick="toggleColl('form-${linkId}')">
                <span class="coll-arrow">▶</span> Form Transizione
            </div>
            <div class="coll-body open" id="coll-b-form-${linkId}">
                <div class="field" style="margin-top:8px;">
                    <label>Tipo</label>
                    <select onchange="RailEditor.setFormType(${linkId},this.value)">
                        <option value=""       ${!formType          ? 'selected' : ''}>Nessuna form</option>
                        <option value="json"   ${formType==='json'  ? 'selected' : ''}>JSON Schema (dinamica)</option>
                        <option value="html"   ${formType==='html'  ? 'selected' : ''}>HTML Raw</option>
                    </select>
                </div>
                ${inner}
                ${previewBtn}
            </div>
        `;
    }

    function renderJsonBuilder(linkId) {
        const schema = getFormSchema(linkId);
        const cards  = schema.length === 0
            ? '<p style="color:#64748b;font-size:12px;margin:0 0 6px;">Nessun campo</p>'
            : schema.map((f, i) => renderFieldCard(linkId, f, i, schema.length)).join('');
        return `
            <div id="fb-${linkId}">${cards}</div>
            <button class="btn btn-secondary" style="font-size:11px;padding:4px 8px;margin-top:2px;"
                onclick="RailEditor.addFormField(${linkId})">+ Campo</button>
        `;
    }

    function renderHtmlEditor(linkId, formData) {
        return `
            <div class="field">
                <label>HTML della form (senza &lt;form&gt; esterno)</label>
                <textarea rows="9" style="font-family:monospace;font-size:11px;"
                    placeholder="&lt;div class=&quot;mb-3&quot;&gt;&lt;label&gt;...&lt;/label&gt;&lt;input name=&quot;x&quot;&gt;&lt;/div&gt;"
                    onblur="RailEditor.updateLinkMeta(${linkId},'form_data',this.value||null)">${esc(formData)}</textarea>
            </div>
        `;
    }

    function renderFieldCard(linkId, f, idx, total) {
        const TYPES = ['text','email','number','password','textarea','select','checkbox','radio','date','hidden'];
        const typeOpts = TYPES.map(t =>
            `<option value="${t}" ${(f.type||'text')===t ? 'selected':''}>${t}</option>`
        ).join('');

        const hasOpts = ['select','radio'].includes(f.type || 'text');
        const optVal  = hasOpts && f.options
            ? f.options.map(o => `${o.value}|${o.label}`).join('\n')
            : '';
        const optsHtml = hasOpts ? `
            <div style="margin-top:4px;">
                <div style="font-size:10px;color:#64748b;margin-bottom:2px;">Opzioni (valore|Label, uno per riga)</div>
                <textarea rows="3" style="font-family:monospace;font-size:10px;"
                    onblur="RailEditor.updateFormFieldOptions(${linkId},${idx},this.value)">${esc(optVal)}</textarea>
            </div>` : '';

        return `
            <div class="ff-card" id="ffc-${linkId}-${idx}">
                <div class="ff-row">
                    <select onchange="RailEditor.updateFormField(${linkId},${idx},'type',this.value)">${typeOpts}</select>
                    <input type="text" placeholder="name" value="${esc(f.name||'')}"
                        oninput="RailEditor.updateFormField(${linkId},${idx},'name',this.value)">
                    <button class="ff-move" onclick="RailEditor.moveFormField(${linkId},${idx},-1)" ${idx===0?'disabled':''}>↑</button>
                    <button class="ff-move" onclick="RailEditor.moveFormField(${linkId},${idx},1)" ${idx===total-1?'disabled':''}>↓</button>
                    <button class="btn-remove" onclick="RailEditor.removeFormField(${linkId},${idx})">✕</button>
                </div>
                <input type="text" placeholder="Label visualizzata" value="${esc(f.label||'')}"
                    oninput="RailEditor.updateFormField(${linkId},${idx},'label',this.value)">
                <input type="text" placeholder="Placeholder" value="${esc(f.placeholder||'')}"
                    oninput="RailEditor.updateFormField(${linkId},${idx},'placeholder',this.value)">
                <input type="text" placeholder="Validazione Laravel (es. min:3|max:255)" value="${esc(f.validation||'')}"
                    oninput="RailEditor.updateFormField(${linkId},${idx},'validation',this.value)">
                <label class="ff-check">
                    <input type="checkbox" ${f.required?'checked':''}
                        onchange="RailEditor.updateFormField(${linkId},${idx},'required',this.checked)">
                    Obbligatorio
                </label>
                ${optsHtml}
            </div>
        `;
    }

    function rebuildFormBuilder(linkId) {
        const listEl = document.getElementById('fb-' + linkId);
        if (!listEl) return;
        const schema = getFormSchema(linkId);
        listEl.innerHTML = schema.length === 0
            ? '<p style="color:#64748b;font-size:12px;margin:0 0 6px;">Nessun campo</p>'
            : schema.map((f, i) => renderFieldCard(linkId, f, i, schema.length)).join('');
        const prevEl = document.getElementById('fp-' + linkId);
        if (prevEl) prevEl.style.display = 'none';
    }

    function buildFormPreview(linkId) {
        const meta = transitionMeta[linkId] || {};
        if (!meta.form_type) return '<p style="color:#999;font-size:12px;">Nessuna form</p>';

        if (meta.form_type === 'html') {
            return meta.form_data || '<p style="color:#999;font-size:12px;">HTML vuoto</p>';
        }

        const schema = getFormSchema(linkId);
        if (!schema.length) return '<p style="color:#999;font-size:12px;">Nessun campo</p>';

        return schema.map(f => {
            if (!f.name) return '';
            const label = esc(f.label || f.name);
            const req   = f.required ? '<span class="fp-req">*</span>' : '';

            switch (f.type || 'text') {
                case 'textarea':
                    return `<div class="fp-field"><span class="fp-label">${label} ${req}</span><textarea class="fp-input" rows="2" placeholder="${esc(f.placeholder||'')}" disabled></textarea></div>`;
                case 'select': {
                    const opts = (f.options||[]).map(o => `<option>${esc(o.label||o.value)}</option>`).join('');
                    return `<div class="fp-field"><span class="fp-label">${label} ${req}</span><select class="fp-input" disabled><option>— seleziona —</option>${opts}</select></div>`;
                }
                case 'radio':
                    return `<div class="fp-field"><span class="fp-label">${label} ${req}</span>`
                        + (f.options||[]).map(o => `<label style="display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:12px;"><input type="radio" disabled> ${esc(o.label||o.value)}</label>`).join('')
                        + `</div>`;
                case 'checkbox':
                    return `<div class="fp-field"><label style="display:flex;align-items:center;gap:6px;font-size:12px;"><input type="checkbox" disabled> ${label} ${req}</label></div>`;
                case 'hidden':
                    return `<div class="fp-field" style="color:#999;font-size:11px;">[hidden: ${esc(f.name)}]</div>`;
                default:
                    return `<div class="fp-field"><span class="fp-label">${label} ${req}</span><input type="${esc(f.type||'text')}" class="fp-input" placeholder="${esc(f.placeholder||'')}" disabled></div>`;
            }
        }).join('');
    }

    function toggleColl(id) {
        const h = document.getElementById('coll-h-' + id);
        const b = document.getElementById('coll-b-' + id);
        if (!h || !b) return;
        h.classList.toggle('open');
        b.classList.toggle('open');
    }

    // --- Public API ---
    window.RailEditor = {
        addState() {
            const node = LiteGraph.createNode('workflow/state');
            const center = canvas.convertOffsetToCanvas([cvs.width / 2, cvs.height / 2]);
            node.pos = [center[0] - 100 + Math.random() * 40 - 20,
                        center[1] - 36 + Math.random() * 40 - 20];
            node.properties.name = 'Nuovo Stato ' + (graph._nodes.length + 1);
            graph.add(node);
            canvas.selectNode(node);
            setStatus('Nodo aggiunto');
        },

        updateNode(nodeId, prop, value) {
            const node = graph.getNodeById(nodeId);
            if (node) {
                node.properties[prop] = value;
                graph.setDirtyCanvas(true);
            }
        },

        deleteNode(nodeId) {
            const node = graph.getNodeById(nodeId);
            if (node && confirm('Eliminare lo stato "' + node.properties.name + '"?')) {
                graph.remove(node);
                panel('<p class="placeholder">Seleziona un nodo o una connessione per modificarne le proprietà.</p>');
                setStatus('Nodo eliminato');
            }
        },

        updateLinkMeta(linkId, prop, value) {
            if (!transitionMeta[linkId]) transitionMeta[linkId] = {};
            transitionMeta[linkId][prop] = value;
        },

        deleteLink(linkId) {
            const link = graph.links[linkId];
            if (link && confirm('Eliminare questa transizione?')) {
                graph.removeLink(linkId);
                panel('<p class="placeholder">Seleziona un nodo o una connessione per modificarne le proprietà.</p>');
                setStatus('Transizione eliminata');
            }
        },

        addStateAction(nodeId, phase) {
            const node = graph.getNodeById(nodeId);
            if (!node || !registeredActions.length) {
                alert('Nessuna azione registrata disponibile.');
                return;
            }
            node.properties[phase].push({ action: registeredActions[0].action, configuration: null });
            renderNodePanel(node);
        },

        removeStateAction(nodeId, phase, idx) {
            const node = graph.getNodeById(nodeId);
            if (node) {
                node.properties[phase].splice(idx, 1);
                renderNodePanel(node);
            }
        },

        addLinkAction(linkId, phase) {
            if (!registeredActions.length) {
                alert('Nessuna azione registrata disponibile.');
                return;
            }
            if (!transitionMeta[linkId]) transitionMeta[linkId] = { actions: [] };
            if (!transitionMeta[linkId].actions) transitionMeta[linkId].actions = [];
            transitionMeta[linkId].actions.push({ phase, action: registeredActions[0].action, configuration: null });
            renderLinkPanel(graph.links[linkId]);
        },

        removeLinkAction(linkId, phase, idx) {
            const meta = transitionMeta[linkId];
            if (!meta || !meta.actions) return;
            const phaseActions = meta.actions.filter(a => a.phase === phase);
            const globalIdx    = meta.actions.indexOf(phaseActions[idx]);
            if (globalIdx !== -1) {
                meta.actions.splice(globalIdx, 1);
            }
            renderLinkPanel(graph.links[linkId]);
        },

        // ── Conditional node ──
        addConditional() {
            const node   = LiteGraph.createNode('workflow/conditional');
            const center = canvas.convertOffsetToCanvas([cvs.width / 2, cvs.height / 2]);
            node.pos     = [center[0] - 105 + Math.random() * 40 - 20,
                            center[1] - 50  + Math.random() * 40 - 20];
            node.properties.name = 'Condizione ' + (graph._nodes.length + 1);
            graph.add(node);
            canvas.selectNode(node);
            setStatus('Nodo condizionale aggiunto');
        },

        addConditionalBranch(nodeId) {
            const node = graph.getNodeById(nodeId);
            if (!node) return;
            const n = node.outputs ? node.outputs.length + 1 : 3;
            node.addOutput('Ramo ' + n, 'flow');
            node._resizeToSlots();
            graph.setDirtyCanvas(true);
            renderConditionalNodePanel(node);
        },

        removeConditionalBranch(nodeId, slotIdx) {
            const node = graph.getNodeById(nodeId);
            if (!node || !node.outputs || node.outputs.length <= 2) return;
            // Disconnect any link on this slot first
            const slot = node.outputs[slotIdx];
            if (slot && slot.links) {
                [...slot.links].forEach(id => graph.removeLink(id));
            }
            node.removeOutput(slotIdx);
            node._resizeToSlots();
            graph.setDirtyCanvas(true);
            renderConditionalNodePanel(node);
        },

        renameConditionalBranch(nodeId, slotIdx, newName) {
            const node = graph.getNodeById(nodeId);
            if (!node || !node.outputs || !node.outputs[slotIdx]) return;
            node.outputs[slotIdx].name = newName || ('Ramo ' + (slotIdx + 1));
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

        addFormField(linkId) {
            const schema = getFormSchema(linkId);
            schema.push({ type: 'text', name: '', label: '', placeholder: '', required: false, validation: '' });
            setFormSchema(linkId, schema);
            rebuildFormBuilder(linkId);
        },

        removeFormField(linkId, idx) {
            const schema = getFormSchema(linkId);
            schema.splice(idx, 1);
            setFormSchema(linkId, schema);
            rebuildFormBuilder(linkId);
        },

        updateFormField(linkId, idx, key, value) {
            const schema = getFormSchema(linkId);
            if (!schema[idx]) return;
            schema[idx][key] = value;
            setFormSchema(linkId, schema);
            if (key === 'type') rebuildFormBuilder(linkId); // re-render to show/hide options
        },

        updateFormFieldOptions(linkId, idx, rawText) {
            const schema = getFormSchema(linkId);
            if (!schema[idx]) return;
            schema[idx].options = (rawText || '').split('\n')
                .map(l => l.trim()).filter(Boolean)
                .map(l => { const p = l.split('|'); return { value: p[0].trim(), label: (p[1] || p[0]).trim() }; });
            setFormSchema(linkId, schema);
        },

        moveFormField(linkId, idx, dir) {
            const schema = getFormSchema(linkId);
            const newIdx = idx + dir;
            if (newIdx < 0 || newIdx >= schema.length) return;
            [schema[idx], schema[newIdx]] = [schema[newIdx], schema[idx]];
            setFormSchema(linkId, schema);
            rebuildFormBuilder(linkId);
        },

        toggleFormPreview(linkId) {
            const el = document.getElementById('fp-' + linkId);
            if (!el) return;
            if (el.style.display === 'none') {
                el.style.display = 'block';
                el.innerHTML = `<div class="form-preview-wrap">${buildFormPreview(linkId)}</div>`;
            } else {
                el.style.display = 'none';
            }
        },

        fitGraph() {
            canvas.ds.reset();
            if (graph._nodes.length) canvas.fitToContents();
        },

        async save() {
            setStatus('Salvataggio...');
            const states = buildPayload();

            try {
                const resp = await fetch(API_URL, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ states }),
                });

                if (!resp.ok) {
                    const err = await resp.json().catch(() => ({}));
                    throw new Error(err.message || 'HTTP ' + resp.status);
                }

                const result = await resp.json();
                // Update node IDs from server response
                for (const savedState of (result.states || [])) {
                    const node = findNodeByName(savedState.name);
                    if (node) node.properties.id = savedState.id;
                }

                setStatus('✓ Salvato', 'ok');
            } catch (e) {
                setStatus('✗ ' + e.message, 'err');
            }
        },
    };

    // --- Serialization helpers ---
    function buildPayload() {
        const TYPES = ['workflow/state', 'workflow/conditional'];
        return graph._nodes
            .filter(n => TYPES.includes(n.type))
            .map(node => {
                const p           = node.properties;
                const isConditional = node.type === 'workflow/conditional';
                const transitions = [];

                if (isConditional) {
                    // Each output slot = one branch = one transition (slot index = sort order)
                    (node.outputs || []).forEach((slot, slotIdx) => {
                        if (!slot.links || !slot.links.length) return;
                        // A conditional branch connects to exactly one destination
                        const linkId   = slot.links[0];
                        const link     = graph.links[linkId];
                        if (!link) return;
                        const destNode = graph.getNodeById(link.target_id);
                        if (!destNode) return;
                        const meta = transitionMeta[linkId] || {};
                        transitions.push({
                            to_id:               destNode.properties.id || destNode.properties.name,
                            sort:                slotIdx,
                            label:               slot.name || ('Ramo ' + (slotIdx + 1)),
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
                            actions:             (meta.actions || []).map((a, i) => ({
                                sort: i, phase: a.phase, action: a.action,
                                configuration: a.configuration || null,
                            })),
                        });
                    });
                } else {
                    // Simple state: single output slot, multiple links allowed
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

    function isConditionalNode(n) { return n.type === 'workflow/conditional'; }

    // --- Load ---
    async function loadWorkflow() {
        setStatus('Caricamento workflow...');
        try {
            // Load registered actions first
            const raResp = await fetch(API_ACTIONS_URL, { headers: { Accept: 'application/json' } });
            if (raResp.ok) registeredActions = await raResp.json();

            const resp = await fetch(API_URL, { headers: { Accept: 'application/json' } });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const data = await resp.json();

            graph.clear();

            const nodeById = {};

            for (const state of (data.states || [])) {
                const isConditional = (state.type === 'conditional');
                const litegraphType = isConditional ? 'workflow/conditional' : 'workflow/state';
                const node          = LiteGraph.createNode(litegraphType);

                node.pos = [state.x || 0, state.y || 0];
                node.properties = {
                    id:               state.id,
                    name:             state.name,
                    type:             state.type || 'simple',
                    code:             state.code || '',
                    slug:             state.slug || '',
                    is_start:         !!state.is_start,
                    is_end:           !!state.is_end,
                    on_enter_actions: state.on_enter_actions || [],
                    on_exit_actions:  state.on_exit_actions  || [],
                    view_permissions: state.view_permissions || [],
                    view_operator:    state.view_operator    || 'OR',
                };

                if (isConditional) {
                    // Resize output slots to match the number of transitions
                    const tCount = (state.transitions || []).length;
                    // Node already has 2 outputs from constructor; add/remove to match
                    while ((node.outputs || []).length < Math.max(2, tCount)) {
                        const n = (node.outputs || []).length + 1;
                        node.addOutput('Ramo ' + n, 'flow');
                    }
                    // Set slot names from transition labels
                    (state.transitions || []).forEach((t, i) => {
                        if (node.outputs[i]) node.outputs[i].name = t.label || ('Ramo ' + (i + 1));
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

                // Sort transitions by sort field (ensures correct slot-index alignment for conditional)
                const transitions = [...(state.transitions || [])].sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0));

                for (const [idx, t] of transitions.entries()) {
                    const dstNode = nodeById[t.to_id];
                    if (!dstNode) continue;

                    const srcSlot = isConditional ? idx : 0;
                    srcNode.connect(srcSlot, dstNode, 0);

                    const newLink = getNewestLink(graph);
                    if (newLink) {
                        transitionMeta[newLink.id] = {
                            show_condition:      t.show_condition      || null,
                            execute_condition:   t.execute_condition   || null,
                            exit_condition:      t.exit_condition      || null,
                            permission:          t.permission          || null,
                            redirect:            t.redirect            || null,
                            actions:             t.actions             || [],
                            form_type:           t.form_type           || null,
                            form_data:           t.form_data           || null,
                            view_permissions:    t.view_permissions    || [],
                            view_operator:       t.view_operator       || 'OR',
                            advance_permissions: t.advance_permissions || [],
                            advance_operator:    t.advance_operator    || 'OR',
                        };
                    }
                }
            }

            // Auto-layout if all positions are zero
            const allZero = (data.states || []).every(s => !s.x && !s.y);
            if (allZero) autoLayout();

            canvas.ds.reset();
            if (graph._nodes.length) canvas.fitToContents();
            setStatus('✓ Workflow caricato', 'ok');
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
        const cols   = Math.ceil(Math.sqrt(graph._nodes.length));
        const padX   = 260, padY = 120;
        graph._nodes.forEach((n, i) => {
            n.pos = [(i % cols) * padX + 50, Math.floor(i / cols) * padY + 50];
        });
    }

    // --- Utility ---
    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function tryParseJson(s) {
        if (!s || !s.trim()) return null;
        try { return JSON.parse(s); } catch (_) { return null; }
    }

    // ─── JsonLogic editor helpers ─────────────────────────────────────────────

    function buildJLVarList() {
        const base = [
            { value: 'variables.status',   label: 'variables.status' },
            { value: 'variables.approved', label: 'variables.approved' },
            { value: 'variables.amount',   label: 'variables.amount' },
            { value: 'variables.count',    label: 'variables.count' },
            { value: 'variables.user_id',  label: 'variables.user_id' },
            { value: 'entity.id',          label: 'entity.id' },
            { value: 'entity.status',      label: 'entity.status' },
            { value: 'request.action',     label: 'request.action' },
        ];
        // Also add any variables seen across loaded nodes
        graph._nodes.forEach(function (n) {
            if (n.type !== 'workflow/state') return;
            const actions = [
                ...(n.properties.on_enter_actions || []),
                ...(n.properties.on_exit_actions  || []),
            ];
            // no-op: we don't have variable schema from nodes, just keep base list
        });
        return base;
    }

    function initJLEditor(divId, linkId, condField, currentValue, vars) {
        if (typeof jQuery === 'undefined') return;
        const $el = jQuery('#' + divId);
        if (!$el.length) return;

        $el.jsonlogicUI({
            variables  : vars,
            value      : currentValue || null,
            input_name : divId,
        }).on('jsonlogicui:change', function () {
            const raw = jQuery(this).find('> input[type=hidden]').val() || '';
            let parsed = null;
            try { if (raw) parsed = JSON.parse(raw); } catch (_) {}
            RailEditor.updateLinkMeta(linkId, condField, parsed);
        });
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y);
        ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        ctx.lineTo(x + w, y + h - r);
        ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        ctx.lineTo(x + r, y + h);
        ctx.quadraticCurveTo(x, y + h, x, y + h - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    // --- Start ---
    graph.start(30);
    loadWorkflow();

    // Resize canvas to fill container
    function resizeCanvas() {
        const wrap = document.getElementById('rail-canvas-wrap');
        cvs.width  = wrap.clientWidth;
        cvs.height = wrap.clientHeight;
        canvas.dirty_canvas = true;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
})();
</script>
</body>
</html>
