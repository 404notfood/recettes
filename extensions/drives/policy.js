export const TTL = 10 * 60 * 1000;
export const retailers = {
    leclerc: { name: 'E.Leclerc', url: 'https://www.leclercdrive.fr/' },
    carrefour: { name: 'Carrefour', url: 'https://www.carrefour.fr/' },
    intermarche: { name: 'Intermarché', url: 'https://www.intermarche.com/' },
};
export function retailerFor(url) {
    try {
        const parsed = new URL(url);
        if (parsed.protocol !== 'https:' || parsed.port) return null;
        if (/^fd\d+-courses\.leclercdrive\.fr$/.test(parsed.hostname))
            return 'leclerc';
        return (
            {
                'www.carrefour.fr': 'carrefour',
                'www.intermarche.com': 'intermarche',
            }[parsed.hostname] || null
        );
    } catch {
        return null;
    }
}
export function appOrigin(url) {
    const parsed = new URL(url);
    const local =
        ['localhost', '127.0.0.1'].includes(parsed.hostname) ||
        parsed.hostname.endsWith('.test');
    if (
        parsed.username ||
        parsed.password ||
        (parsed.protocol !== 'https:' &&
            !(local && parsed.protocol === 'http:'))
    )
        throw new Error(
            'Ouvrez le site Cuistot Club en HTTPS (ou sur un domaine local .test).',
        );
    if (
        /(^|\.)(leclercdrive\.fr|carrefour\.fr|intermarche\.com)$/.test(
            parsed.hostname,
        )
    )
        throw new Error('Ouvrez Cuistot Club pour le relier.');
    return parsed.origin;
}
export function selectedLines(input, quotes, now = Date.now()) {
    if (!Array.isArray(input) || !input.length || input.length > 60)
        throw new Error('Sélectionnez entre 1 et 60 lignes.');
    const lines = new Map();
    let context;
    for (const line of input) {
        const quote = quotes[line.quoteId];
        if (
            !quote ||
            !Number.isFinite(quote.createdAt) ||
            now - quote.createdAt >= TTL ||
            quote.createdAt > now
        )
            throw new Error('Prix expirés. Relancez la recherche.');
        if (
            !Number.isInteger(line.quantity) ||
            line.quantity < 1 ||
            line.quantity > 99
        )
            throw new Error('Indiquez un nombre de paquets entre 1 et 99.');
        const key = `${quote.retailer}|${quote.tabId}|${quote.store.id}`;
        if (context && context !== key)
            throw new Error('Le transfert doit concerner un seul magasin.');
        context = key;
        const previous = lines.get(quote.product.id);
        if (
            previous &&
            (previous.priceCents !== quote.product.priceCents ||
                previous.packaging !== quote.product.packaging)
        )
            throw new Error('Les prix ont changé. Relancez la recherche.');
        const quantity = (previous?.quantity || 0) + line.quantity;
        if (quantity > 99) throw new Error('Un produit dépasse 99 paquets.');
        lines.set(quote.product.id, {
            ...quote.product,
            query: quote.query,
            quantity,
        });
    }
    const first = quotes[input[0].quoteId];
    return {
        retailer: first.retailer,
        tabId: first.tabId,
        store: first.store,
        lines: [...lines.values()],
    };
}
