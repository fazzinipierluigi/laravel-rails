/**
 * jsonlogic_ui — jQuery plugin for building JsonLogic conditions visually.
 * Usage: $('#container').jsonlogicUI({ variables: [...], value: {...} })
 * Event: 'jsonlogicui:change' fired on every change; read hidden input for JSON value.
 */
(function ($) {
    'use strict';

    $.fn.jsonlogicUI = function (options) {
        if (this.length > 1) {
            this.each(function () { $(this).jsonlogicUI(options); });
            return this;
        }

        var self = this;
        var uid  = Date.now().toString(36) + Math.random().toString(36).substr(2, 6);

        var cfg = $.extend({
            input_name : self.is('input') ? self.attr('name') : ('jl_' + uid),
            value      : null,
            variables  : [],   // [{value: 'variables.status', label: 'Status'}]
        }, options);

        // ─── Helpers ──────────────────────────────────────────────────────────

        function isObj(v) { return v !== null && typeof v === 'object' && !Array.isArray(v); }

        function escH(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                                  .replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function castValue(raw, type) {
            if (type === 'number')  return isNaN(parseFloat(raw)) ? 0 : parseFloat(raw);
            if (type === 'boolean') return (raw === 'true' || raw === '1');
            if (type === 'null')    return null;
            return String(raw);
        }

        // ─── DOM → Object ──────────────────────────────────────────────────────

        function domToObj($group) {
            var op  = $group.find('> .jl-group-header > .jl-group-op').val() || 'and';
            var not = $group.find('> .jl-group-header .jl-not-check').is(':checked');

            var children = [];

            $group.find('> .jl-group-body').children().each(function () {
                var $el = $(this);
                var obj;
                if ($el.hasClass('jl-rule')) {
                    obj = ruleToObj($el);
                } else if ($el.hasClass('jl-group')) {
                    obj = domToObj($el);
                }
                if (obj !== null && obj !== undefined) children.push(obj);
            });

            if (!children.length) return null;

            var combined = children.length === 1 ? children[0] : {};
            if (children.length > 1) combined[op] = children;

            return not ? { '!': combined } : combined;
        }

        function ruleToObj($rule) {
            var op       = $rule.find('.jl-op').val();
            var left     = readSide($rule, 'left');
            var right    = readSide($rule, 'right');

            // Unary operators: only need left side
            if (op === '!') return { '!': left };
            if (op === 'truthy') return { '!!': left };

            return { [op]: [left, right] };
        }

        function readSide($rule, side) {
            var type = $rule.find('.jl-' + side + '-type').val();
            if (type === 'var') {
                var sel = $rule.find('.jl-' + side + '-var').val();
                var custom = $rule.find('.jl-' + side + '-var-custom').val() || '';
                var path = sel === '__custom__' ? custom : sel;
                return { var: path };
            }
            var vtype = $rule.find('.jl-' + side + '-vtype').val() || 'string';
            var raw   = $rule.find('.jl-' + side + '-val').val() || '';
            return castValue(raw, vtype);
        }

        // ─── Object → DOM ──────────────────────────────────────────────────────

        function objToDom(obj, $body) {
            if (!isObj(obj)) return;

            var key = Object.keys(obj)[0];

            // NOT wrapper
            if (key === '!') {
                var inner = obj['!'];
                if (isObj(inner)) {
                    var ik = Object.keys(inner)[0];
                    if (ik === 'and' || ik === 'or') {
                        var $g = makeGroupEl(ik);
                        $g.find('> .jl-group-header .jl-not-check').prop('checked', true);
                        $.each(inner[ik], function (_, child) { objToDom(child, $g.find('> .jl-group-body')); });
                        $body.append($g);
                        return;
                    }
                }
                // Single negation — treat as unary rule
                $body.append(makeRuleFromObj({ '!': [inner] }));
                return;
            }

            // Group
            if (key === 'and' || key === 'or') {
                var $grp = makeGroupEl(key);
                $.each(obj[key], function (_, child) { objToDom(child, $grp.find('> .jl-group-body')); });
                $body.append($grp);
                return;
            }

            // Rule
            $body.append(makeRuleFromObj(obj));
        }

        function makeRuleFromObj(obj) {
            var $rule = makeRuleEl();
            var key   = Object.keys(obj)[0];
            var args  = obj[key];

            $rule.find('.jl-op').val(key).trigger('change');

            if (key === '!' || key === '!!') {
                var val = Array.isArray(args) ? args[0] : args;
                populateSide($rule, 'left', val);
            } else if (Array.isArray(args)) {
                if (args.length >= 1) populateSide($rule, 'left', args[0]);
                if (args.length >= 2) populateSide($rule, 'right', args[1]);
            }

            return $rule;
        }

        function populateSide($rule, side, value) {
            if (isObj(value) && typeof value.var === 'string') {
                $rule.find('.jl-' + side + '-type').val('var').trigger('change');
                var knownVar = cfg.variables.find(function (v) { return v.value === value.var; });
                if (knownVar) {
                    $rule.find('.jl-' + side + '-var').val(value.var);
                } else {
                    $rule.find('.jl-' + side + '-var').val('__custom__').trigger('change');
                    $rule.find('.jl-' + side + '-var-custom').val(value.var);
                }
            } else {
                var vtype = typeof value === 'number'  ? 'number'
                          : typeof value === 'boolean' ? 'boolean'
                          : value === null             ? 'null'
                          : 'string';
                $rule.find('.jl-' + side + '-type').val('literal').trigger('change');
                $rule.find('.jl-' + side + '-vtype').val(vtype);
                $rule.find('.jl-' + side + '-val').val(value === null ? '' : String(value));
            }
        }

        // ─── DOM builders ─────────────────────────────────────────────────────

        function varOptsHtml(selected) {
            var html = '<option value="">— variabile —</option>';
            $.each(cfg.variables, function (_, v) {
                var sel = (selected === v.value) ? ' selected' : '';
                html += '<option value="' + escH(v.value) + '"' + sel + '>' + escH(v.label || v.value) + '</option>';
            });
            var customSel = (selected && !cfg.variables.find(function (v) { return v.value === selected; })) ? ' selected' : '';
            html += '<option value="__custom__"' + customSel + '>✏ Personalizzato…</option>';
            return html;
        }

        function makeSideHtml(side) {
            return '<span class="jl-side jl-side-' + side + '">' +
                '<select class="jl-' + side + '-type jl-sel jl-side-type">' +
                    '<option value="var">Variabile</option>' +
                    '<option value="literal">Valore</option>' +
                '</select>' +
                '<span class="jl-' + side + '-var-wrap">' +
                    '<select class="jl-' + side + '-var jl-sel jl-var-sel">' + varOptsHtml('') + '</select>' +
                    '<input class="jl-' + side + '-var-custom jl-inp jl-custom-var" placeholder="es. variables.status" style="display:none;">' +
                '</span>' +
                '<span class="jl-' + side + '-lit-wrap" style="display:none;">' +
                    '<select class="jl-' + side + '-vtype jl-sel jl-vtype-sel">' +
                        '<option value="string">Testo</option>' +
                        '<option value="number">Numero</option>' +
                        '<option value="boolean">Booleano</option>' +
                        '<option value="null">Null</option>' +
                    '</select>' +
                    '<input class="jl-' + side + '-val jl-inp" placeholder="valore">' +
                '</span>' +
            '</span>';
        }

        function makeRuleEl() {
            var $rule = $(
                '<div class="jl-rule">' +
                    makeSideHtml('left') +
                    '<select class="jl-op jl-sel jl-op-sel">' +
                        '<option value="==">= uguale a</option>' +
                        '<option value="!=">≠ diverso da</option>' +
                        '<option value=">">&gt; maggiore di</option>' +
                        '<option value=">=">&ge; magg. uguale</option>' +
                        '<option value="<">&lt; minore di</option>' +
                        '<option value="<=">&le; min. uguale</option>' +
                        '<option value="in">∈ contenuto in</option>' +
                        '<option value="!">! è falso (unario)</option>' +
                        '<option value="truthy">!! è vero (unario)</option>' +
                    '</select>' +
                    makeSideHtml('right') +
                    '<button class="jl-remove jl-btn-del" title="Rimuovi regola">✕</button>' +
                '</div>'
            );

            // Wire side-type toggles
            ['left', 'right'].forEach(function (side) {
                $rule.find('.jl-' + side + '-type').on('change', function () {
                    var isVar = $(this).val() === 'var';
                    $rule.find('.jl-' + side + '-var-wrap').toggle(isVar);
                    $rule.find('.jl-' + side + '-lit-wrap').toggle(!isVar);
                });
                $rule.find('.jl-' + side + '-var').on('change', function () {
                    $rule.find('.jl-' + side + '-var-custom').toggle($(this).val() === '__custom__');
                });
            });

            // Wire operator: hide right side for unary ops
            $rule.find('.jl-op').on('change', function () {
                var unary = ['!', 'truthy'].indexOf($(this).val()) !== -1;
                $rule.find('.jl-side-right').toggle(!unary);
            });

            return $rule;
        }

        function makeGroupEl(op, isRoot) {
            var $grp = $(
                '<div class="jl-group' + (isRoot ? ' jl-root' : '') + '">' +
                    '<div class="jl-group-header">' +
                        '<select class="jl-group-op jl-sel">' +
                            '<option value="and">AND</option>' +
                            '<option value="or">OR</option>' +
                        '</select>' +
                        '<label class="jl-not-label">' +
                            '<input type="checkbox" class="jl-not-check"> NOT' +
                        '</label>' +
                        '<button class="jl-add-rule jl-btn">+ Regola</button>' +
                        '<button class="jl-add-group jl-btn">+ Gruppo</button>' +
                        (!isRoot ? '<button class="jl-remove jl-btn-del" title="Rimuovi gruppo">✕</button>' : '') +
                    '</div>' +
                    '<div class="jl-group-body"></div>' +
                '</div>'
            );
            $grp.find('.jl-group-op').val(op || 'and');
            return $grp;
        }

        // ─── Sync output ──────────────────────────────────────────────────────

        function sync() {
            var $root = self.find('> .jl-root');
            var obj   = domToObj($root);
            self.find('> input[type=hidden]').val(obj ? JSON.stringify(obj) : '');
            self.trigger('jsonlogicui:change');
        }

        // ─── Init ─────────────────────────────────────────────────────────────

        function loadValue(val) {
            if (!val) return;
            try {
                var parsed = typeof val === 'string' ? JSON.parse(val.replace(/&quot;/g, '"')) : val;
                if (!parsed) return;
                var $rootBody = self.find('> .jl-root > .jl-group-body');
                var key = Object.keys(parsed)[0];

                if (key === 'and' || key === 'or') {
                    self.find('> .jl-root > .jl-group-header .jl-group-op').val(key);
                    $.each(parsed[key], function (_, child) { objToDom(child, $rootBody); });
                } else if (key === '!') {
                    var inner = parsed['!'];
                    if (isObj(inner)) {
                        var ik = Object.keys(inner)[0];
                        if (ik === 'and' || ik === 'or') {
                            self.find('> .jl-root > .jl-group-header .jl-group-op').val(ik);
                            self.find('> .jl-root > .jl-group-header .jl-not-check').prop('checked', true);
                            $.each(inner[ik], function (_, child) { objToDom(child, $rootBody); });
                            return;
                        }
                    }
                    objToDom(parsed, $rootBody);
                } else {
                    objToDom(parsed, $rootBody);
                }
            } catch (e) { /* bad JSON — start empty */ }
        }

        // Build DOM
        var $root = makeGroupEl('and', true);
        self.addClass('jsonlogic_ui')
            .prepend('<input type="hidden" name="' + escH(cfg.input_name) + '">')
            .append($root);

        // ─── Event delegation ─────────────────────────────────────────────────

        self.on('click', '.jl-add-rule', function (e) {
            e.preventDefault(); e.stopPropagation();
            $(this).closest('.jl-group').find('> .jl-group-body').append(makeRuleEl());
            sync();
        });

        self.on('click', '.jl-add-group', function (e) {
            e.preventDefault(); e.stopPropagation();
            $(this).closest('.jl-group').find('> .jl-group-body').append(makeGroupEl('and'));
            sync();
        });

        self.on('click', '.jl-remove', function (e) {
            e.preventDefault(); e.stopPropagation();
            $(this).closest('.jl-rule, .jl-group:not(.jl-root)').remove();
            sync();
        });

        self.on('change input', '.jl-sel, .jl-inp, .jl-not-check', function () {
            sync();
        });

        loadValue(cfg.value);
        sync();

        return self;
    };

})(jQuery);
