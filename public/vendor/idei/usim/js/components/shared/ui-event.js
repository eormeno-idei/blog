/**
 * Shared UI event helpers for modular components.
 */
(function initUsimComponentHelpers(global) {
    const namespace = global.USIM_COMPONENT_HELPERS || {};

    async function sendUiEvent({ componentId, event, action, parameters = {}, credentials = 'same-origin' }) {
        const csrfHeaders = typeof global.getCsrfHeaders === 'function'
            ? global.getCsrfHeaders()
            : (document.querySelector('meta[name="csrf-token"]')?.content
                ? { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                : {});
        const usimStorage = typeof getUsimStorageHeaderValue === 'function'
            ? getUsimStorageHeaderValue()
            : '';
        const usimClientId = typeof global.getUsimClientId === 'function'
            ? global.getUsimClientId()
            : (typeof getUsimClientId === 'function' ? getUsimClientId() : '');

        const response = await fetch('/api/ui-event', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-USIM-Storage': usimStorage,
                ...(usimClientId ? { 'X-UI-Client-Id': usimClientId } : {}),
                ...csrfHeaders,
            },
            credentials,
            body: JSON.stringify({
                component_id: componentId,
                event,
                action,
                parameters,
            }),
        });

        if (response.status === 419) {
            const renderer = global.globalRenderer || (typeof globalRenderer !== 'undefined' ? globalRenderer : null);
            if (renderer && typeof renderer.destroy === 'function') {
                renderer.destroy();
            }
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        }

        let result = null;
        try {
            result = await response.json();
        } catch (_error) {
            result = null;
        }

        return { ok: response.ok, response, result };
    }

    function applyUiUpdate(result) {
        if (!result || typeof result !== 'object' || Object.keys(result).length === 0) {
            return false;
        }

        const renderer = global.globalRenderer || (typeof globalRenderer !== 'undefined' ? globalRenderer : null);

        if (renderer && typeof renderer.handleUIUpdate === 'function') {
            renderer.handleUIUpdate(result);
            return true;
        }

        return false;
    }

    function buildUiDataUrl({ componentId, action, parameters = {} }) {
        const url = new URL('/api/ui-data', window.location.origin);
        url.searchParams.set('component_id', componentId);
        url.searchParams.set('action', action);
        if (parameters && typeof parameters === 'object' && Object.keys(parameters).length > 0) {
            url.searchParams.set('parameters', JSON.stringify(parameters));
        }
        return url.toString();
    }

    async function fetchUiData({ componentId, action, parameters = {}, credentials = 'same-origin', signal = null }) {
        const csrfHeaders = typeof global.getCsrfHeaders === 'function'
            ? global.getCsrfHeaders()
            : (document.querySelector('meta[name="csrf-token"]')?.content
                ? { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                : {});
        const usimStorage = typeof getUsimStorageHeaderValue === 'function'
            ? getUsimStorageHeaderValue()
            : '';
        const usimClientId = typeof global.getUsimClientId === 'function'
            ? global.getUsimClientId()
            : (typeof getUsimClientId === 'function' ? getUsimClientId() : '');

        const url = new URL('/api/ui-data', window.location.origin);
        url.searchParams.set('component_id', componentId);
        url.searchParams.set('action', action);
        if (parameters && typeof parameters === 'object' && Object.keys(parameters).length > 0) {
            url.searchParams.set('parameters', JSON.stringify(parameters));
        }

        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-USIM-Storage': usimStorage,
                ...(usimClientId ? { 'X-UI-Client-Id': usimClientId } : {}),
                ...csrfHeaders,
            },
            credentials,
            signal,
        });

        if (response.status === 419) {
            const renderer = global.globalRenderer || (typeof globalRenderer !== 'undefined' ? globalRenderer : null);
            if (renderer && typeof renderer.destroy === 'function') {
                renderer.destroy();
            }
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        }

        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        let data = null;

        if (response.status === 204) {
            data = null;
        } else if (contentType.includes('application/json')) {
            try {
                data = await response.json();
            } catch (_error) {
                data = null;
            }
        } else if (
            contentType.startsWith('image/') ||
            contentType.startsWith('audio/') ||
            contentType.startsWith('video/') ||
            contentType.includes('octet-stream') ||
            contentType.includes('pdf')
        ) {
            try {
                data = await response.blob();
            } catch (_error) {
                data = null;
            }
        } else {
            try {
                data = await response.text();
            } catch (_error) {
                data = null;
            }
        }

        return { ok: response.ok, status: response.status, contentType, response, data };
    }

    namespace.sendUiEvent = sendUiEvent;
    namespace.applyUiUpdate = applyUiUpdate;
    namespace.buildUiDataUrl = buildUiDataUrl;
    namespace.fetchUiData = fetchUiData;
    global.USIM_COMPONENT_HELPERS = namespace;
})(window);
