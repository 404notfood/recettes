import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    appOrigin,
    retailerFor,
    selectedLines,
    TTL,
} from '../../extensions/drives/policy.js';

const now = 2000000;
const quote = (overrides = {}) => ({
    retailer: 'carrefour',
    tabId: 10,
    store: { id: '8000-150-1000', name: 'Drive test' },
    createdAt: now - 1000,
    product: {
        id: '1234567890123',
        name: 'Riz',
        priceCents: 189,
        packaging: '500 g',
    },
    ...overrides,
});

await test('only the three exact retailer origins are accepted', () => {
    assert.equal(retailerFor('https://www.carrefour.fr/'), 'carrefour');
    assert.equal(
        retailerFor(
            'https://fd9-courses.leclercdrive.fr/magasin-053701-053701/',
        ),
        'leclerc',
    );
    assert.equal(retailerFor('https://www.intermarche.com/'), 'intermarche');
    for (const url of [
        'https://www.carrefour.fr.evil.test/',
        'https://www.carrefour.fr:8443/',
        'http://www.carrefour.fr/',
        'https://other.leclercdrive.fr/',
        'https://evil.test/?url=https://www.carrefour.fr',
    ])
        assert.equal(retailerFor(url), null);
});
await test('pairing rejects credentials, retailer pages and insecure remote sites', () => {
    assert.equal(
        appOrigin('https://idee.test/menus/1/drives'),
        'https://idee.test',
    );
    assert.equal(
        appOrigin('http://localhost:8000/menus'),
        'http://localhost:8000',
    );
    for (const url of [
        'http://example.com/',
        'https://www.carrefour.fr/',
        'https://name:password@example.com/',
        'file:///tmp/site',
    ])
        assert.throws(() => appOrigin(url));
});
await test('pack counts are consolidated without changing existing carts', () => {
    const basket = selectedLines(
        [
            { quoteId: 'a', quantity: 2 },
            { quoteId: 'b', quantity: 3 },
        ],
        { a: quote(), b: quote() },
        now,
    );
    assert.equal(basket.lines.length, 1);
    assert.equal(basket.lines[0].quantity, 5);
    assert.equal(basket.lines[0].priceCents, 189);
});
await test('quotes from different stores or tabs cannot be mixed', () => {
    for (const other of [
        quote({ tabId: 11 }),
        quote({ store: { id: 'other' } }),
        quote({ retailer: 'leclerc' }),
    ])
        assert.throws(
            () =>
                selectedLines(
                    [
                        { quoteId: 'a', quantity: 1 },
                        { quoteId: 'b', quantity: 1 },
                    ],
                    { a: quote(), b: other },
                    now,
                ),
            /seul magasin/,
        );
});
await test('expired, future, unknown quotes and invalid quantities are rejected', () => {
    for (const createdAt of [now - TTL, now + 1, NaN])
        assert.throws(() =>
            selectedLines(
                [{ quoteId: 'a', quantity: 1 }],
                { a: quote({ createdAt }) },
                now,
            ),
        );
    for (const quantity of [0, -1, 1.5, 100, '2', NaN])
        assert.throws(() =>
            selectedLines([{ quoteId: 'a', quantity }], { a: quote() }, now),
        );
    assert.throws(() =>
        selectedLines([{ quoteId: 'missing', quantity: 1 }], {}, now),
    );
    assert.throws(() => selectedLines([], {}, now));
    assert.throws(() =>
        selectedLines(
            [
                { quoteId: 'a', quantity: 60 },
                { quoteId: 'a', quantity: 60 },
            ],
            { a: quote() },
            now,
        ),
    );
});
