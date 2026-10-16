(() => {
    if (globalThis.__cuistotDriveBridge) return;
    globalThis.__cuistotDriveBridge = true;
    window.addEventListener('message', async (event) => {
        const request = event.data;
        if (
            event.source !== window ||
            event.origin !== location.origin ||
            request?.channel !== 'cuistot-drives:request'
        )
            return;
        if (
            !['status', 'open', 'search', 'prepare', 'job'].includes(
                request.operation,
            ) ||
            typeof request.id !== 'string' ||
            request.id.length > 80
        )
            return;
        let response;
        try {
            response = await chrome.runtime.sendMessage({
                operation: request.operation,
                payload: request.payload,
            });
        } catch {
            response = { error: 'Extension déconnectée. Rechargez la page.' };
        }
        window.postMessage(
            { channel: 'cuistot-drives:response', id: request.id, ...response },
            location.origin,
        );
    });
})();
