(() => {
    'use strict';

    const META = {
        transport: document.querySelector('meta[name="t-transport"]')?.content || 'http',
        csrf: document.querySelector('meta[name="t-csrf"]')?.content || '',
    };

    const wsConnections = new Map();
    const sseConnections = new Map();

    function transportFor(root) {
        return root.dataset.tTransport || META.transport || 'http';
    }

    function stateInput(root) {
        return root.querySelector('input[name="t:state"]');
    }

    function stateToken(root) {
        return stateInput(root)?.value || '';
    }

    function componentName(root) {
        return root.dataset.tLive;
    }

    function csrfToken() {
        return META.csrf;
    }

    function findRoot(name, fallback) {
        const candidates = document.querySelectorAll(`[data-t-live="${CSS.escape(name)}"]`);
        if (fallback && fallback.isConnected) {
            return fallback;
        }

        for (const el of candidates) {
            if (el.isConnected) {
                return el;
            }
        }

        return candidates[0] || null;
    }

    function coerce(value) {
        value = String(value).trim();

        if (value === 'true') {
            return true;
        }

        if (value === 'false') {
            return false;
        }

        if (value === 'null' || value === 'undefined') {
            return null;
        }

        if (/^-?\d+$/.test(value)) {
            return parseInt(value, 10);
        }

        if (/^-?\d+\.\d+$/.test(value)) {
            return parseFloat(value);
        }

        if ((value.startsWith("'") && value.endsWith("'")) || (value.startsWith('"') && value.endsWith('"'))) {
            return value.slice(1, -1);
        }

        try {
            return JSON.parse(value);
        } catch {
            return value;
        }
    }

    function parseAction(raw) {
        const match = String(raw).match(/^(\w+)(?:\((.*)\))?$/s);

        if (!match) {
            return { name: raw, params: {} };
        }

        const name = match[1];
        const rawParams = (match[2] || '').trim();

        if (rawParams === '') {
            return { name, params: {} };
        }

        const params = {};
        let positional = 0;

        for (let part of rawParams.split(',')) {
            part = part.trim();

            if (part === '') {
                continue;
            }

            const named = part.match(/^(\w+):\s*(.*)$/s);

            if (named) {
                params[named[1]] = coerce(named[2]);
            } else {
                params[positional++] = coerce(part);
            }
        }

        return { name, params };
    }

    function applyPatches(root, patches) {
        let current = root;

        for (const patch of patches || []) {
            if (patch.type !== 'replace' || patch.id !== 0) {
                continue;
            }

            current.outerHTML = patch.html;
            const name = componentName(current);
            const replacement = name ? findRoot(name, current) : null;

            if (replacement && replacement !== current) {
                current = replacement;
                bindEvents(current);
            }
        }

        return current;
    }

    function requestHeaders() {
        const headers = {};
        const token = csrfToken();

        if (token) {
            headers['X-CSRF-Token'] = token;
        }

        return headers;
    }

    function buildFormData(data) {
        const formData = new FormData();

        for (const [key, value] of Object.entries(data)) {
            if (value === undefined) {
                continue;
            }

            if (Array.isArray(value)) {
                for (const item of value) {
                    formData.append(key, item === null ? '' : item);
                }
            } else {
                formData.append(key, value === null ? '' : value);
            }
        }

        return formData;
    }

    function updateHttp(root, body) {
        const name = componentName(root);

        fetch(`/_live/${name}`, {
            method: 'POST',
            headers: requestHeaders(),
            body,
        })
            .then((res) => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }

                return res.text();
            })
            .then((html) => {
                const newRoot = applyPatches(root, [{ type: 'replace', id: 0, html }]);

                if (newRoot && newRoot !== root) {
                    updateTransportRoot(root, newRoot);
                }
            })
            .catch((err) => console.error('TondView HTTP update failed', err));
    }

    function sendHttp(root, data) {
        updateHttp(root, data instanceof FormData ? data : buildFormData(data));
    }

    function sendAction(root, action, params = {}) {
        const data = {
            't:state': stateToken(root),
            't:action': action,
            't:params': JSON.stringify(params),
        };

        const transport = transportFor(root);

        if (transport === 'websocket') {
            const conn = wsConnections.get(root);

            if (conn && conn.readyState === WebSocket.OPEN) {
                conn.send(JSON.stringify(data));

                return;
            }
        }

        sendHttp(root, data);
    }

    function sendForm(root, formData, action = '') {
        const data = { 't:state': stateToken(root) };

        if (action) {
            data['t:action'] = action;
        }

        for (const [key, value] of formData.entries()) {
            if (key === 't:state' || key === 't:action') {
                continue;
            }

            if (Object.prototype.hasOwnProperty.call(data, key)) {
                if (!Array.isArray(data[key])) {
                    data[key] = [data[key]];
                }

                data[key].push(value);
            } else {
                data[key] = value;
            }
        }

        const transport = transportFor(root);

        if (transport === 'websocket') {
            const conn = wsConnections.get(root);

            if (conn && conn.readyState === WebSocket.OPEN) {
                conn.send(JSON.stringify(data));

                return;
            }
        }

        sendHttp(root, data);
    }

    function attachAction(el, root) {
        const raw = el.dataset.tAction || el.getAttribute('wire:click');

        if (!raw) {
            return;
        }

        const { name, params } = parseAction(raw);

        el.addEventListener('click', (e) => {
            e.preventDefault();
            sendAction(root, name, params);
        });
    }

    function attachForm(form, root) {
        const action = form.dataset.tAction || '';

        form.addEventListener('submit', (e) => {
            e.preventDefault();

            const formData = new FormData(form);

            if (action && !formData.has('t:action')) {
                formData.append('t:action', action);
            }

            if (!formData.has('t:state')) {
                formData.append('t:state', stateToken(root));
            }

            sendForm(root, formData, action);
        });
    }

    function bindEvents(root) {
        root.querySelectorAll('[data-t-action], [wire\\:click]').forEach((el) => attachAction(el, root));
        root.querySelectorAll('form[data-t-action]').forEach((form) => attachForm(form, root));
    }

    function updateTransportRoot(oldRoot, newRoot) {
        if (wsConnections.has(oldRoot)) {
            const ws = wsConnections.get(oldRoot);
            wsConnections.delete(oldRoot);
            wsConnections.set(newRoot, ws);
        }

        if (sseConnections.has(oldRoot)) {
            const es = sseConnections.get(oldRoot);
            sseConnections.delete(oldRoot);
            sseConnections.set(newRoot, es);
        }
    }

    function connectWebSocket(root) {
        if (wsConnections.has(root)) {
            return;
        }

        const name = componentName(root);
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const ws = new WebSocket(`${protocol}//${window.location.host}/_live/ws`);

        wsConnections.set(root, ws);

        ws.addEventListener('open', () => {
            ws.send(JSON.stringify({
                't:component': name,
                't:state': stateToken(root),
            }));
        });

        ws.addEventListener('message', (event) => {
            let data;

            try {
                data = JSON.parse(event.data);
            } catch {
                return;
            }

            if (data.error) {
                console.error('TondView WS error', data.error);

                return;
            }

            const newRoot = applyPatches(root, data.patches || []);

            if (newRoot && newRoot !== root) {
                updateTransportRoot(root, newRoot);
            }
        });

        ws.addEventListener('close', () => wsConnections.delete(root));
        ws.addEventListener('error', () => wsConnections.delete(root));
    }

    function connectSse(root) {
        if (sseConnections.has(root)) {
            return;
        }

        const name = componentName(root);
        const es = new EventSource(`/_live/sse?component=${encodeURIComponent(name)}`);

        sseConnections.set(root, es);

        es.addEventListener('message', (event) => {
            let data;

            try {
                data = JSON.parse(event.data);
            } catch {
                return;
            }

            if (data.patches) {
                const newRoot = applyPatches(root, data.patches);

                if (newRoot && newRoot !== root) {
                    updateTransportRoot(root, newRoot);
                }
            }
        });

        es.addEventListener('error', () => sseConnections.delete(root));
    }

    function initRoot(root) {
        const transport = transportFor(root);

        if (transport === 'websocket') {
            connectWebSocket(root);
        } else if (transport === 'sse') {
            connectSse(root);
        }

        bindEvents(root);
    }

    function init() {
        document.querySelectorAll('[data-t-live]').forEach(initRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
