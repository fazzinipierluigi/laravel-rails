<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Rails — Esecuzione</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litegraph.js@0.7.18/css/litegraph.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            width: 100%; height: 100%;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
        }

        #rail-exec {
            display: flex;
            flex-direction: column;
            width: 100vw;
            height: 100vh;
        }

        /* ── Toolbar ── */
        #exec-toolbar {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            background: #1e293b;
            border-bottom: 1px solid #334155;
            z-index: 10;
        }
        #exec-toolbar h2 {
            font-size: 13px; font-weight: 700; color: #94a3b8;
            letter-spacing: .05em; text-transform: uppercase; margin-right: 4px;
        }
        #exec-title { font-size: 13px; color: #e2e8f0; font-weight: 600; }
        #exec-status { font-size: 12px; color: #64748b; margin-left: auto; }

        .legend-item {
            display: flex; align-items: center; gap: 5px;
            font-size: 11px; color: #94a3b8;
        }
        .legend-dot {
            width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0;
        }

        .btn {
            padding: 5px 12px; border: none; border-radius: 6px;
            font-size: 12px; font-weight: 600; cursor: pointer;
            background: #475569; color: #e2e8f0; transition: opacity .15s;
        }
        .btn:hover { opacity: .8; }

        /* ── Canvas ── */
        #exec-canvas-wrap {
            flex: 1;
            position: relative;
            overflow: hidden;
        }
        #exec-canvas {
            display: block;
            width: 100%;
            height: 100%;
        }

        /* ── Modal ── */
        #exec-modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.65);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        #exec-modal-overlay.open { display: flex; }

        #exec-modal {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            width: min(780px, 96vw);
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,.6);
        }

        #exec-modal-header {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            border-bottom: 1px solid #334155;
            gap: 10px;
        }
        #exec-modal-title {
            flex: 1;
            font-size: 14px; font-weight: 700; color: #e2e8f0;
        }
        #exec-modal-close {
            background: none; border: none; color: #64748b;
            font-size: 18px; cursor: pointer; line-height: 1;
        }
        #exec-modal-close:hover { color: #e2e8f0; }

        #exec-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px 18px;
        }

        /* ── Log entries ── */
        .log-entry {
            margin-bottom: 12px;
            padding: 10px 12px;
            border-radius: 6px;
            border-left: 3px solid #334155;
            background: #0f172a;
            font-size: 12px;
        }
        .log-entry.success { border-left-color: #22c55e; }
        .log-entry.failure { border-left-color: #ef4444; background: #1a0a0a; }
        .log-entry.skipped { border-left-color: #f59e0b; }
        .log-entry.info    { border-left-color: #3b82f6; }
        .log-entry.cond    { border-left-color: #a855f7; }
        .log-entry.auto    { border-left-color: #06b6d4; }

        .log-event {
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .05em; color: #64748b; margin-bottom: 4px;
        }
        .log-time {
            font-size: 10px; color: #475569; float: right;
        }
        .log-detail {
            margin-top: 6px;
        }
        .log-row {
            display: flex; gap: 6px; margin-bottom: 3px;
            align-items: baseline;
        }
        .log-label {
            font-size: 11px; font-weight: 600; color: #64748b;
            white-space: nowrap; min-width: 130px;
        }
        .log-value {
            font-size: 11px; color: #cbd5e1; word-break: break-all;
        }
        .log-value.ok    { color: #4ade80; }
        .log-value.err   { color: #f87171; font-weight: 600; }
        .log-value.warn  { color: #fbbf24; }
        .log-value.code  { font-family: monospace; background: #0f172a; padding: 1px 4px; border-radius: 3px; }

        .log-stack {
            font-family: monospace; font-size: 10px; color: #f87171;
            background: #200a0a; padding: 8px; border-radius: 4px;
            overflow-x: auto; white-space: pre; margin-top: 6px;
            max-height: 150px; overflow-y: auto;
        }
        .log-json {
            font-family: monospace; font-size: 10px; color: #94a3b8;
            background: #0f172a; padding: 8px; border-radius: 4px;
            overflow-x: auto; white-space: pre; margin-top: 4px;
        }

        .log-section-title {
            font-size: 11px; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: .05em;
            margin: 14px 0 6px; border-bottom: 1px solid #1e293b; padding-bottom: 4px;
        }

        .empty-state {
            text-align: center; color: #475569; font-size: 13px;
            padding: 24px;
        }

        /* ── Scrollbar ── */
        #exec-modal-body::-webkit-scrollbar { width: 6px; }
        #exec-modal-body::-webkit-scrollbar-track { background: #0f172a; }
        #exec-modal-body::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
    </style>
</head>
<body>
<div id="rail-exec">
    <div id="exec-toolbar">
        <h2>Esecuzione</h2>
        <span id="exec-title">Caricamento...</span>
        <div class="legend-item"><div class="legend-dot" style="background:#1d4ed8"></div> Corrente</div>
        <div class="legend-item"><div class="legend-dot" style="background:#166534"></div> Percorso</div>
        <div class="legend-item"><div class="legend-dot" style="background:#991b1b"></div> Errore</div>
        <div class="legend-item"><div class="legend-dot" style="background:#1e293b;border:1px solid #334155"></div> Non raggiunto</div>
        <button class="btn" onclick="ExecViewer.fit()">⊡ Fit</button>
        <span id="exec-status">Caricamento...</span>
    </div>
    <div id="exec-canvas-wrap">
        <canvas id="exec-canvas"></canvas>
    </div>
</div>

<!-- Detail modal -->
<div id="exec-modal-overlay">
    <div id="exec-modal">
        <div id="exec-modal-header">
            <div id="exec-modal-title">Dettagli</div>
            <button id="exec-modal-close" onclick="ExecViewer.closeModal()">✕</button>
        </div>
        <div id="exec-modal-body">
            <div class="empty-state">Caricamento...</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/litegraph.js@0.7.18/build/litegraph.js"></script>
<script>
(function () {
    'use strict';

    const DATA_URL  = @json(route('laravel-rails.execution.data', ['instanceId' => $instanceId]));
    const LOGS_BASE = @json(url('laravel-rails/api/execution/' . $instanceId));

    function logsUrl(type, subjectId) {
        return LOGS_BASE + '/' + type + '/' + subjectId;
    }

    // ── Traversal data (populated after load) ──
    let traversed = { states: [], transitions: [], errors: { states: [], transitions: [] } };
    let currentStateId = null;

    // ── Link → transition ID mapping ──
    const linkToTransitionId = {};

    // ── Status helpers ──
    function stateStatus(stateId) {
        if (stateId === currentStateId)         return 'current';
        if (traversed.errors.states.includes(stateId)) return 'error';
        if (traversed.states.includes(stateId))        return 'visited';
        return 'unreached';
    }

    function transitionStatus(transitionId) {
        if (traversed.errors.transitions.includes(transitionId)) return 'error';
        if (traversed.transitions.includes(transitionId))        return 'visited';
        return 'unreached';
    }

    const STATUS_NODE_COLORS = {
        current:  { color: '#1d4ed8', bgColor: '#1e3a8a' },
        visited:  { color: '#14532d', bgColor: '#166534' },
        error:    { color: '#7f1d1d', bgColor: '#991b1b' },
        unreached:{ color: '#1e293b', bgColor: '#0f172a' },
    };

    const STATUS_LINK_COLORS = {
        current:  '#60a5fa',
        visited:  '#22c55e',
        error:    '#ef4444',
        unreached:'#334155',
    };

    // ── LiteGraph setup ──
    LiteGraph.debug = false;
    LiteGraph.node_images_path = '';

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

    // WorkflowStateNode (execution read-only)
    function ExecStateNode() {
        this.size     = [200, 72];
        this.addInput('', 'flow');
        this.addOutput('', 'flow');
        this.properties = { id: null, name: 'State', code: '', is_start: false, is_end: false };
        this.color    = '#1e293b';
        this.bgColor  = '#0f172a';
        this.shape    = LiteGraph.ROUND_SHAPE;
    }
    ExecStateNode.title = 'State';
    ExecStateNode.prototype.onExecute = function () {};
    ExecStateNode.prototype.onDrawForeground = function (ctx) {
        const p      = this.properties;
        const status = p.id ? stateStatus(p.id) : 'unreached';
        const colors = STATUS_NODE_COLORS[status];

        // Status overlay
        const overlayMap = {
            current:  'rgba(29,78,216,.30)',
            visited:  'rgba(20,83,45,.30)',
            error:    'rgba(127,29,29,.35)',
            unreached:'rgba(30,41,59,.40)',
        };
        ctx.fillStyle = overlayMap[status] || '';
        ctx.fillRect(0, 0, this.size[0], this.size[1]);

        // Name
        ctx.font      = 'bold 13px sans-serif';
        ctx.fillStyle = p.is_start ? '#4ade80' : p.is_end ? '#f87171' : '#e2e8f0';
        ctx.fillText((p.name || 'State').substring(0, 24), 8, 26);

        if (p.code) {
            ctx.font      = '11px monospace';
            ctx.fillStyle = '#94a3b8';
            ctx.fillText(p.code, 8, 44);
        }

        // Badges
        if (p.is_start) {
            ctx.fillStyle = '#16a34a';
            roundRect(ctx, this.size[0] - 48, 8, 42, 16, 4);
            ctx.fill();
            ctx.fillStyle = '#fff'; ctx.font = '9px sans-serif';
            ctx.fillText('START', this.size[0] - 43, 19);
        }
        if (p.is_end) {
            ctx.fillStyle = '#dc2626';
            roundRect(ctx, this.size[0] - 42, 8, 36, 16, 4);
            ctx.fill();
            ctx.fillStyle = '#fff'; ctx.font = '9px sans-serif';
            ctx.fillText('END', this.size[0] - 35, 19);
        }

        // Status badge
        const statusLabel = { current: 'CORRENTE', visited: 'PERCORSO', error: 'ERRORE', unreached: '' }[status];
        if (statusLabel) {
            ctx.fillStyle = colors.color;
            roundRect(ctx, 6, this.size[1] - 18, statusLabel.length * 6.5 + 6, 14, 3);
            ctx.fill();
            ctx.fillStyle = '#fff'; ctx.font = '8px sans-serif';
            ctx.fillText(statusLabel, 9, this.size[1] - 7);
        }

        // Hint: click for details
        if (status !== 'unreached') {
            ctx.font      = '9px sans-serif';
            ctx.fillStyle = 'rgba(100,116,139,.6)';
            ctx.fillText('clicca per dettagli →', this.size[0] - 110, this.size[1] - 6);
        }
    };
    ExecStateNode.prototype.onSelected = function () {
        if (this.properties.id) ExecViewer.showStateModal(this.properties.id, this.properties.name);
    };
    LiteGraph.registerNodeType('exec/state', ExecStateNode);

    // WorkflowConditionalNode (execution read-only)
    function ExecConditionalNode() {
        this.addInput('', 'flow');
        this.addOutput('Ramo 1', 'flow');
        this.addOutput('Ramo 2', 'flow');
        this.properties = { id: null, name: 'Condizione', code: '', is_start: false, is_end: false };
        this.color   = '#422006';
        this.bgColor = '#1c1100';
        this.shape   = LiteGraph.ROUND_SHAPE;
        this._resizeToSlots();
    }
    ExecConditionalNode.title = 'Conditional';
    ExecConditionalNode.prototype._resizeToSlots = function () {
        const slots = this.outputs ? this.outputs.length : 2;
        this.size = [210, 46 + slots * 22];
    };
    ExecConditionalNode.prototype.onExecute = function () {};
    ExecConditionalNode.prototype.onDrawForeground = function (ctx) {
        const p  = this.properties;
        const cx = this.size[0] / 2;

        ctx.fillStyle = '#fbbf24';
        ctx.beginPath();
        ctx.moveTo(cx, 8); ctx.lineTo(cx + 10, 18);
        ctx.lineTo(cx, 28); ctx.lineTo(cx - 10, 18);
        ctx.closePath(); ctx.fill();

        ctx.font = 'bold 12px sans-serif'; ctx.fillStyle = '#fde68a';
        ctx.fillText((p.name || 'Condizione').substring(0, 24), 8, 42);

        if (p.code) {
            ctx.font = '10px monospace'; ctx.fillStyle = '#92400e';
            ctx.fillText(p.code, 8, 54);
        }
    };
    ExecConditionalNode.prototype.onSelected = function () {
        if (this.properties.id) ExecViewer.showStateModal(this.properties.id, this.properties.name);
    };
    LiteGraph.registerNodeType('exec/conditional', ExecConditionalNode);

    // ── Graph & Canvas ──
    const graph  = new LGraph();
    const cvs    = document.getElementById('exec-canvas');
    const canvas = new LGraphCanvas(cvs, graph);

    canvas.background_image   = null;
    canvas.clear_background   = true;
    canvas.clear_background_color = '#0f172a';
    canvas.render_connection_arrows = true;
    canvas.connections_width  = 2;
    canvas.default_link_color = '#334155';

    // Intercept link click → show transition modal
    canvas.onShowLinkMenu = function (link) {
        const transitionId = linkToTransitionId[link.id];
        if (transitionId) ExecViewer.showTransitionModal(transitionId, link);
        return false;
    };

    // Disable editing context menus
    canvas.onShowNodeContextMenu = function () { return false; };

    // ── Load ──
    async function loadExecution() {
        try {
            const resp = await fetch(DATA_URL, { headers: { Accept: 'application/json' } });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const data = await resp.json();

            currentStateId = data.instance.current_state_id;
            traversed      = data.traversed;

            document.getElementById('exec-title').textContent =
                (data.workflow.name || 'Workflow') + ' — istanza ' + data.instance.id.substring(0, 8) + '…';
            document.getElementById('exec-status').textContent =
                'Stato corrente: ' + (data.workflow.states.find(s => s.id === currentStateId)?.name || '—');

            graph.clear();
            const nodeById = {};

            // Create nodes
            for (const state of (data.workflow.states || [])) {
                const isConditional = state.type === 'conditional';
                const node = LiteGraph.createNode(isConditional ? 'exec/conditional' : 'exec/state');
                node.pos = [state.x || 0, state.y || 0];
                node.properties = {
                    id: state.id, name: state.name, code: state.code || '',
                    is_start: !!state.is_start, is_end: !!state.is_end,
                };

                const status = stateStatus(state.id);
                const nc     = STATUS_NODE_COLORS[status];
                node.color   = nc.color;
                node.bgColor = nc.bgColor;

                if (isConditional) {
                    const tCount = (state.transitions || []).length;
                    while ((node.outputs || []).length < Math.max(2, tCount)) {
                        node.addOutput('Ramo ' + ((node.outputs || []).length + 1), 'flow');
                    }
                    (state.transitions || []).forEach((t, i) => {
                        if (node.outputs[i]) node.outputs[i].name = t.label || ('Ramo ' + (i + 1));
                    });
                    node._resizeToSlots();
                }

                graph.add(node);
                nodeById[state.id] = node;
            }

            // Connect edges and assign colors
            for (const state of (data.workflow.states || [])) {
                const srcNode       = nodeById[state.id];
                const isConditional = state.type === 'conditional';
                if (!srcNode) continue;

                const transitions = [...(state.transitions || [])].sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0));

                for (const [idx, t] of transitions.entries()) {
                    const dstNode = nodeById[t.to];
                    if (!dstNode) continue;

                    const srcSlot = isConditional ? idx : 0;
                    srcNode.connect(srcSlot, dstNode, 0);

                    // Map newest link ID → transition ID for modal lookup
                    const newLinkId = getNewestLinkId(graph);
                    if (newLinkId !== null) {
                        linkToTransitionId[newLinkId] = t.id;
                        // Color the link
                        const link = graph.links[newLinkId];
                        if (link) {
                            link.color = STATUS_LINK_COLORS[transitionStatus(t.id)];
                        }
                    }
                }
            }

            // Auto-layout if all zero
            const allZero = (data.workflow.states || []).every(s => !s.x && !s.y);
            if (allZero) autoLayout();

            canvas.ds.reset();
            if (graph._nodes.length) canvas.fitToContents();
            graph.setDirtyCanvas(true, true);

        } catch (e) {
            document.getElementById('exec-status').textContent = 'Errore: ' + e.message;
        }
    }

    function getNewestLinkId(g) {
        const ids = Object.keys(g.links).map(Number);
        return ids.length ? Math.max(...ids) : null;
    }

    function autoLayout() {
        const cols = Math.ceil(Math.sqrt(graph._nodes.length));
        graph._nodes.forEach((n, i) => {
            n.pos = [(i % cols) * 280 + 60, Math.floor(i / cols) * 130 + 60];
        });
    }

    function resizeCanvas() {
        const wrap = document.getElementById('exec-canvas-wrap');
        cvs.width  = wrap.clientWidth;
        cvs.height = wrap.clientHeight;
        canvas.dirty_canvas = true;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
    graph.start(30);
    loadExecution();

    // ── Modal helpers ──

    function esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtTime(ts) {
        if (!ts) return '—';
        const d = new Date(ts);
        return d.toLocaleString('it-IT');
    }

    function fmtData(key, val) {
        if (val === null || val === undefined) return '<span class="log-value" style="color:#475569">—</span>';
        if (typeof val === 'boolean') return `<span class="log-value ${val ? 'ok' : 'err'}">${val ? 'sì' : 'no'}</span>`;
        if (typeof val === 'object') {
            return `<span class="log-json">${esc(JSON.stringify(val, null, 2))}</span>`;
        }
        return `<span class="log-value">${esc(String(val))}</span>`;
    }

    function rowHtml(label, val) {
        return `<div class="log-row"><span class="log-label">${esc(label)}</span>${fmtData(label, val)}</div>`;
    }

    function renderLog(log) {
        const d   = log.data || {};
        const evt = log.event || '';

        // Determine entry class from event type + result
        let cls = 'info';
        if (evt === 'action.executed') {
            cls = d.result === 'success' ? 'success' : d.result === 'failure' ? 'failure' : 'skipped';
        } else if (evt === 'execution.error' || evt === 'permission.denied') {
            cls = 'failure';
        } else if (evt === 'transition.condition_evaluated') {
            cls = 'cond';
        } else if ((log.triggered_by || '').startsWith('auto')) {
            cls = 'auto';
        }

        const eventLabels = {
            'instance.started':                'Avvio istanza',
            'state.entered':                   'Ingresso stato',
            'state.exited':                    'Uscita stato',
            'transition.performed':            'Transizione eseguita',
            'transition.condition_evaluated':  'Valutazione condizione',
            'action.executed':                 'Azione eseguita',
            'permission.denied':               'Permesso negato',
            'instance.completed':              'Workflow completato',
            'instance.blocked':                'Workflow bloccato',
            'execution.error':                 'Errore esecuzione',
        };

        let body = '';

        if (evt === 'action.executed') {
            body += rowHtml('Azione', d.action_name || d.action_class);
            body += rowHtml('Fase', d.phase_label || d.phase);
            body += `<div class="log-row"><span class="log-label">Esito</span><span class="log-value ${d.result === 'success' ? 'ok' : d.result === 'failure' ? 'err' : 'warn'}">${esc(d.result_label || d.result)}</span></div>`;
            body += rowHtml('Durata', d.duration || d.duration_ms + 'ms');
            if (d.error_message) {
                body += `<div class="log-row"><span class="log-label">Errore</span><span class="log-value err">${esc(d.error_message)}</span></div>`;
                if (d.error_trace) body += `<div class="log-stack">${esc(d.error_trace)}</div>`;
            }
            if (d.configuration) {
                body += '<div class="log-label" style="margin-top:4px">Configurazione</div>';
                body += `<div class="log-json">${esc(JSON.stringify(d.configuration, null, 2))}</div>`;
            }
        } else if (evt === 'transition.condition_evaluated') {
            body += rowHtml('Tipo', d.condition_type_label || d.condition_type);
            body += `<div class="log-row"><span class="log-label">Esito</span><span class="log-value ${d.result ? 'ok' : 'err'}">${esc(d.result_label || (d.result ? 'Vera' : 'Falsa'))}</span></div>`;
            if (d.condition) {
                body += '<div class="log-label" style="margin-top:4px">Regola jsonLogic</div>';
                body += `<div class="log-json">${esc(JSON.stringify(d.condition, null, 2))}</div>`;
            }
            if (d.input_data) {
                body += '<div class="log-label" style="margin-top:4px">Dati di input</div>';
                body += `<div class="log-json">${esc(JSON.stringify(d.input_data, null, 2))}</div>`;
            }
        } else if (evt === 'state.entered') {
            body += rowHtml('Stato', d.state_name);
            body += rowHtml('Modalità', d.mode_label || d.mode);
        } else if (evt === 'state.exited') {
            body += rowHtml('Stato', d.state_name);
            body += rowHtml('Tempo nello stato', d.time_in_state || (d.time_in_state_ms != null ? d.time_in_state_ms + 'ms' : '—'));
        } else if (evt === 'transition.performed') {
            body += rowHtml('Transizione', d.transition_label);
            body += rowHtml('Da', d.from_state_name);
            body += rowHtml('A', d.to_state_name);
        } else if (evt === 'execution.error') {
            body += `<div class="log-row"><span class="log-label">Classe errore</span><span class="log-value err code">${esc(d.class)}</span></div>`;
            body += `<div class="log-row"><span class="log-label">Messaggio</span><span class="log-value err">${esc(d.message)}</span></div>`;
            body += rowHtml('File', d.file + ':' + d.line);
            if (d.trace) body += `<div class="log-stack">${esc(d.trace)}</div>`;
        } else if (evt === 'instance.started') {
            body += rowHtml('Workflow', d.workflow_name + ' (' + d.workflow_slug + ')');
            body += rowHtml('Entità', d.entity_display || d.entity_class + ' #' + d.entity_id);
            body += rowHtml('Avviato da', d.triggered_by_detail);
        } else if (evt === 'permission.denied') {
            body += `<div class="log-row"><span class="log-label">Azione negata</span><span class="log-value err">${esc(d.action_label || d.action)}</span></div>`;
            body += rowHtml('Utente ID', d.user_id);
            body += rowHtml('Driver permessi', d.driver);
            body += `<div class="log-row"><span class="log-label">Riepilogo</span><span class="log-value err">${esc(d.summary)}</span></div>`;
        } else if (evt === 'instance.completed' || evt === 'instance.blocked') {
            body += rowHtml('Stato finale', d.final_state_name || d.state_name);
            if (d.summary) body += `<div class="log-row"><span class="log-value">${esc(d.summary)}</span></div>`;
        }

        return `
        <div class="log-entry ${cls}">
            <div>
                <span class="log-event">${esc(eventLabels[evt] || evt)}</span>
                <span class="log-time">${fmtTime(log.occurred_at)}</span>
            </div>
            ${body ? `<div class="log-detail">${body}</div>` : ''}
        </div>`;
    }

    function openModal(title, html) {
        document.getElementById('exec-modal-title').textContent = title;
        document.getElementById('exec-modal-body').innerHTML = html || '<div class="empty-state">Nessun log trovato.</div>';
        document.getElementById('exec-modal-overlay').classList.add('open');
    }

    // Close on overlay click (outside modal card)
    document.getElementById('exec-modal-overlay').addEventListener('click', function (e) {
        if (e.target === this) ExecViewer.closeModal();
    });

    // ── Public API ──
    window.ExecViewer = {
        fit() {
            canvas.ds.reset();
            if (graph._nodes.length) canvas.fitToContents();
        },

        closeModal() {
            document.getElementById('exec-modal-overlay').classList.remove('open');
        },

        async showStateModal(stateId, stateName) {
            openModal('Stato: ' + stateName, '<div class="empty-state">Caricamento log...</div>');
            try {
                const resp = await fetch(logsUrl('state', stateId), { headers: { Accept: 'application/json' } });
                const logs = await resp.json();

                if (!logs.length) {
                    openModal('Stato: ' + stateName, '<div class="empty-state">Nessun log per questo stato.</div>');
                    return;
                }

                const byEvent = (evts) => logs.filter(l => evts.includes(l.event));

                let html = '';

                const lifecycle = byEvent(['state.entered', 'state.exited', 'instance.completed', 'instance.blocked']);
                if (lifecycle.length) {
                    html += '<div class="log-section-title">Ciclo di vita dello stato</div>';
                    lifecycle.forEach(l => { html += renderLog(l); });
                }

                const entering = logs.filter(l => l.event === 'action.executed' && (l.data?.phase === 'on_enter'));
                if (entering.length) {
                    html += '<div class="log-section-title">Azioni all\'ingresso (on_enter)</div>';
                    entering.forEach(l => { html += renderLog(l); });
                }

                const exiting = logs.filter(l => l.event === 'action.executed' && (l.data?.phase === 'on_exit'));
                if (exiting.length) {
                    html += '<div class="log-section-title">Azioni all\'uscita (on_exit)</div>';
                    exiting.forEach(l => { html += renderLog(l); });
                }

                openModal('Stato: ' + stateName, html || '<div class="empty-state">Nessun log trovato.</div>');
            } catch (e) {
                openModal('Stato: ' + stateName, `<div class="empty-state" style="color:#f87171">Errore: ${esc(e.message)}</div>`);
            }
        },

        async showTransitionModal(transitionId, link) {
            const label = 'Transizione';
            openModal(label, '<div class="empty-state">Caricamento log...</div>');
            try {
                const resp = await fetch(logsUrl('transition', transitionId), { headers: { Accept: 'application/json' } });
                const logs = await resp.json();

                if (!logs.length) {
                    openModal(label, '<div class="empty-state">Nessun log per questa transizione.</div>');
                    return;
                }

                let html = '';

                const performed = logs.filter(l => l.event === 'transition.performed');
                if (performed.length) {
                    html += '<div class="log-section-title">Esecuzione transizione</div>';
                    performed.forEach(l => { html += renderLog(l); });
                }

                const conditions = logs.filter(l => l.event === 'transition.condition_evaluated');
                if (conditions.length) {
                    html += '<div class="log-section-title">Valutazione condizioni</div>';
                    conditions.forEach(l => { html += renderLog(l); });
                }

                const prePre = logs.filter(l => l.event === 'action.executed' && l.data?.phase === 'pre');
                if (prePre.length) {
                    html += '<div class="log-section-title">Azioni pre-transizione</div>';
                    prePre.forEach(l => { html += renderLog(l); });
                }

                const postPost = logs.filter(l => l.event === 'action.executed' && l.data?.phase === 'post');
                if (postPost.length) {
                    html += '<div class="log-section-title">Azioni post-transizione</div>';
                    postPost.forEach(l => { html += renderLog(l); });
                }

                openModal(label, html || '<div class="empty-state">Nessun log trovato.</div>');
            } catch (e) {
                openModal(label, `<div class="empty-state" style="color:#f87171">Errore: ${esc(e.message)}</div>`);
            }
        },
    };

})();
</script>
</body>
</html>
