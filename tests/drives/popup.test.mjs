import assert from 'node:assert/strict';
import { test } from 'node:test';

const events = new Map();
const elements = new Map();
const calls = [];
globalThis.document = {
    querySelector: (selector) => {
        if (!elements.has(selector))
            elements.set(selector, {
                textContent: '',
                disabled: false,
                addEventListener: (_, handler) => events.set(selector, handler),
            });
        return elements.get(selector);
    },
};
globalThis.chrome = {
    storage: {
        local: { get: async () => ({}), set: async () => calls.push('saved') },
        session: { clear: async () => {} },
    },
    tabs: {
        query: async () => [
            { id: 23, url: 'https://idee.test/menus/1/drives' },
        ],
        reload: async (id) => calls.push(['reload', id]),
    },
    permissions: { request: async () => true },
    scripting: {
        unregisterContentScripts: async () => {},
        registerContentScripts: async () => calls.push('registered'),
    },
    runtime: {
        sendMessage: async (message) => {
            calls.push(message.operation);
            return { value: { tabs: [1, 2, 3] } };
        },
    },
};
await import('../../extensions/drives/popup.js');

await test('pairing reloads the page after saving the new bridge registration', async () => {
    await events.get('#pair')();
    assert.deepEqual(calls, ['registered', 'saved', ['reload', 23]]);
    assert.match(elements.get('#status').textContent, /page a été rechargée/);
});

await test('the popup opening button works without a website message bridge', async () => {
    await events.get('#open')({ currentTarget: elements.get('#open') });
    assert.equal(calls.at(-1), 'open');
    assert.match(
        elements.get('#status').textContent,
        /trois drives sont ouverts/,
    );
    assert.equal(elements.get('#open').disabled, false);
});
