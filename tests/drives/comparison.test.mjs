import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    cheapestSelection,
    coversNeed,
    compatibleProduct,
    packAmount,
    validSelection,
    fuelCost,
} from '../../resources/js/lib/drive-comparison.ts';

const now = 1_800_000_000_000;
const need = { id: 'riz', name: 'Riz', quantity: 600, unit: 'g' };
const product = (id, priceCents, packaging, extra = {}) => ({
    id,
    quoteId: id,
    name: 'Riz long grain',
    packaging,
    priceCents,
    unitPrice: '',
    createdAt: now,
    ...extra,
});

await test('the cheapest basket line covers the required quantity, rather than picking the cheapest pack', () => {
    const selection = cheapestSelection(
        need,
        [product('small', 100, '500 g'), product('large', 170, '1 kg')],
        now,
    );
    assert.equal(selection.product.id, 'large');
    assert.equal(selection.quantity, '1');
    const smallNeed = cheapestSelection(
        { ...need, quantity: 200 },
        [product('small', 100, '500 g'), product('large', 170, '1 kg')],
        now,
    );
    assert.equal(smallNeed.product.id, 'small');
});
await test('whole pack counts round upward and compatible weight and volume units convert', () => {
    assert.equal(
        cheapestSelection(need, [product('small', 100, '250 g')], now).quantity,
        '3',
    );
    assert.equal(packAmount({ name: '', packaging: '6 x 1,5 L' }, 'ml'), 9000);
    assert.equal(packAmount({ name: '', packaging: '75 cl' }, 'l'), 0.75);
    assert.equal(packAmount({ name: '', packaging: '2 x 125 g' }, 'kg'), 0.25);
    assert.equal(packAmount({ name: '', packaging: '6 œufs' }, 'pièce'), 6);
    assert.equal(
        packAmount({ name: '', packaging: '2 x 6 œufs' }, 'pièce'),
        12,
    );
});
await test('unknown sizes, incompatible dimensions and ambiguous drained weights remain unselected', () => {
    for (const packaging of [
        'Le paquet',
        '500 ml',
        '500 g + 100 g offerts',
        'poids net 500 g, égoutté 300 g',
    ])
        assert.equal(
            cheapestSelection(
                need,
                [product('ambiguous', 100, packaging)],
                now,
            ),
            null,
            packaging,
        );
    assert.equal(
        cheapestSelection(
            { ...need, quantity: null },
            [product('one', 100, '1 kg')],
            now,
        ),
        null,
    );
    assert.equal(packAmount({ name: '', packaging: '1 kg' }, 'pièce'), null);
});
await test('a cheap derivative cannot replace the requested ingredient', () => {
    for (const name of [
        'Lait de riz',
        'Farine de riz',
        'Galettes de riz sauce soja',
    ])
        assert.equal(
            compatibleProduct(need, product('wrong', 10, '500 g', { name })),
            false,
            name,
        );
    assert.equal(
        compatibleProduct(
            { ...need, name: 'Pois chiches cuits' },
            product('dry', 100, '500 g', { name: 'Pois chiches secs' }),
        ),
        false,
    );
    assert.equal(
        compatibleProduct(
            { ...need, name: 'Pois chiches cuits' },
            product('cooked', 100, '500 g', { name: 'Pois chiches cuits' }),
        ),
        true,
    );
});
await test('expired, future, invalid prices and excessive pack counts are excluded', () => {
    for (const candidate of [
        product('old', 100, '1 kg', { createdAt: now - 600000 }),
        product('future', 100, '1 kg', { createdAt: now + 1 }),
        product('zero', 0, '1 kg'),
        product('negative', -1, '1 kg'),
        product('fraction', 100.5, '1 kg'),
    ])
        assert.equal(cheapestSelection(need, [candidate], now), null);
    assert.equal(
        cheapestSelection(
            { ...need, quantity: 100000 },
            [product('tiny', 100, '1 g')],
            now,
        ),
        null,
    );
    assert.equal(
        validSelection(
            {
                product: product('overflow', Number.MAX_SAFE_INTEGER, '1 kg'),
                quantity: '99',
            },
            now,
        ),
        false,
    );
});
await test('fuel uses the total return-trip distance at six litres per hundred kilometres', () => {
    assert.equal(fuelCost('20', '1.80'), 216);
    assert.equal(fuelCost('20', '1,80'), 216);
    assert.equal(fuelCost('0', '1.80'), 0);
    assert.equal(fuelCost('', '1.80'), null);
    assert.equal(fuelCost('20', ''), null);
    assert.equal(fuelCost('-1', '1.80'), null);
    assert.equal(fuelCost('20', '0'), null);
});

await test('under-covered lines do not make a basket comparable and unknown formats need an explicit check', () => {
    const line = { product: product('small', 100, '500 g'), quantity: '1' };
    assert.equal(coversNeed(need, line, now), false);
    assert.equal(coversNeed(need, { ...line, confirmed: true }, now), false);
    assert.equal(coversNeed(need, { ...line, quantity: '2' }, now), true);
    const unknown = { ...line, product: product('unknown', 100, 'Le paquet') };
    assert.equal(coversNeed(need, unknown, now), false);
    assert.equal(coversNeed(need, { ...unknown, confirmed: true }, now), true);
    assert.equal(
        coversNeed(need, { ...unknown, confirmed: true, quantity: '0' }, now),
        false,
    );
});
