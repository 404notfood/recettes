import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';

const source = await readFile(
    new URL('../../extensions/drives/runtime.js', import.meta.url),
    'utf8',
);
function load({
    host = 'www.carrefour.fr',
    pathname = '/',
    cookie = '',
    storage = {},
    routes = {},
} = {}) {
    const calls = [];
    const context = {
        URL,
        URLSearchParams,
        AbortSignal,
        location: {
            hostname: host,
            origin: `https://${host}`,
            href: `https://${host}${pathname}`,
            pathname,
        },
        document: { cookie, title: 'Drive test' },
        localStorage: { getItem: (key) => storage[key] || null },
        fetch: async (url, options) => {
            const parsed = new URL(url);
            calls.push({
                path: parsed.pathname,
                query: parsed.search,
                ...options,
            });
            const route =
                routes[`${options.method} ${parsed.pathname}`] ??
                routes[parsed.pathname];
            const body = typeof route === 'function' ? route(options) : route;
            assert.notEqual(
                body,
                undefined,
                `Unexpected request: ${parsed.pathname}`,
            );
            return {
                ok: true,
                url,
                text: async () =>
                    typeof body === 'string' ? body : JSON.stringify(body),
            };
        },
    };
    vm.runInNewContext(source, context);
    return { runtime: context.cuistotDriveRuntime, calls };
}
const product = {
    id: '1234567890123',
    ean: '1234567890123',
    name: 'Riz',
    packaging: '500 g',
    priceCents: 189,
    query: 'riz',
};
const cart = (quantity) => ({
    cart: {
        facilityServiceId: '8000-150-1000',
        storeRef: '1000',
        type: 'drive_clcv',
        items: [
            {
                products: quantity
                    ? [
                          {
                              counter: quantity,
                              product: { attributes: { ean: product.id } },
                          },
                      ]
                    : [],
            },
        ],
    },
});
const carrefour = {
    data: {
        products: [
            {
                attributes: {
                    ean: product.ean,
                    title: 'Riz',
                    slug: 'riz',
                    format: '500 g',
                    offers: {
                        [product.ean]: {
                            '8000-150-1000': {
                                attributes: {
                                    price: { price: 1.89 },
                                    availability: { purchasable: true },
                                },
                            },
                        },
                    },
                },
            },
        ],
    },
};

await test('prices never silently become zero and JSON parsing does not evaluate scripts', () => {
    const { helpers } = load().runtime;
    assert.equal(helpers.cents('1,89 €'), 189);
    for (const value of [null, undefined, '', 0, -1, 'inconnu'])
        assert.equal(helpers.cents(value), null);
    assert.equal(
        helpers.jsonAt('prefix {"a":"} { \\\"", "b":[1]} suffix', 0).b[0],
        1,
    );
    assert.equal(helpers.jsonAt('{evil: globalThis.secret}', 0), null);
});
await test('Carrefour never falls back to a price from another store', () => {
    const helper = load().runtime.helpers.carrefourProduct;
    assert.equal(helper(carrefour.data.products[0], 'other-store'), null);
    assert.equal(
        helper(carrefour.data.products[0], '8000-150-1000').priceCents,
        189,
    );
});
await test('Carrefour adds to the existing quantity using an absolute target and confirms it', async () => {
    let quantity = 2;
    const { runtime, calls } = load({
        routes: {
            '/api/me': {
                userId: 'only-read-in-retailer-tab',
                email: 'private@example.test',
            },
            'GET /api/cart': () => cart(quantity),
            'PATCH /api/cart': (options) => {
                const item = JSON.parse(options.body).items[0];
                assert.equal(item.counter, 5);
                quantity = item.counter;
                return cart(quantity);
            },
            '/autocomplete': carrefour,
            [`/p/riz-${product.ean}`]: `<script>{"8000-150-1000-${product.ean}":{"attributes":{"availability":{"purchasable":true},"price":{"price":1.89}}}}</script>`,
        },
    });
    const result = await runtime.call('add', {
        retailer: 'carrefour',
        storeId: '8000-150-1000',
        product,
        quantity: 3,
    });
    assert.equal(result.ok, true, result.error);
    assert.equal(calls.filter((call) => call.method === 'PATCH').length, 1);
    assert.equal(
        JSON.stringify(result).includes('private@example.test'),
        false,
    );
});
await test('a store or price change blocks all cart writes', async () => {
    const { runtime, calls } = load({
        routes: {
            '/api/me': { userId: 'test' },
            '/api/cart': cart(2),
            '/autocomplete': carrefour,
        },
    });
    const changed = await runtime.call('add', {
        retailer: 'carrefour',
        storeId: 'another-drive',
        product,
        quantity: 1,
    });
    assert.equal(changed.ok, false);
    const price = await runtime.call('add', {
        retailer: 'carrefour',
        storeId: '8000-150-1000',
        product: { ...product, priceCents: 180 },
        quantity: 1,
    });
    assert.equal(price.ok, false);
    assert.equal(
        calls.some((call) => call.method !== 'GET'),
        false,
    );
});
await test('Intermarché uses the internal product id and a positive delta, then reads the returned quantity', async () => {
    let quantity = 2;
    const item = {
        itemId: '789',
        produitEan13: product.ean,
        libelle: 'Riz',
        conditionnement: '500 g',
        prix: 1.89,
        stock: 20,
        dispoCataloguePdv: true,
        typeProduit: { code: 'PU' },
    };
    const { runtime, calls } = load({
        host: 'www.intermarche.com',
        cookie: `itm_pdv=${encodeURIComponent(JSON.stringify({ ref: '10000_1', name: 'Drive test', isEcommerce: true }))}`,
        storage: {
            'persist:auth': JSON.stringify({
                user: JSON.stringify({
                    id: 'customer-test',
                    email: 'not-returned',
                }),
            }),
        },
        routes: {
            [`/api/service/produits/v3/pdvs/10000_1/produits/${product.ean}`]:
                item,
            '/api/service/panier/v1/stores/10000_1/carts': (options) => {
                const body = JSON.parse(options.body);
                if (body.events.length) {
                    assert.equal(body.events[0].quantity, 3);
                    assert.equal(body.events[0].itemId, '789');
                    quantity += body.events[0].quantity;
                }
                return { carts: [{ items: [{ id: '789', quantity }] }] };
            },
        },
    });
    const result = await runtime.call('add', {
        retailer: 'intermarche',
        storeId: '10000_1',
        product: { ...product, id: '789' },
        quantity: 3,
    });
    assert.equal(result.ok, true, result.error);
    assert.equal(quantity, 5);
    assert.equal(calls.filter((call) => call.method === 'POST').length, 2);
});
await test('Leclerc parses product widgets without executing embedded page scripts', () => {
    const products = load().runtime.helpers.leclercProducts(
        `widget({"objElement":{"iIdProduit":2612,"sLibelleLigne1":"Riz","nrPVUnitaireTTC":1.89,"iQteDisponible":5}}); evil();`,
    );
    assert.equal(products[0].id, '2612');
    assert.equal(products[0].priceCents, 189);
});

await test('Leclerc preserves existing quantities and uses the selected store for the absolute target', async () => {
    const localProduct = { ...product, id: '2612', packaging: '500 g' };
    const widget = {
        iIdProduit: 2612,
        sLibelleLigne1: 'Riz',
        sLibelleLigne2: '500 g',
        nrPVUnitaireTTC: 1.89,
        iQteDisponible: 20,
    };
    const { runtime, calls } = load({
        host: 'fd9-courses.leclercdrive.fr',
        pathname: '/magasin-053701-053701-ville.aspx',
        routes: {
            '/magasin-053701-053701/recherche.aspx': () =>
                `<script>{"objElement":${JSON.stringify(widget)},"lstProduits":[{"iIdProduit":2612,"iQuantitePanier":2}]}</script>`,
            '/magasin-053701-053701/panier.aspx': (options) => {
                const payload = JSON.parse(
                    new URLSearchParams(options.body).get('d'),
                );
                assert.equal(payload.iQuantite, 5);
                assert.equal(payload.sNoPointLivraison, '053701');
                return [{ sIdUnique: 'Produit2612', iQuantitePanier: 5 }];
            },
        },
    });
    const result = await runtime.call('add', {
        retailer: 'leclerc',
        storeId: '053701-053701',
        product: localProduct,
        quantity: 3,
    });
    assert.equal(result.ok, true, result.error);
    assert.equal(calls.filter((call) => call.method === 'POST').length, 1);
});

await test('variable-price Intermarché products and stock shortages are excluded', () => {
    const helper = load().runtime.helpers.itmProduct;
    const item = { itemId: '789', prix: 2, stock: 10, dispoCataloguePdv: true };
    for (const code of ['PUP', 'PUV', undefined])
        assert.equal(helper({ ...item, typeProduit: { code } }), null);
    assert.equal(
        helper({ ...item, stock: 0, typeProduit: { code: 'PU' } }),
        null,
    );
});
await test('an unavailable Carrefour page prevents the PATCH even when search advertises stock', async () => {
    const { runtime, calls } = load({
        routes: {
            '/api/me': { userId: 'test' },
            '/api/cart': cart(0),
            '/autocomplete': carrefour,
            [`/p/riz-${product.ean}`]: '{}',
        },
    });
    assert.equal(
        (
            await runtime.call('add', {
                retailer: 'carrefour',
                storeId: '8000-150-1000',
                product,
                quantity: 1,
            })
        ).ok,
        false,
    );
    assert.equal(
        calls.some((call) => call.method === 'PATCH'),
        false,
    );
});
