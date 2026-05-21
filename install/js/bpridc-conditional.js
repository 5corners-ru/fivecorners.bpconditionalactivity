/**
 * Global script: conditional field show/hide for CBPRequestInfoDeclineConditional
 * in the modern Bitrix24 task form (getTaskControls / bizproc slider UI).
 *
 * Intercepts BX.ajax.runAction for bizproc.task.getUserTaskByWorkflowId.
 * Reads DependOnField / DependOnValue from data.additionalParams.FIELDS.
 * Waits for the slider to render the form, then applies show/hide logic.
 *
 * Field rows in the modern UI: <div class="ui-form-row" data-cid="FIELD_ID">
 * Trigger controls:             <select name="bprioact_FIELD_ID">
 */
(function () {
    'use strict';

    var _intercepted = false;
    var _cancelToken  = 0;

    // ------------------------------------------------------------------
    // Extract dependency rules from the FIELDS metadata returned by
    // getTaskControls(). Only fields that have DependOnField + DependOnValue
    // set (by our CBPRequestInfoDeclineConditional::getTaskControls()) matter.
    // ------------------------------------------------------------------
    function extractRules(fields) {
        var rules = [];
        if (!Array.isArray(fields)) { return rules; }
        fields.forEach(function (f) {
            if (
                f &&
                f.DependOnField &&
                f.DependOnValue !== undefined &&
                String(f.DependOnValue) !== ''
            ) {
                rules.push({
                    depCid:       String(f.Id),                          // e.g. 'comment'
                    triggerName:  'bprioact_' + String(f.DependOnField), // e.g. 'bprioact_prichina'
                    triggerValue: String(f.DependOnValue),               // e.g. 'Низкая ЗП'
                });
            }
        });
        return rules;
    }

    // ------------------------------------------------------------------
    // Read current selected value(s) of a named form control.
    // Handles <select> (single + multiple) and <input type="hidden">.
    // ------------------------------------------------------------------
    function getFieldValues(name) {
        var values = [];
        var sel = document.querySelector(
            'select[name="' + name + '"], select[name="' + name + '[]"]'
        );
        if (sel) {
            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].selected && sel.options[i].value !== '') {
                    values.push(sel.options[i].value);
                }
            }
            return values;
        }
        var inp = document.querySelector(
            'input[name="' + name + '"], input[name="' + name + '[]"]'
        );
        if (inp && inp.value !== '') { values.push(inp.value); }
        return values;
    }

    // ------------------------------------------------------------------
    // Apply all rules: show/hide dependent rows based on trigger values.
    // ------------------------------------------------------------------
    function applyRules(rules) {
        rules.forEach(function (r) {
            var vals     = getFieldValues(r.triggerName);
            var triggered = vals.indexOf(r.triggerValue) !== -1;
            // Modern UI container
            var row = document.querySelector('.ui-form-row[data-cid="' + r.depCid + '"]');
            if (row) {
                row.style.display = triggered ? '' : 'none';
            }
        });
    }

    // ------------------------------------------------------------------
    // Attach change listeners to trigger controls.
    // ------------------------------------------------------------------
    function attachListeners(rules) {
        rules.forEach(function (r) {
            var els = document.querySelectorAll(
                'select[name="' + r.triggerName + '"], ' +
                'select[name="' + r.triggerName + '[]"], ' +
                'input[name="'  + r.triggerName + '"], ' +
                'input[name="'  + r.triggerName + '[]"]'
            );
            els.forEach(function (el) {
                el.addEventListener('change', function () { applyRules(rules); });
                el.addEventListener('input',  function () { applyRules(rules); });
            });
        });
    }

    // ------------------------------------------------------------------
    // Poll until the trigger field appears in the DOM (the slider renders
    // the form asynchronously), then apply rules and attach listeners.
    // ------------------------------------------------------------------
    function waitAndApply(rules, token, attempt) {
        if (token !== _cancelToken) { return; } // superseded by newer task
        if (attempt > 50)           { return; } // 5-second timeout

        var triggerSelector =
            'select[name="' + rules[0].triggerName + '"], ' +
            'input[name="'  + rules[0].triggerName + '"]';

        if (document.querySelector(triggerSelector)) {
            applyRules(rules);
            attachListeners(rules);
        } else {
            setTimeout(function () { waitAndApply(rules, token, attempt + 1); }, 100);
        }
    }

    // ------------------------------------------------------------------
    // Wrap BX.ajax.runAction to intercept getUserTaskByWorkflowId responses.
    // ------------------------------------------------------------------
    function setupInterception() {
        if (!window.BX || !BX.ajax || typeof BX.ajax.runAction !== 'function') {
            return false;
        }
        if (_intercepted) { return true; }
        _intercepted = true;

        var origFn = BX.ajax.runAction;
        BX.ajax.runAction = function (action) {
            var promise = origFn.apply(this, arguments);

            if (action === 'bizproc.task.getUserTaskByWorkflowId') {
                _cancelToken++;
                var token = _cancelToken;

                promise.then(function (res) {
                    var fields =
                        res &&
                        res.data &&
                        res.data.additionalParams &&
                        res.data.additionalParams.FIELDS;

                    var rules = extractRules(fields);
                    if (rules.length > 0) {
                        waitAndApply(rules, token, 0);
                    }
                });
            }

            return promise;
        };

        return true;
    }

    // ------------------------------------------------------------------
    // Boot: retry until BX.ajax.runAction is available (it's loaded lazily).
    // ------------------------------------------------------------------
    function trySetup(attempt) {
        if (attempt > 60) { return; } // 6-second boot timeout
        if (!setupInterception()) {
            setTimeout(function () { trySetup(attempt + 1); }, 100);
        }
    }

    trySetup(0);
}());

// ==========================================================================
// ShowTaskForm path (popup + full-page forms rendered via PHP / innerHTML).
// Rules are stored in <div id="bpridc-task-rules" data-rules="[...]">.
// This IIFE runs in every Bitrix page context (including iframes loaded via
// init.php → OnEpilog), so it catches the div even after innerHTML injection.
// ==========================================================================
(function () {
    'use strict';

    var _done = false;

    function getVal(name) {
        var el = document.querySelector('[name="' + name + '"]');
        if (!el) { return []; }
        if (el.tagName === 'SELECT') {
            var vals = [];
            for (var i = 0; i < el.options.length; i++) {
                if (el.options[i].selected && el.options[i].value !== '') {
                    vals.push(el.options[i].value);
                }
            }
            return vals;
        }
        return el.value !== '' ? [el.value] : [];
    }

    function getRow(name) {
        var el = document.querySelector('[name="' + name + '"]');
        if (!el) { return null; }
        return el.closest('tr')
            || el.closest('.bizproc-field-line')
            || el.closest('.ui-form-row');
    }

    function applyAll(rules) {
        rules.forEach(function (r) {
            var triggered = getVal(r.triggerField).indexOf(r.triggerValue) !== -1;
            var row = getRow(r.depField);
            if (row) { row.style.display = triggered ? '' : 'none'; }
        });
    }

    function initSF(rules) {
        applyAll(rules);

        rules.forEach(function (r) {
            var el = document.querySelector('[name="' + r.triggerField + '"]');
            if (el) {
                el.addEventListener('change', function () { applyAll(rules); });
                el.addEventListener('input',  function () { applyAll(rules); });
            }
        });

        // Polling for Bitrix custom dropdown that updates hidden input
        // via direct .value assignment (no DOM event fires)
        var _snap = {};
        setInterval(function () {
            var changed = false;
            rules.forEach(function (r) {
                var snap = JSON.stringify(getVal(r.triggerField));
                if (_snap[r.triggerField] !== snap) {
                    _snap[r.triggerField] = snap;
                    changed = true;
                }
            });
            if (changed) { applyAll(rules); }
        }, 200);
    }

    function poll(attempt) {
        if (_done || attempt > 150) { return; } // 30-second timeout
        var el = document.getElementById('bpridc-task-rules');
        if (el) {
            _done = true;
            try {
                var rules = JSON.parse(el.getAttribute('data-rules') || '[]');
                if (rules.length) { initSF(rules); }
            } catch (e) {}
            return;
        }
        setTimeout(function () { poll(attempt + 1); }, 200);
    }

    poll(0);
}());

// ==========================================================================
// WorkflowInfo slider path (/company/personal/bizproc/NNN/).
// The slider/template.php renders fields server-side and passes taskFields
// (with our DependOnField/DependOnValue from getTaskControls()) to:
//   BX.Bizproc.Component.WorkflowInfo.Instance
// We poll for that Instance, then apply show/hide every 200 ms.
// ==========================================================================
(function () {
    'use strict';

    function extractWFIRules(taskFields) {
        var rules = [];
        if (!Array.isArray(taskFields)) { return rules; }
        // Build Id→field map to look up the trigger field's actual FieldId
        // (the name Bitrix uses for the form control, e.g. "bprioact_prichina").
        var byId = {};
        taskFields.forEach(function (f) { if (f && f.Id) { byId[String(f.Id)] = f; } });

        taskFields.forEach(function (f) {
            if (f && f.DependOnField && f.DependOnValue !== undefined && String(f.DependOnValue) !== '') {
                var triggerField = byId[String(f.DependOnField)];
                rules.push({
                    depCid:       String(f.Id),
                    // Use FieldId from taskFields — it is exactly what getFieldInputControl()
                    // uses as the input name in the slider template.
                    triggerName:  triggerField ? String(triggerField.FieldId || triggerField.Id) : String(f.DependOnField),
                    triggerValue: String(f.DependOnValue),
                });
            }
        });
        return rules;
    }

    function getWFIVal(name) {
        var sel = document.querySelector('select[name="' + name + '"], select[name="' + name + '[]"]');
        if (sel) {
            var vals = [];
            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].selected && sel.options[i].value !== '') { vals.push(sel.options[i].value); }
            }
            return vals;
        }
        var inp = document.querySelector('input[name="' + name + '"], input[name="' + name + '[]"]');
        if (inp && inp.value !== '') { return [inp.value]; }
        return [];
    }

    function applyWFIRules(rules) {
        rules.forEach(function (r) {
            var triggered = getWFIVal(r.triggerName).indexOf(r.triggerValue) !== -1;
            var row = document.querySelector('.ui-form-row[data-cid="' + r.depCid + '"]');
            if (row) { row.style.display = triggered ? '' : 'none'; }
        });
    }

    function waitForWFI(attempt) {
        if (attempt > 100) { return; } // 10-second boot timeout
        var inst = window.BX
            && BX.Bizproc
            && BX.Bizproc.Component
            && BX.Bizproc.Component.WorkflowInfo
            && BX.Bizproc.Component.WorkflowInfo.Instance;
        if (inst) {
            // Re-read inst.taskFields on each tick so next-task AJAX updates work too.
            setInterval(function () {
                var rules = extractWFIRules(inst.taskFields);
                if (rules.length) { applyWFIRules(rules); }
            }, 200);
            var rules = extractWFIRules(inst.taskFields);
            if (rules.length) { applyWFIRules(rules); }
        } else {
            setTimeout(function () { waitForWFI(attempt + 1); }, 100);
        }
    }

    waitForWFI(0);
}());
