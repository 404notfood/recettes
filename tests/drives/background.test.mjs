import assert from 'node:assert/strict';
import { test } from 'node:test';

let listener;
let executed = 0;
const data = {
    'j:aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa': {
        id: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        status: 'pending',
        createdAt: Date.now(),
        lines: [],
        store: { id: 'test' },
    },
};
globalThis.chrome = {
    storage: {
        local: { get: async () => ({ appOrigin: 'https://idee.test' }) },
        session: {
            get: async (key) => ({ [key]: data[key] }),
            set: async (values) => Object.assign(data, values),
        },
    },
    runtime: {
        getURL: (path) => `chrome-extension://test-extension/${path}`,
        onMessage: {
            addListener: (fn) => {
                listener = fn;
            },
        },
    },
    tabs: {
        query: async () => {
            executed++;
            return [];
        },
    },
};
await import('../../extensions/drives/background.js');
const send = (operation, sender, payload = {}) =>
    new Promise((resolve) => listener({ operation, payload }, sender, resolve));
const site = {
    url: 'https://idee.test/menus/1/drives',
    frameId: 0,
    tab: { id: 1 },
};

await test('unpaired origins and subframes cannot read retailer sessions', async () => {
    for (const sender of [
        { ...site, url: 'https://other.test/' },
        { ...site, frameId: 1 },
        { url: 'https://idee.test/' },
    ]) {
        assert.ok((await send('status', sender)).error);
    }
    assert.equal(executed, 0);
    assert.equal((await send('status', site)).value.version, '0.1.0');
    assert.equal(executed, 1);
});
await test('the paired site cannot confirm or cancel transfers directly', async () => {
    for (const operation of ['run', 'cancel', 'review'])
        assert.ok(
            (
                await send(operation, site, {
                    id: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                })
            ).error,
        );
    assert.equal(
        data['j:aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'].status,
        'pending',
    );
});
await test('only the extension review page can cancel, and a cancelled job cannot run', async () => {
    const sender = {
        url: 'chrome-extension://test-extension/review.html?job=aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
    };
    const response = await send('cancel', sender, {
        id: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
    });
    assert.equal(response.value.status, 'cancelled');
    assert.ok(
        (
            await send('run', sender, {
                id: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            })
        ).error,
    );
    assert.equal(
        data['j:aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'].status,
        'cancelled',
    );
});
