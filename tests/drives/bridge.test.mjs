import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { test } from 'node:test';

const source = readFileSync(
    new URL('../../extensions/drives/bridge.js', import.meta.url),
    'utf8',
);

await test('bridge reinjection replaces its previous listener and forwards the opening handshake once', async () => {
    const listeners = new Set();
    const replies = [];
    const calls = [];
    let disposed = false;
    const window = {
        addEventListener: (_, fn) => listeners.add(fn),
        removeEventListener: (_, fn) => listeners.delete(fn),
        postMessage: (message, origin) => replies.push({ message, origin }),
    };
    const context = vm.createContext({
        window,
        location: { origin: 'https://idee.test' },
        chrome: {
            runtime: {
                sendMessage: async (message) => {
                    calls.push(message);
                    return { value: { version: '0.1.2' } };
                },
            },
        },
        __cuistotDriveBridge: {
            version: '0.1.1',
            dispose: () => {
                disposed = true;
            },
        },
    });
    vm.runInContext(source, context);
    vm.runInContext(source, context);
    assert.equal(disposed, true);
    assert.equal(listeners.size, 1);
    const request = {
        source: window,
        origin: 'https://idee.test',
        data: {
            channel: 'cuistot-drives:request',
            operation: 'ping',
            id: 'request-1',
        },
    };
    for (const fn of listeners) {
        await fn({ ...request, origin: 'https://other.test' });
        await fn({ ...request, source: {} });
        await fn(request);
    }
    assert.equal(calls.length, 1);
    assert.equal(calls[0].operation, 'ping');
    assert.equal(replies[0].message.value.version, '0.1.2');
    assert.equal(replies[0].origin, 'https://idee.test');
});
