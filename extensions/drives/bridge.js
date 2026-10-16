(() => {
    const previous = globalThis.__cuistotDriveBridge;
    if (previous?.version === '0.1.2') return;
    previous?.dispose?.();
    const receive = async (event) => {
        const request = event.data;
        if (
            event.source !== window ||
            event.origin !== location.origin ||
            request?.channel !== 'cuistot-drives:request'
        )
            return;
        if (
            !['ping', 'status', 'open', 'search', 'prepare', 'job'].includes(
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
    };
    window.addEventListener('message', receive);
    globalThis.__cuistotDriveBridge = {
        version: '0.1.2',
        dispose: () => window.removeEventListener('message', receive),
    };
})();
