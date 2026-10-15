import { retailerFor, retailers, selectedLines, TTL } from './policy.js';

const queues = new Map();
let writing = false;
function serial(tabId, task) {
    const previous = queues.get(tabId) || Promise.resolve();
    const current = previous.catch(() => {}).then(task);
    queues.set(tabId, current);
    void current
        .catch(() => {})
        .finally(() => {
            if (queues.get(tabId) === current) queues.delete(tabId);
        });
    return current;
}
async function invoke(tabId, retailer, operation, payload = {}) {
    const tab = await chrome.tabs.get(tabId);
    if (retailerFor(tab.url) !== retailer)
        throw new Error('L’onglet du drive a changé. Reconnectez le magasin.');
    await chrome.scripting.executeScript({
        target: { tabId },
        world: 'MAIN',
        files: ['runtime.js'],
    });
    const [result] = await chrome.scripting.executeScript({
        target: { tabId },
        world: 'MAIN',
        func: async (method, input) =>
            globalThis.cuistotDriveRuntime.call(method, input),
        args: [operation, { ...payload, retailer }],
    });
    if (!result?.result?.ok)
        throw new Error(
            result?.result?.error ||
                'Le drive ne répond pas. Ouvrez son onglet et rechargez-le.',
        );
    return result.result.value;
}
async function status() {
    const tabs = await chrome.tabs.query({
        url: [
            'https://*.leclercdrive.fr/*',
            'https://www.carrefour.fr/*',
            'https://www.intermarche.com/*',
        ],
    });
    return {
        version: '0.1.0',
        tabs: await Promise.all(
            tabs
                .filter((tab) => retailerFor(tab.url))
                .slice(0, 12)
                .map(async (tab) => {
                    const retailer = retailerFor(tab.url);
                    try {
                        return {
                            tabId: tab.id,
                            retailer,
                            store: await serial(tab.id, () =>
                                invoke(tab.id, retailer, 'context'),
                            ),
                        };
                    } catch (error) {
                        return {
                            tabId: tab.id,
                            retailer,
                            error: error.message,
                        };
                    }
                }),
        ),
    };
}
async function search(payload) {
    const { tabId, retailer, query } = payload;
    if (
        !retailers[retailer] ||
        !Number.isInteger(tabId) ||
        typeof query !== 'string' ||
        query.trim().length < 2 ||
        query.length > 120
    )
        throw new Error('Recherche invalide.');
    return serial(tabId, async () => {
        const lastKey = `last:${tabId}`;
        const last = (await chrome.storage.session.get(lastKey))[lastKey] || 0;
        await new Promise((resolve) =>
            setTimeout(resolve, Math.max(0, 1000 - (Date.now() - last))),
        );
        await chrome.storage.session.set({ [lastKey]: Date.now() });
        const result = await invoke(tabId, retailer, 'search', {
            query: query.trim(),
        });
        const all = await chrome.storage.session.get(null);
        const expired = Object.keys(all).filter(
            (key) =>
                (key.startsWith('q:') || key.startsWith('j:')) &&
                Date.now() - all[key].createdAt >= TTL &&
                all[key].status !== 'running',
        );
        if (expired.length) await chrome.storage.session.remove(expired);
        const candidates = [];
        for (const product of result.products.slice(0, 5)) {
            const quoteId = crypto.randomUUID();
            const quote = {
                retailer,
                tabId,
                store: result.store,
                query,
                product,
                createdAt: Date.now(),
            };
            await chrome.storage.session.set({ [`q:${quoteId}`]: quote });
            candidates.push({
                ...product,
                quoteId,
                createdAt: quote.createdAt,
            });
        }
        return { store: result.store, products: candidates };
    });
}
async function prepare(payload) {
    const requested = payload?.lines;
    if (!Array.isArray(requested) || requested.length > 60)
        throw new Error('Panier invalide.');
    const keys = requested.map((line) => `q:${line.quoteId}`);
    const stored = await chrome.storage.session.get(keys);
    const quotes = Object.fromEntries(
        Object.entries(stored).map(([key, value]) => [key.slice(2), value]),
    );
    const selection = selectedLines(requested, quotes);
    const active = await serial(selection.tabId, () =>
        invoke(selection.tabId, selection.retailer, 'context'),
    );
    if (active.id !== selection.store.id)
        throw new Error(
            'Le magasin a changé. Recherchez à nouveau les produits.',
        );
    const id = crypto.randomUUID();
    const createdAt = Math.min(
        ...Object.values(quotes).map((quote) => quote.createdAt),
    );
    const { appOrigin } = await chrome.storage.local.get('appOrigin');
    const job = {
        ...selection,
        id,
        appOrigin,
        createdAt,
        status: 'pending',
        retailerLabel: retailers[selection.retailer].name,
        added: 0,
    };
    await chrome.storage.session.set({ [`j:${id}`]: job });
    await chrome.tabs.create({
        url: chrome.runtime.getURL(`review.html?job=${id}`),
    });
    return {
        id,
        status: 'pending',
        message:
            'Vérifiez puis confirmez les produits dans l’onglet de l’extension.',
    };
}
async function getJob(id) {
    if (typeof id !== 'string' || !/^[a-f0-9-]{36}$/.test(id))
        throw new Error('Transfert introuvable.');
    const job = (await chrome.storage.session.get(`j:${id}`))[`j:${id}`];
    if (!job)
        throw new Error('Transfert expiré. Recherchez à nouveau les produits.');
    return job;
}
async function run(id) {
    if (writing) throw new Error('Un transfert est déjà en cours.');
    writing = true;
    let job;
    try {
        job = await getJob(id);
        if (job.status !== 'pending' || Date.now() - job.createdAt >= TTL)
            throw new Error(
                'Ce transfert a déjà été traité ou a expiré. Recherchez les produits à nouveau.',
            );
        job.status = 'running';
        job.message =
            'Transfert en cours. En cas d’interruption, vérifiez le panier avant toute nouvelle tentative.';
        await chrome.storage.session.set({ [`j:${id}`]: job });
        for (const line of job.lines) {
            if (Date.now() - job.createdAt >= TTL)
                throw new Error(
                    'Prix expirés. Recherchez les produits restants.',
                );
            await serial(job.tabId, async () => {
                await new Promise((resolve) => setTimeout(resolve, 1100));
                const activeSite = await chrome.storage.local.get('appOrigin');
                const storedJob = await chrome.storage.session.get(`j:${id}`);
                if (
                    activeSite.appOrigin !== job.appOrigin ||
                    !storedJob[`j:${id}`]
                ) {
                    throw new Error('Le site a été déconnecté.');
                }
                await invoke(job.tabId, job.retailer, 'add', {
                    storeId: job.store.id,
                    product: line,
                    quantity: line.quantity,
                });
            });
            job.added++;
            await chrome.storage.session.set({ [`j:${id}`]: job });
        }
        job.status = 'done';
        job.message = `${job.added} référence(s) ajoutée(s). Rechargez le drive et vérifiez le panier, les promotions et les frais avant de commander.`;
    } catch (error) {
        if (!job || job.status !== 'running') throw error;
        job.status = 'stopped';
        job.message = `${job.added} référence(s) confirmée(s) avant interruption. ${error.message} Vérifiez le panier réel avant de recommencer : le dernier ajout peut avoir été reçu.`;
    } finally {
        if (job && job.status !== 'pending')
            await chrome.storage.session.set({ [`j:${id}`]: job });
        writing = false;
    }
    return job;
}
async function dispatch(message, sender) {
    const review =
        sender.url?.split('?')[0] === chrome.runtime.getURL('review.html');
    if (review) {
        if (message.operation === 'review') return getJob(message.payload?.id);
        if (message.operation === 'run') return run(message.payload?.id);
        if (message.operation === 'cancel') {
            if (writing)
                throw new Error(
                    'Un transfert est en cours. Vérifiez son suivi.',
                );
            const job = await getJob(message.payload?.id);
            if (writing || job.status !== 'pending')
                throw new Error('Ce transfert est déjà traité.');
            job.status = 'cancelled';
            job.message = 'Transfert annulé. Aucun produit ajouté.';
            await chrome.storage.session.set({ [`j:${job.id}`]: job });
            return job;
        }
    }
    const { appOrigin } = await chrome.storage.local.get('appOrigin');
    if (
        !appOrigin ||
        !sender.tab ||
        sender.frameId !== 0 ||
        new URL(sender.url).origin !== appOrigin
    )
        throw new Error('Reliez ce site depuis l’icône de l’extension.');
    if (message.operation === 'status') return status();
    if (message.operation === 'search') return search(message.payload || {});
    if (message.operation === 'prepare') return prepare(message.payload || {});
    if (message.operation === 'job') {
        const job = await getJob(message.payload?.id);
        return {
            id: job.id,
            status: job.status,
            message: job.message,
            added: job.added,
        };
    }
    throw new Error('Action non autorisée.');
}
chrome.runtime.onMessage.addListener((message, sender, respond) => {
    void dispatch(message, sender)
        .then((value) => respond({ value }))
        .catch((error) =>
            respond({
                error: error.message || 'Connexion au drive interrompue.',
            }),
        );
    return true;
});
