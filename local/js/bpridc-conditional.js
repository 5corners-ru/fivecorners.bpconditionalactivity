/**
 * Global script: conditional field show/hide for CBPRequestInfoDeclineConditional.
 *
 * Three paths are handled:
 *  1. BX.ajax.runAction intercept  — CRM slider popup
 *  2. #bpridc-task-rules div       — ShowTaskForm PHP path + /bizproc/ page (via init.php injection)
 *  3. WorkflowInfo.Instance        — reserved for future Bitrix versions where taskFields is exposed
 *
 * Root cause note: Bitrix stores S:Enum option values as internal hash IDs (e.g. MD5-like strings)
 * in the rendered <select>, while DependOnValue may store the display text typed by the designer.
 * _bpridcMatch() handles both cases by comparing against option.value AND option.text.
 */

// Returns true if the named select/input matches triggerValue —
// compares against option.value AND option.text to handle Bitrix hash-based enum IDs.
function _bpridcMatch(fieldName, triggerValue) {
    var el = document.querySelector('[name="' + fieldName + '"]');
    if (!el) { return false; }
    if (el.tagName === 'SELECT') {
        for (var i = 0; i < el.options.length; i++) {
            if (!el.options[i].selected) { continue; }
            if (el.options[i].value === triggerValue)       { return true; }
            if (el.options[i].text.trim() === triggerValue) { return true; }
        }
        return false;
    }
    return el.value === triggerValue;
}

// Returns snapshot of current field value(s) — used for polling change detection.
function _bpridcGetVal(fieldName) {
    var el = document.querySelector('[name="' + fieldName + '"]');
    if (!el) { return []; }
    if (el.tagName === 'SELECT') {
        var vals = [];
        for (var i = 0; i < el.options.length; i++) {
            if (el.options[i].selected) {
                // Prefer value; fall back to text so polling detects changes even when value="" (Bitrix classic admin)
                var v = el.options[i].value !== '' ? el.options[i].value : el.options[i].text.trim();
                if (v !== '') { vals.push(v); }
            }
        }
        return vals;
    }
    return el.value !== '' ? [el.value] : [];
}

// ==========================================================================
// IIFE-1: BX.ajax.runAction intercept (CRM slider popup).
// Intercepts bizproc.task.getUserTaskByWorkflowId and reads DependOnField /
// DependOnValue injected by getTaskControls() into additionalParams.FIELDS.
// ==========================================================================
(function () {
    'use strict';

    var _intercepted = false;
    var _cancelToken  = 0;

    function extractRules(fields) {
        var rules = [];
        if (!Array.isArray(fields)) { return rules; }
        fields.forEach(function (f) {
            if (f && f.DependOnField && f.DependOnValue !== undefined && String(f.DependOnValue) !== '') {
                rules.push({
                    depCid:       String(f.Id),
                    triggerName:  'bprioact_' + String(f.DependOnField),
                    triggerValue: String(f.DependOnValue),
                });
            }
        });
        return rules;
    }

    function applyRules(rules) {
        rules.forEach(function (r) {
            var triggered = _bpridcMatch(r.triggerName, r.triggerValue);
            var row = document.querySelector('.ui-form-row[data-cid="' + r.depCid + '"]');
            if (row) { row.style.display = triggered ? '' : 'none'; }
        });
    }

    function attachListeners(rules) {
        rules.forEach(function (r) {
            var els = document.querySelectorAll(
                'select[name="' + r.triggerName + '"], select[name="' + r.triggerName + '[]"], ' +
                'input[name="'  + r.triggerName + '"], input[name="'  + r.triggerName + '[]"]'
            );
            els.forEach(function (el) {
                el.addEventListener('change', function () { applyRules(rules); });
                el.addEventListener('input',  function () { applyRules(rules); });
            });
        });
    }

    function waitAndApply(rules, token, attempt) {
        if (token !== _cancelToken || attempt > 50) { return; }
        var sel = 'select[name="' + rules[0].triggerName + '"], input[name="' + rules[0].triggerName + '"]';
        if (document.querySelector(sel)) {
            applyRules(rules);
            attachListeners(rules);
        } else {
            setTimeout(function () { waitAndApply(rules, token, attempt + 1); }, 100);
        }
    }

    function setupInterception() {
        if (!window.BX || !BX.ajax || typeof BX.ajax.runAction !== 'function') { return false; }
        if (_intercepted) { return true; }
        _intercepted = true;

        var origFn = BX.ajax.runAction;
        BX.ajax.runAction = function (action) {
            var promise = origFn.apply(this, arguments);
            if (action === 'bizproc.task.getUserTaskByWorkflowId') {
                _cancelToken++;
                var token = _cancelToken;
                promise.then(function (res) {
                    var fields = res && res.data && res.data.additionalParams && res.data.additionalParams.FIELDS;
                    var rules = extractRules(fields);
                    if (rules.length > 0) { waitAndApply(rules, token, 0); }
                });
            }
            return promise;
        };
        return true;
    }

    function trySetup(attempt) {
        if (attempt > 60) { return; }
        if (!setupInterception()) { setTimeout(function () { trySetup(attempt + 1); }, 100); }
    }
    trySetup(0);
}());

// ==========================================================================
// IIFE-2: #bpridc-task-rules path.
// Handles forms rendered via PHP ShowTaskForm AND /bizproc/ pages where
// init.php injects the div by loading task parameters from CBPTaskService.
//
// Rules format: [{depField, triggerField, triggerValue, depTitle}, ...]
// Row lookup: el.closest('tr') → .bizproc-field-line → .ui-form-row
// ==========================================================================
(function () {
    'use strict';

    var _done = false;

    function getRow(name) {
        var el = document.querySelector('[name="' + name + '"]');
        if (!el) { return null; }
        return el.closest('tr') || el.closest('.bizproc-field-line') || el.closest('.ui-form-row');
    }

    function applyAll(rules) {
        rules.forEach(function (r) {
            var triggered = _bpridcMatch(r.triggerField, r.triggerValue);
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

        // Polling: catches Bitrix custom dropdowns that assign .value directly
        // without firing DOM events, and hash-based enum updates.
        var _snap = {};
        setInterval(function () {
            var changed = false;
            rules.forEach(function (r) {
                var snap = JSON.stringify(_bpridcGetVal(r.triggerField));
                if (_snap[r.triggerField] !== snap) {
                    _snap[r.triggerField] = snap;
                    changed = true;
                }
            });
            if (changed) { applyAll(rules); }
        }, 200);
    }

    function poll(attempt) {
        if (_done || attempt > 150) { return; }
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
// IIFE-3: WorkflowInfo.Instance path.
// Reserved for future Bitrix versions that populate inst.taskFields.
// Currently inst.taskFields === undefined on sdportal (24.x on-premise).
// The /bizproc/ page is handled by IIFE-2 via init.php div injection instead.
// ==========================================================================
(function () {
    'use strict';

    function extractWFIRules(taskFields) {
        var rules = [];
        if (!Array.isArray(taskFields)) { return rules; }
        var byId = {};
        taskFields.forEach(function (f) { if (f && f.Id) { byId[String(f.Id)] = f; } });
        taskFields.forEach(function (f) {
            if (f && f.DependOnField && f.DependOnValue !== undefined && String(f.DependOnValue) !== '') {
                var triggerField = byId[String(f.DependOnField)];
                rules.push({
                    depCid:       String(f.Id),
                    triggerName:  triggerField ? String(triggerField.FieldId || triggerField.Id) : String(f.DependOnField),
                    triggerValue: String(f.DependOnValue),
                });
            }
        });
        return rules;
    }

    function applyWFIRules(rules) {
        rules.forEach(function (r) {
            var triggered = _bpridcMatch(r.triggerName, r.triggerValue);
            var row = document.querySelector('.ui-form-row[data-cid="' + r.depCid + '"]');
            if (row) { row.style.display = triggered ? '' : 'none'; }
        });
    }

    function waitForWFI(attempt) {
        if (attempt > 100) { return; }
        var inst = window.BX
            && BX.Bizproc
            && BX.Bizproc.Component
            && BX.Bizproc.Component.WorkflowInfo
            && BX.Bizproc.Component.WorkflowInfo.Instance;
        if (inst) {
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
