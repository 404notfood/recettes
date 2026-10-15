/* Runs only in the chosen retailer tab. No login tokens leave this context. */
(() => {
    const fail = (message) => {
        throw new Error(message);
    };
    const text = (value, max = 180) =>
        typeof value === 'string' ? value.slice(0, max) : '';
    const cents = (value) => {
        if (value === null || value === undefined || value === '') return null;
        const number = Number(
            typeof value === 'string'
                ? value.replace(/\s/g, '').replace(',', '.').replace('€', '')
                : value,
        );
        return Number.isFinite(number) && number > 0
            ? Math.round(number * 100)
            : null;
    };
    function jsonAt(source, offset) {
        const start = source.slice(offset).search(/[[{]/);
        if (start < 0) return null;
        const from = offset + start;
        let depth = 0,
            quoted = false,
            escaped = false;
        for (let i = from; i < source.length; i++) {
            const char = source[i];
            if (quoted) {
                if (escaped) escaped = false;
                else if (char === '\\') escaped = true;
                else if (char === '"') quoted = false;
            } else if (char === '"') quoted = true;
            else if (char === '{' || char === '[') depth++;
            else if (char === '}' || char === ']') {
                if (--depth === 0) {
                    try {
                        return JSON.parse(source.slice(from, i + 1));
                    } catch {
                        return null;
                    }
                }
            }
        }
        return null;
    }
    async function request(
        path,
        { method = 'GET', headers = {}, body, html = false } = {},
    ) {
        const url = new URL(path, location.origin);
        if (url.origin !== location.origin) fail('Adresse du drive invalide.');
        let response;
        try {
            response = await fetch(url.href, {
                method,
                headers,
                ...(method === 'GET' || body === undefined ? {} : { body }),
                credentials: 'same-origin',
                signal: AbortSignal.timeout(20000),
            });
        } catch {
            fail('Réponse non reçue. Vérifiez le drive dans son onglet.');
        }
        if (!response.ok)
            fail(
                [401, 403, 429].includes(response.status)
                    ? 'Connexion ou vérification nécessaire : ouvrez le drive et rechargez-le.'
                    : 'Le drive refuse cette opération. Ouvrez son onglet pour vérifier.',
            );
        if (new URL(response.url || url.href).origin !== location.origin)
            fail('La session du drive a changé.');
        const content = await response.text();
        if (content.length > 5000000)
            fail('Réponse du drive trop volumineuse.');
        if (html) return content;
        try {
            return JSON.parse(content);
        } catch {
            fail(
                'Le drive demande une connexion ou son format a changé. Ouvrez son onglet.',
            );
        }
    }
    const xrw = { 'X-Requested-With': 'XMLHttpRequest' };
    const itmHeaders = () => ({
        'x-red-version': String(
            globalThis.publicRuntimeConfig?.X_RED_VERSION || '3',
        ),
        'x-red-device': String(
            globalThis.publicRuntimeConfig?.X_RED_DEVICE || 'red_fo_desktop',
        ),
    });
    function itmPdv() {
        try {
            return JSON.parse(
                decodeURIComponent(
                    document.cookie.match(/(?:^|;\s*)itm_pdv=([^;]+)/)?.[1] ||
                        'null',
                ),
            );
        } catch {
            return null;
        }
    }
    function itmCustomer() {
        try {
            return (
                JSON.parse(
                    JSON.parse(localStorage.getItem('persist:auth') || '{}')
                        .user || 'null',
                )?.id || null
            );
        } catch {
            return null;
        }
    }
    function leclercStore() {
        const match = location.pathname.match(/magasin-(\d{6})-(\d{6})/);
        if (!match)
            fail(
                'Choisissez votre magasin sur Leclerc Drive, puis revenez ici.',
            );
        return {
            id: `${match[1]}-${match[2]}`,
            point: match[1],
            base: `/magasin-${match[1]}-${match[2]}`,
        };
    }
    async function context(retailer) {
        if (retailer === 'leclerc') {
            const store = leclercStore();
            return {
                id: store.id,
                name: text(document.title) || `Drive ${store.point}`,
                url: location.origin + location.pathname,
            };
        }
        if (retailer === 'intermarche') {
            const pdv = itmPdv();
            if (!pdv?.ref || pdv.ref === '00000' || !pdv.isEcommerce)
                fail('Choisissez un drive sur Intermarché.');
            if (!itmCustomer())
                fail('Connectez-vous sur Intermarché et rechargez son onglet.');
            let name = text(pdv.name) || `Drive ${pdv.ref}`;
            try {
                name = decodeURIComponent(name);
            } catch {
                /* retain readable label */
            }
            return {
                id: String(pdv.ref),
                name,
                url: 'https://www.intermarche.com/',
            };
        }
        if (retailer !== 'carrefour') fail('Enseigne non prise en charge.');
        const [profile, basket] = await Promise.all([
            request('/api/me', { headers: xrw }),
            request('/api/cart', { headers: xrw }),
        ]);
        if (!profile.userId) fail('Connectez-vous sur Carrefour.');
        const cart = basket.cart;
        if (
            !cart?.facilityServiceId ||
            cart.storeRef === '6' ||
            cart.facilityServiceId.endsWith('-6') ||
            cart.type !== 'drive_clcv'
        )
            fail('Choisissez un drive Carrefour.');
        const offline =
            globalThis.__INITIAL_STATE__?.pinia?.offline?.offlineStore;
        return {
            id: cart.facilityServiceId,
            name:
                offline?.basketServiceId === cart.facilityServiceId
                    ? text(offline.name)
                    : `Drive Carrefour ${cart.storeRef}`,
            url: 'https://www.carrefour.fr/',
        };
    }
    function leclercProducts(html) {
        const products = new Map();
        for (const match of html.matchAll(/"objElement"\s*:/g)) {
            const item = jsonAt(html, match.index + match[0].length);
            if (!item?.iIdProduit) continue;
            const priceCents = cents(
                item.nrPVUnitaireTTC ?? item.sPrixUnitaire,
            );
            if (!priceCents || !(item.iQteDisponible > 0)) continue;
            products.set(String(item.iIdProduit), {
                id: String(item.iIdProduit),
                name: text(
                    `${item.sLibelleLigne1 || ''} ${item.sLibelleLigne2 || ''}`.trim(),
                ),
                packaging: text(
                    item.sLibelleLigne3 ||
                        item.sConditionnement ||
                        item.sLibelleLigne2 ||
                        '',
                ),
                priceCents,
                unitPrice: text(item.sPrixParUniteDeMesure),
                cartQuantity: Number(item.iQuantitePanier) || 0,
            });
        }
        return [...products.values()];
    }
    function itmProduct(item) {
        const priceCents = cents(item.prix);
        const id = String(item.itemId ?? item.identifier ?? '');
        if (
            !id ||
            !priceCents ||
            !item.dispoCataloguePdv ||
            !(item.stock > 0) ||
            item.typeProduit?.code !== 'PU'
        )
            return null;
        return {
            id,
            ean: text(item.produitEan13),
            name: text(`${item.marque || ''} ${item.libelle || ''}`.trim()),
            packaging: text(item.conditionnement),
            priceCents,
            unitPrice: item.prixKg
                ? `${item.prixKg} ${text(item.unitePrixVente?.value)}`
                : '',
        };
    }
    function carrefourProduct(item, storeId) {
        const entry = item.attributes || item;
        const offer = entry.offers?.[entry.ean]?.[storeId]?.attributes;
        const priceCents = cents(offer?.price?.price);
        if (
            !priceCents ||
            offer?.availability?.purchasable !== true ||
            !entry.ean ||
            !entry.slug
        )
            return null;
        return {
            id: String(entry.ean),
            ean: String(entry.ean),
            name: text(`${entry.brand || ''} ${entry.title || ''}`.trim()),
            packaging: text(entry.format || entry.packaging),
            priceCents,
            unitPrice: text(offer.price.perUnitLabel),
            slug: text(entry.slug, 300),
        };
    }
    async function search(retailer, query, suppliedStore) {
        const store = suppliedStore || (await context(retailer));
        let products;
        if (retailer === 'leclerc') {
            const html = await request(
                `${leclercStore().base}/recherche.aspx?TexteRecherche=${encodeURIComponent(query)}`,
                { html: true },
            );
            products = leclercProducts(html);
            if (
                !html.includes('iIdProduit') &&
                !/aucun|0 produit|pas de résultat/i.test(html)
            )
                fail('Catalogue Leclerc non lisible. Rechargez son onglet.');
        } else if (retailer === 'intermarche') {
            const result = await request(
                `/api/service/produits/v3/pdvs/${encodeURIComponent(store.id)}/products/suggestions?keyword=${encodeURIComponent(query)}`,
                { headers: itmHeaders() },
            );
            if (!Array.isArray(result.produits))
                fail('Le catalogue Intermarché a changé de format.');
            products = result.produits.map(itmProduct).filter(Boolean);
        } else {
            const result = await request(
                `/autocomplete?q=${encodeURIComponent(query)}&maxResults=12&productCard=true`,
                { headers: xrw },
            );
            if (!Array.isArray(result.data?.products))
                fail('Le catalogue Carrefour a changé de format.');
            products = result.data.products
                .map((item) => carrefourProduct(item, store.id))
                .filter(Boolean);
        }
        return { store, products: products.slice(0, 5) };
    }
    const carrefourQuantity = (data, id) => {
        if (!data.cart || !Array.isArray(data.cart.items))
            fail('Panier Carrefour non lisible.');
        const row = data.cart.items
            .flatMap((group) => group.products || [])
            .find((item) => item.product?.attributes?.ean === id);
        return row ? Number(row.counter) : 0;
    };
    const itmQuantity = (data, id) => {
        if (!Array.isArray(data.carts)) fail('Panier Intermarché non lisible.');
        const row = data.carts
            .flatMap((cart) => cart.items || [])
            .find((item) => String(item.id) === id);
        return row ? Number(row.quantity) : 0;
    };
    async function itmCart(storeId, events) {
        const customer = itmCustomer();
        if (!customer) fail('Reconnectez-vous sur Intermarché.');
        return request(
            `/api/service/panier/v1/stores/${encodeURIComponent(storeId)}/carts?customerId=${encodeURIComponent(customer)}`,
            {
                method: 'POST',
                headers: {
                    ...itmHeaders(),
                    'x-oauth': 'true',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    customerDateTime: new Date().toISOString(),
                    events,
                }),
            },
        );
    }
    async function add(retailer, { storeId, product, quantity }) {
        if (!Number.isInteger(quantity) || quantity < 1 || quantity > 99)
            fail('Nombre de paquets invalide.');
        const store = await context(retailer);
        if (store.id !== storeId)
            fail('Le magasin a changé. Relancez la comparaison.');
        let fresh;
        if (retailer === 'intermarche') {
            fresh = itmProduct(
                await request(
                    `/api/service/produits/v3/pdvs/${encodeURIComponent(store.id)}/produits/${encodeURIComponent(product.ean)}?extensions=PRIX&extensions=INFORMATIONS`,
                    { headers: itmHeaders() },
                ),
            );
        } else
            fresh = (
                await search(
                    retailer,
                    retailer === 'carrefour' ? product.ean : product.query,
                    store,
                )
            ).products.find((item) => item.id === product.id);
        if (
            !fresh ||
            fresh.id !== product.id ||
            fresh.priceCents !== product.priceCents ||
            fresh.packaging !== product.packaging
        )
            fail(
                'Produit, conditionnement ou prix modifié. Recherchez les produits à nouveau.',
            );
        if (retailer === 'carrefour') {
            const html = await request(`/p/${fresh.slug}-${fresh.ean}`, {
                html: true,
            });
            const marker = JSON.stringify(`${store.id}-${fresh.ean}`) + ':';
            const index = html.indexOf(marker);
            const offer =
                index < 0
                    ? null
                    : jsonAt(html, index + marker.length)?.attributes;
            if (
                offer?.availability?.purchasable !== true ||
                cents(offer.price?.price) !== fresh.priceCents
            )
                fail(
                    'Disponibilité ou prix Carrefour à vérifier sur la fiche du produit.',
                );
            const before = await request('/api/cart', { headers: xrw });
            if (before.cart?.facilityServiceId !== store.id)
                fail('Le drive Carrefour a changé.');
            const target = carrefourQuantity(before, product.id) + quantity;
            const after = await request('/api/cart', {
                method: 'PATCH',
                headers: { ...xrw, 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    items: [
                        {
                            basketServiceId: store.id,
                            counter: target,
                            ean: product.id,
                            subBasketType: 'drive_clcv',
                        },
                    ],
                }),
            });
            if (
                after.cart?.facilityServiceId !== store.id ||
                carrefourQuantity(after, product.id) !== target
            )
                fail(
                    'Ajout Carrefour incomplet. Vérifiez les quantités dans le panier.',
                );
        } else if (retailer === 'intermarche') {
            const before = await itmCart(store.id, []);
            if (itmPdv()?.ref !== store.id)
                fail('Le drive Intermarché a changé.');
            const target = itmQuantity(before, product.id) + quantity;
            const after = await itmCart(store.id, [
                {
                    catalog: 'PDV',
                    itemId: product.id,
                    quantity,
                    dateTime: new Date().toISOString(),
                    type: 'QUANTITY',
                    acceptSubstitution: false,
                },
            ]);
            if (
                itmQuantity(after, product.id) !== target ||
                Object.values(after.metaData?.invalid || {}).some(
                    (value) => Array.isArray(value) && value.length,
                )
            )
                fail('Ajout Intermarché incomplet. Vérifiez le panier.');
        } else {
            const selected = leclercStore();
            const html = await request(
                `${selected.base}/recherche.aspx?TexteRecherche=cuistotclubpanierverification`,
                { html: true },
            );
            const marker = /"lstProduits"\s*:/.exec(html);
            const cart = marker
                ? jsonAt(html, marker.index + marker[0].length)
                : null;
            if (!Array.isArray(cart))
                fail(
                    'Le panier Leclerc ne peut pas être vérifié. Ouvrez le drive.',
                );
            const row = cart
                .map((item) => item.objElement || item)
                .find((item) => String(item.iIdProduit) === product.id);
            const target =
                Number(row?.iQuantitePanier || row?.iQtePanier || 0) + quantity;
            const payload = {
                eTypeAction: 1,
                iIdProduit: product.id,
                iQuantite: target,
                sNoPointLivraison: selected.point,
                objContexteProvenanceArticle: {
                    eOrigine: 4,
                    eTypePage: 3,
                    sTexteRecherche: product.query,
                    eVue: 0,
                    sInformationsComplementaires: 'uni-2',
                },
            };
            const after = await request(`${selected.base}/panier.aspx?op=1`, {
                method: 'POST',
                headers: {
                    ...xrw,
                    'Content-Type':
                        'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: new URLSearchParams({
                    d: JSON.stringify(payload),
                }).toString(),
            });
            const event = Array.isArray(after)
                ? after.find(
                      (item) => item.sIdUnique === `Produit${product.id}`,
                  )
                : null;
            if (!event || Number(event.iQuantitePanier) !== target)
                fail('Ajout Leclerc non confirmé. Vérifiez le panier.');
        }
        return { confirmed: true };
    }
    globalThis.cuistotDriveRuntime = {
        helpers: {
            cents,
            jsonAt,
            leclercProducts,
            itmProduct,
            carrefourProduct,
        },
        async call(operation, input) {
            try {
                const actual =
                    location.hostname === 'www.carrefour.fr'
                        ? 'carrefour'
                        : location.hostname === 'www.intermarche.com'
                          ? 'intermarche'
                          : /^fd\d+-courses\.leclercdrive\.fr$/.test(
                                  location.hostname,
                              )
                            ? 'leclerc'
                            : null;
                if (input.retailer !== actual || !actual)
                    fail('Onglet du drive invalide.');
                if (operation === 'context')
                    return { ok: true, value: await context(actual) };
                if (operation === 'search')
                    return {
                        ok: true,
                        value: await search(actual, input.query),
                    };
                if (operation === 'add')
                    return { ok: true, value: await add(actual, input) };
                fail('Action du drive invalide.');
            } catch (error) {
                return { ok: false, error: error.message };
            }
        },
    };
})();
