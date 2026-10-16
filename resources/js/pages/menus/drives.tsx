import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    Check,
    Download,
    LoaderCircle,
    RefreshCw,
    ShoppingBasket,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    createDriveClient,
    euros,
    retailers,
    type DriveTab,
    type Retailer,
    type SearchResult,
    type Transfer,
} from '@/lib/drives';
import {
    cheapestSelection,
    coversNeed,
    fuelCost,
    packAmount,
    validSelection,
    type ProductSelection,
} from '@/lib/drive-comparison';

type Item = { id: string; name: string; quantity: number | null; unit: string };
type Result = SearchResult & { error?: string };
type Selection = ProductSelection;
const keyFor = (retailer: Retailer, itemId: string) => `${retailer}:${itemId}`;

export default function MenuDrives({
    plan,
    items,
}: {
    plan: { id: number; title: string; people: number };
    items: Item[];
}) {
    const client = useRef<ReturnType<typeof createDriveClient> | null>(null);
    const [tabs, setTabs] = useState<DriveTab[]>([]);
    const [active, setActive] = useState<Partial<Record<Retailer, number>>>({});
    const [included, setIncluded] = useState(
        () => new Set(items.map((item) => item.id)),
    );
    const [results, setResults] = useState<Record<string, Result>>({});
    const [selections, setSelections] = useState<Record<string, Selection>>({});
    const [queries, setQueries] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState('');
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [transfer, setTransfer] = useState<Transfer | null>(null);
    const [fuelPrice, setFuelPrice] = useState('');
    const [distances, setDistances] = useState<
        Partial<Record<Retailer, string>>
    >({});
    const [progress, setProgress] = useState({ done: 0, total: 0 });
    const [now, setNow] = useState(() => Date.now());
    const stopComparison = useRef(false);
    useEffect(() => {
        const connection = createDriveClient();
        client.current = connection;
        return () => {
            stopComparison.current = true;
            client.current = null;
            connection.dispose();
        };
    }, []);
    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 30000);
        return () => clearInterval(timer);
    }, []);
    async function request<T>(
        operation: 'status' | 'open' | 'search' | 'prepare' | 'job',
        payload?: unknown,
    ) {
        if (!client.current)
            throw new Error('Rechargez cette page pour relier l’extension.');
        return client.current.request<T>(operation, payload);
    }
    async function openDrives() {
        setBusy('open');
        setError('');
        setNotice('');
        try {
            const response = await request<{
                tabs: { retailer: Retailer; tabId: number }[];
            }>('open');
            if (
                new Set(response.tabs.map((tab) => tab.retailer)).size !==
                retailers.length
            )
                throw new Error(
                    'Les trois drives n’ont pas pu être ouverts. Utilisez les liens de chaque enseigne ci-dessous.',
                );
            setNotice(
                'Les trois drives sont ouverts. Dans chaque onglet, connectez-vous et choisissez votre magasin, puis revenez ici pour détecter les drives.',
            );
        } catch (caught) {
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'Ouverture interrompue. Utilisez les liens de chaque enseigne.',
            );
        } finally {
            setBusy('');
        }
    }
    async function detect() {
        setBusy('detect');
        setError('');
        setResults({});
        setSelections({});
        setTransfer(null);
        try {
            const response = await request<{ tabs: DriveTab[] }>('status');
            setTabs(response.tabs);
            const selected: Partial<Record<Retailer, number>> = {};
            for (const retailer of retailers) {
                const matches = response.tabs.filter(
                    (tab) => tab.retailer === retailer.id && tab.store,
                );
                if (matches.length === 1)
                    selected[retailer.id] = matches[0].tabId;
            }
            setActive(selected);
            if (!response.tabs.length)
                setError(
                    'Ouvrez vos drives, connectez-vous et choisissez un magasin, puis relancez la détection.',
                );
        } catch (caught) {
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'Détection interrompue.',
            );
        } finally {
            setBusy('');
        }
    }
    async function lookup(retailer: Retailer, item: Item) {
        const response = await request<SearchResult>('search', {
            retailer,
            tabId: active[retailer],
            query: queries[keyFor(retailer, item.id)]?.trim() || item.name,
        });
        const expected = tabs.find(
            (tab) =>
                tab.tabId === active[retailer] && tab.retailer === retailer,
        )?.store;
        if (!expected || response.store.id !== expected.id)
            throw new Error(
                'Le magasin a changé. Détectez à nouveau vos drives.',
            );
        return response;
    }
    async function autoFill() {
        const stores = retailers.filter((retailer) => active[retailer.id]);
        const needs = items.filter((item) => included.has(item.id));
        if (!stores.length || !needs.length) return;
        stopComparison.current = false;
        setBusy('auto');
        setError('');
        setNotice('');
        setTransfer(null);
        setProgress({ done: 0, total: stores.length * needs.length });
        let selectedCount = 0;
        try {
            // One stream per shop; the extension paces requests within each shop.
            await Promise.all(
                stores.map(async (retailer) => {
                    for (const item of needs) {
                        if (stopComparison.current) break;
                        const key = keyFor(retailer.id, item.id);
                        setSelections((previous) => {
                            const next = { ...previous };
                            delete next[key];
                            return next;
                        });
                        try {
                            const response = await lookup(retailer.id, item);
                            if (stopComparison.current) break;
                            setResults((previous) => ({
                                ...previous,
                                [key]: response,
                            }));
                            const selection = cheapestSelection(
                                item,
                                response.products,
                                Date.now(),
                            );
                            if (selection) {
                                selectedCount++;
                                setSelections((previous) => ({
                                    ...previous,
                                    [key]: selection,
                                }));
                            }
                        } catch (caught) {
                            if (stopComparison.current) break;
                            setResults((previous) => ({
                                ...previous,
                                [key]: {
                                    store: { id: '', name: '', url: '' },
                                    products: [],
                                    error:
                                        caught instanceof Error
                                            ? caught.message
                                            : 'Recherche interrompue.',
                                },
                            }));
                        }
                        setProgress((previous) => ({
                            ...previous,
                            done: previous.done + 1,
                        }));
                    }
                }),
            );
            if (client.current)
                setNotice(
                    stopComparison.current
                        ? 'Comparaison arrêtée. Les références déjà trouvées sont conservées.'
                        : `${selectedCount} ligne(s) préremplie(s). Vérifiez les produits, puis complétez les formats ou quantités restés à vérifier.`,
                );
        } finally {
            if (client.current) {
                setNow(Date.now());
                setBusy('');
            }
        }
    }
    async function search(retailer: Retailer, item: Item) {
        const key = keyFor(retailer, item.id);
        setBusy(key);
        setError('');
        setTransfer(null);
        setSelections((previous) => {
            const next = { ...previous };
            delete next[key];
            return next;
        });
        try {
            const response = await lookup(retailer, item);
            setNow(Date.now());
            setResults((previous) => ({ ...previous, [key]: response }));
        } catch (caught) {
            const message =
                caught instanceof Error
                    ? caught.message
                    : 'Recherche interrompue.';
            setResults((previous) => ({
                ...previous,
                [key]: {
                    store: { id: '', name: '', url: '' },
                    products: [],
                    error: message,
                },
            }));
        } finally {
            setBusy('');
        }
    }
    async function prepare(retailer: Retailer) {
        setBusy(`transfer:${retailer}`);
        setError('');
        try {
            const lines = items
                .filter((item) => included.has(item.id))
                .map((item) => selections[keyFor(retailer, item.id)])
                .filter(Boolean)
                .map((line) => ({
                    quoteId: line.product.quoteId,
                    quantity: Number(line.quantity),
                }));
            setTransfer(await request<Transfer>('prepare', { lines }));
        } catch (caught) {
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'Préparation interrompue.',
            );
        } finally {
            setBusy('');
        }
    }
    async function refreshTransfer() {
        if (!transfer) return;
        setBusy('job');
        setError('');
        try {
            setTransfer(await request<Transfer>('job', { id: transfer.id }));
        } catch (caught) {
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'État indisponible. Vérifiez le panier dans le drive.',
            );
        } finally {
            setBusy('');
        }
    }
    const selectedItems = items.filter((item) => included.has(item.id));
    const baskets = retailers.map((retailer) => {
        const picked = selectedItems
            .map((item) => selections[keyFor(retailer.id, item.id)])
            .filter((line) => validSelection(line, now));
        const total = picked.reduce(
            (sum, line) =>
                sum + line.product.priceCents * Number(line.quantity),
            0,
        );
        const complete =
            selectedItems.length > 0 &&
            selectedItems.every((item) =>
                coversNeed(item, selections[keyFor(retailer.id, item.id)], now),
            );
        const fuel = fuelCost(distances[retailer.id] ?? '', fuelPrice);
        return {
            retailer,
            picked,
            total,
            complete,
            fuel,
            combined: complete && fuel !== null ? total + fuel : null,
        };
    });
    const ranked = baskets
        .filter((basket) => basket.combined !== null)
        .sort((a, b) => a.combined! - b.combined!);
    const best = ranked[0];
    return (
        <div className="site-container menu-page drive-page">
            <Head title="Comparer mes drives">
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <Link href={`/menus/${plan.id}`} className="menu-back">
                <ArrowLeft size={15} /> Retour à mes menus
            </Link>
            <div className="menu-heading">
                <div>
                    <span className="menu-eyebrow">
                        LES BONS PRODUITS, LE BON PANIER
                    </span>
                    <h1>
                        Mes courses, <em>mon drive.</em>
                    </h1>
                    <p>
                        {plan.title} · Quantités pour {plan.people} personnes
                    </p>
                </div>
                <ShoppingBasket size={36} />
            </div>
            <section
                className="drive-setup"
                aria-labelledby="drive-setup-title"
            >
                <div>
                    <span className="menu-eyebrow">PREMIÈRE CONNEXION</span>
                    <h2 id="drive-setup-title">Relier mes drives</h2>
                    <p>
                        Ouvrez vos drives dans ce navigateur, connectez-vous et
                        choisissez vos magasins. L’extension utilise ces
                        sessions pour rechercher les produits et préparer vos
                        paniers.
                    </p>
                    <p className="drive-note">
                        Version expérimentale pour Chrome et Edge sur
                        ordinateur. Les trois connecteurs doivent encore être
                        validés sur de vrais comptes. Aucun paiement n’est
                        effectué ici.
                    </p>
                </div>
                <div className="drive-install">
                    <Button
                        onClick={() => void openDrives()}
                        disabled={Boolean(busy)}
                    >
                        <ArrowUpRight size={16} /> Ouvrir les 3 drives
                    </Button>
                    <a
                        href="/downloads/cuistot-drives.zip"
                        className="drive-download"
                    >
                        <Download size={17} /> Télécharger l’extension
                    </a>
                    <details>
                        <summary>Installation et connexion</summary>
                        <ol>
                            <li>Décompressez le fichier téléchargé.</li>
                            <li>
                                Ouvrez <code>chrome://extensions</code> ou{' '}
                                <code>edge://extensions</code>, activez le mode
                                développeur puis « Charger l’extension non
                                empaquetée ». Sélectionnez le dossier
                                décompressé contenant <code>manifest.json</code>
                                .
                            </li>
                            <li>
                                Sur cette page, cliquez sur l’icône de
                                l’extension puis « Relier le site de cet onglet
                                ».
                            </li>
                            <li>
                                Ouvrez vos drives puis cliquez sur « Détecter
                                mes drives » ci-dessous.
                            </li>
                        </ol>
                    </details>
                    <Button
                        onClick={() => void detect()}
                        disabled={Boolean(busy)}
                    >
                        <RefreshCw
                            size={16}
                            className={busy === 'detect' ? 'animate-spin' : ''}
                        />{' '}
                        Détecter mes drives
                    </Button>
                </div>
            </section>
            {error && (
                <p className="drive-alert" role="alert">
                    {error}
                </p>
            )}
            {notice && (
                <p className="drive-note" role="status">
                    {notice}
                </p>
            )}
            <section className="drive-stores" aria-label="Mes magasins">
                {retailers.map((retailer) => {
                    const matches = tabs.filter(
                        (tab) => tab.retailer === retailer.id,
                    );
                    const usable = matches.filter((tab) => tab.store);
                    return (
                        <article key={retailer.id} className="drive-store">
                            <h2>{retailer.name}</h2>
                            <a
                                href={retailer.url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Ouvrir mon drive <ArrowUpRight size={14} />
                            </a>
                            {usable.length > 0 ? (
                                <label>
                                    Magasin à comparer
                                    <select
                                        value={active[retailer.id] || ''}
                                        disabled={Boolean(busy)}
                                        onChange={(event) => {
                                            setActive((previous) => ({
                                                ...previous,
                                                [retailer.id]:
                                                    Number(
                                                        event.target.value,
                                                    ) || undefined,
                                            }));
                                            setResults({});
                                            setSelections({});
                                            setTransfer(null);
                                        }}
                                    >
                                        <option value="">
                                            Choisir l’onglet du magasin
                                        </option>
                                        {usable.map((tab) => (
                                            <option
                                                key={tab.tabId}
                                                value={tab.tabId}
                                            >
                                                {tab.store?.name} · onglet{' '}
                                                {tab.tabId}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            ) : (
                                <p className="drive-note">
                                    {matches[0]?.error ||
                                        'Ouvrez votre magasin puis détectez les drives.'}
                                </p>
                            )}
                        </article>
                    );
                })}
            </section>
            <section className="drive-list" aria-labelledby="drive-list-title">
                <div className="drive-setup">
                    <div>
                        <h2>Préremplir au moins cher</h2>
                        <p>
                            Recherche dans chaque magasin détecté, puis
                            sélection du produit compatible au coût le plus bas
                            pour couvrir la quantité demandée. Les formats
                            incertains restent à vérifier.
                        </p>
                    </div>
                    <div>
                        <Button
                            onClick={() => void autoFill()}
                            disabled={
                                Boolean(busy) ||
                                !selectedItems.length ||
                                !retailers.some(
                                    (retailer) => active[retailer.id],
                                )
                            }
                        >
                            Rechercher et préremplir
                        </Button>
                        {busy === 'auto' ? (
                            <div role="status">
                                <p>
                                    {progress.done}/{progress.total} recherches
                                    terminées
                                </p>
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        stopComparison.current = true;
                                    }}
                                >
                                    Arrêter la comparaison
                                </Button>
                            </div>
                        ) : null}
                    </div>
                </div>
                <div className="menu-weekbar">
                    <div>
                        <span className="menu-eyebrow">DU MENU AU PANIER</span>
                        <h2 id="drive-list-title">
                            Je choisis <em>mes produits.</em>
                        </h2>
                        <p>
                            Décochez ce que vous avez déjà. Choisissez chaque
                            référence et indiquez le nombre de paquets à acheter
                            pour couvrir la quantité demandée.
                        </p>
                    </div>
                </div>
                <p className="drive-note">
                    Les recherches proposent jusqu’à cinq références. Les
                    premiers résultats ne couvrent pas tout le catalogue :
                    affinez les mots-clés si nécessaire. Vérifiez les
                    ingrédients, les allergènes et les formats sur le drive.
                </p>
                {items.map((item) => (
                    <article key={item.id} className="drive-item">
                        <label className="drive-ingredient">
                            <input
                                type="checkbox"
                                checked={included.has(item.id)}
                                disabled={Boolean(busy)}
                                onChange={() => {
                                    setIncluded((previous) => {
                                        const next = new Set(previous);
                                        if (next.has(item.id))
                                            next.delete(item.id);
                                        else next.add(item.id);
                                        return next;
                                    });
                                    setTransfer(null);
                                }}
                            />
                            <strong>{item.name}</strong>
                            <span>
                                {item.quantity === null
                                    ? 'Quantité à vérifier'
                                    : `${item.quantity.toLocaleString('fr-FR')} ${item.unit}`}
                            </span>
                        </label>
                        {included.has(item.id) && (
                            <div className="drive-options">
                                {retailers.map((retailer) => {
                                    const key = keyFor(retailer.id, item.id);
                                    const result = results[key];
                                    const selection = selections[key];
                                    const amount = selection
                                        ? packAmount(
                                              selection.product,
                                              item.unit,
                                          )
                                        : null;
                                    const underfilled =
                                        item.quantity !== null &&
                                        amount !== null &&
                                        Number(selection?.quantity) > 0 &&
                                        amount * Number(selection?.quantity) <
                                            item.quantity;
                                    const label = `${item.name} — ${retailer.name}`;
                                    return (
                                        <div
                                            key={retailer.id}
                                            className="drive-option"
                                        >
                                            <h3>{retailer.name}</h3>
                                            <div className="drive-search">
                                                <input
                                                    aria-label={`Recherche ${label}`}
                                                    value={
                                                        queries[key] ??
                                                        item.name
                                                    }
                                                    maxLength={120}
                                                    disabled={Boolean(busy)}
                                                    onChange={(event) =>
                                                        setQueries(
                                                            (previous) => ({
                                                                ...previous,
                                                                [key]: event
                                                                    .target
                                                                    .value,
                                                            }),
                                                        )
                                                    }
                                                />
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={
                                                        !active[retailer.id] ||
                                                        Boolean(busy)
                                                    }
                                                    onClick={() =>
                                                        void search(
                                                            retailer.id,
                                                            item,
                                                        )
                                                    }
                                                >
                                                    {busy === key ? (
                                                        <LoaderCircle
                                                            size={16}
                                                            className="animate-spin"
                                                        />
                                                    ) : (
                                                        'Chercher'
                                                    )}
                                                </Button>
                                            </div>
                                            {result?.error && (
                                                <p
                                                    role="alert"
                                                    className="drive-note"
                                                >
                                                    {result.error}
                                                </p>
                                            )}
                                            {result &&
                                                !result.error &&
                                                (result.products.length ? (
                                                    <>
                                                        <label>
                                                            Référence
                                                            <select
                                                                aria-label={`Produit ${label}`}
                                                                value={
                                                                    selection
                                                                        ?.product
                                                                        .quoteId ||
                                                                    ''
                                                                }
                                                                disabled={Boolean(
                                                                    busy,
                                                                )}
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    const product =
                                                                        result.products.find(
                                                                            (
                                                                                entry,
                                                                            ) =>
                                                                                entry.quoteId ===
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        );
                                                                    setSelections(
                                                                        (
                                                                            previous,
                                                                        ) => {
                                                                            const next =
                                                                                {
                                                                                    ...previous,
                                                                                };
                                                                            if (
                                                                                product
                                                                            )
                                                                                next[
                                                                                    key
                                                                                ] =
                                                                                    {
                                                                                        product,
                                                                                        quantity:
                                                                                            '',
                                                                                    };
                                                                            else
                                                                                delete next[
                                                                                    key
                                                                                ];
                                                                            return next;
                                                                        },
                                                                    );
                                                                    setTransfer(
                                                                        null,
                                                                    );
                                                                }}
                                                            >
                                                                <option value="">
                                                                    Choisir un
                                                                    produit
                                                                </option>
                                                                {result.products.map(
                                                                    (
                                                                        product,
                                                                    ) => (
                                                                        <option
                                                                            key={
                                                                                product.quoteId
                                                                            }
                                                                            value={
                                                                                product.quoteId
                                                                            }
                                                                        >
                                                                            {
                                                                                product.name
                                                                            }{' '}
                                                                            ·{' '}
                                                                            {product.packaging ||
                                                                                'format à vérifier'}{' '}
                                                                            ·{' '}
                                                                            {euros(
                                                                                product.priceCents,
                                                                            )}
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                        </label>
                                                        {selection && (
                                                            <div className="drive-product">
                                                                <p>
                                                                    {
                                                                        selection
                                                                            .product
                                                                            .name
                                                                    }
                                                                </p>
                                                                <span>
                                                                    {selection
                                                                        .product
                                                                        .packaging ||
                                                                        'Vérifiez le format sur le drive'}{' '}
                                                                    ·{' '}
                                                                    {euros(
                                                                        selection
                                                                            .product
                                                                            .priceCents,
                                                                    )}
                                                                    {selection
                                                                        .product
                                                                        .unitPrice
                                                                        ? ` · ${selection.product.unitPrice}`
                                                                        : ''}
                                                                </span>
                                                                <label>
                                                                    Nombre de
                                                                    paquets
                                                                    <input
                                                                        type="number"
                                                                        min="1"
                                                                        max="99"
                                                                        step="1"
                                                                        inputMode="numeric"
                                                                        aria-label={`Nombre de paquets ${label}`}
                                                                        disabled={Boolean(
                                                                            busy,
                                                                        )}
                                                                        value={
                                                                            selection.quantity
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) => {
                                                                            const quantity =
                                                                                event
                                                                                    .target
                                                                                    .value;
                                                                            setSelections(
                                                                                (
                                                                                    previous,
                                                                                ) => ({
                                                                                    ...previous,
                                                                                    [key]: {
                                                                                        ...previous[
                                                                                            key
                                                                                        ],
                                                                                        quantity,
                                                                                        confirmed: false,
                                                                                    },
                                                                                }),
                                                                            );
                                                                            setTransfer(
                                                                                null,
                                                                            );
                                                                        }}
                                                                    />
                                                                </label>
                                                                {underfilled ? (
                                                                    <p className="drive-note">
                                                                        Quantité
                                                                        insuffisante
                                                                        :
                                                                        augmentez
                                                                        le
                                                                        nombre
                                                                        de
                                                                        paquets.
                                                                    </p>
                                                                ) : !coversNeed(
                                                                      item,
                                                                      selection,
                                                                      now,
                                                                  ) ||
                                                                  selection.confirmed ? (
                                                                    <label className="mt-2 flex items-start gap-2">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={Boolean(
                                                                                selection.confirmed,
                                                                            )}
                                                                            disabled={
                                                                                !validSelection(
                                                                                    selection,
                                                                                    now,
                                                                                ) ||
                                                                                Boolean(
                                                                                    busy,
                                                                                )
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) => {
                                                                                const confirmed =
                                                                                    event
                                                                                        .target
                                                                                        .checked;
                                                                                setSelections(
                                                                                    (
                                                                                        previous,
                                                                                    ) => ({
                                                                                        ...previous,
                                                                                        [key]: {
                                                                                            ...previous[
                                                                                                key
                                                                                            ],
                                                                                            confirmed,
                                                                                        },
                                                                                    }),
                                                                                );
                                                                                setTransfer(
                                                                                    null,
                                                                                );
                                                                            }}
                                                                        />{' '}
                                                                        Produit,
                                                                        format
                                                                        et
                                                                        quantité
                                                                        vérifiés
                                                                        pour cet
                                                                        ingrédient
                                                                    </label>
                                                                ) : null}
                                                            </div>
                                                        )}
                                                        <p className="drive-note">
                                                            {result.store.name}{' '}
                                                            · Relevé à{' '}
                                                            {new Date(
                                                                result
                                                                    .products[0]
                                                                    .createdAt,
                                                            ).toLocaleTimeString(
                                                                'fr-FR',
                                                                {
                                                                    hour: '2-digit',
                                                                    minute: '2-digit',
                                                                },
                                                            )}
                                                        </p>
                                                    </>
                                                ) : (
                                                    <p className="drive-note">
                                                        Aucune référence
                                                        disponible trouvée.
                                                        Affinez la recherche ou
                                                        vérifiez le drive.
                                                    </p>
                                                ))}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </article>
                ))}
            </section>
            <section className="drive-setup" aria-label="Calculer le trajet">
                <div>
                    <h2>Courses + essence</h2>
                    <p>
                        Consommation : 6 L/100 km. Indiquez la distance totale
                        aller-retour de chaque magasin. Les distances et tarifs
                        restent dans cette page.
                    </p>
                </div>
                <label>
                    Carburant (€/L)
                    <input
                        className="mt-2 block rounded border p-2"
                        type="number"
                        min="0.01"
                        max="20"
                        step="0.001"
                        value={fuelPrice}
                        onChange={(event) => setFuelPrice(event.target.value)}
                        placeholder="Prix au litre"
                    />
                </label>
            </section>
            {best ? (
                <p className="drive-alert" role="status">
                    {ranked.length === 1
                        ? 'Un seul panier complet comparable : '
                        : 'Le moins cher parmi les paniers complets renseignés : '}
                    {best.retailer.name} · {euros(best.combined!)} courses et
                    essence comprises.
                </p>
            ) : (
                <p className="drive-note">
                    Complétez un panier, son trajet et le prix du carburant pour
                    calculer le coût total. Les paniers incomplets ne sont pas
                    classés.
                </p>
            )}
            <section
                className="drive-stores drive-totals"
                aria-label="Comparer mes paniers"
            >
                {baskets.map(
                    ({ retailer, picked, total, complete, fuel, combined }) => {
                        return (
                            <article className="drive-store" key={retailer.id}>
                                <h2>{retailer.name}</h2>
                                <strong className="drive-total">
                                    {picked.length ? euros(total) : '—'}
                                </strong>
                                <p>Produits sélectionnés</p>
                                <label>
                                    Distance aller-retour (km)
                                    <input
                                        className="mt-2 block w-full rounded border p-2"
                                        type="number"
                                        min="0"
                                        max="20000"
                                        step="0.1"
                                        value={distances[retailer.id] ?? ''}
                                        onChange={(event) =>
                                            setDistances((previous) => ({
                                                ...previous,
                                                [retailer.id]:
                                                    event.target.value,
                                            }))
                                        }
                                    />
                                </label>
                                <p>
                                    Essence :{' '}
                                    {fuel === null
                                        ? 'À renseigner'
                                        : euros(fuel)}
                                </p>
                                <p>
                                    <strong>
                                        Total courses + essence :{' '}
                                        {combined === null
                                            ? 'À compléter'
                                            : euros(combined)}
                                    </strong>
                                </p>
                                <p>
                                    {picked.length}/{selectedItems.length}{' '}
                                    ingrédient(s) renseigné(s)
                                </p>
                                <p className="drive-note">
                                    {complete
                                        ? 'Toutes les lignes sont renseignées. Vérifiez que les formats couvrent les besoins du menu.'
                                        : 'Sous-total partiel. Complétez les produits, les quantités et les formats à vérifier pour comparer les paniers.'}
                                </p>
                                <Button
                                    disabled={!complete || Boolean(busy)}
                                    onClick={() => void prepare(retailer.id)}
                                >
                                    <Check size={16} /> Préparer le transfert
                                </Button>
                            </article>
                        );
                    },
                )}
            </section>
            <p className="drive-note">
                Montants des produits sélectionnés, hors frais, consignes et
                avantages fidélité. Les promotions par lots ne sont pas
                recalculées ici. Prix valables pour la préparation pendant dix
                minutes, revérifiés au transfert. La sélection automatique porte
                sur les résultats trouvés, pas sur tout le catalogue. Vérifiez
                les qualités comparables et les lignes laissées à vérifier. Le
                trajet est une estimation à partir de vos valeurs, hors péages.
            </p>
            {transfer && (
                <div className="drive-transfer" role="status">
                    <p>
                        {transfer.message ||
                            'Consultez l’onglet de l’extension pour vérifier le transfert.'}
                    </p>
                    <Button
                        variant="outline"
                        disabled={Boolean(busy)}
                        onClick={() => void refreshTransfer()}
                    >
                        Actualiser le suivi
                    </Button>
                </div>
            )}
        </div>
    );
}
