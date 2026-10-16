import type { DriveProduct } from './drives';

export type ShoppingNeed = {
    id: string;
    name: string;
    quantity: number | null;
    unit: string;
};
export type ProductSelection = {
    product: DriveProduct;
    quantity: string;
    confirmed?: boolean;
};
const TTL = 10 * 60 * 1000;
const clean = (text: string) =>
    text
        .toLowerCase()
        .replaceAll('œ', 'oe')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replaceAll('×', 'x');
const words = (text: string) =>
    clean(text)
        .split(/[^a-z0-9]+/)
        .filter(Boolean)
        .map((word) =>
            ['pois', 'riz', 'mais', 'noix', 'ananas'].includes(word)
                ? word
                : word.replace(/s$/, ''),
        );
const unitInfo = (unit: string): [string, number] | null => {
    switch (clean(unit).trim()) {
        case 'g':
            return ['g', 1];
        case 'kg':
            return ['g', 1000];
        case 'ml':
            return ['ml', 1];
        case 'cl':
            return ['ml', 10];
        case 'l':
            return ['ml', 1000];
        case 'piece':
        case 'pieces':
            return ['piece', 1];
        default:
            return null;
    }
};

export function compatibleProduct(
    need: ShoppingNeed,
    product: DriveProduct,
): boolean {
    const requested = words(need.name);
    const offered = words(product.name);
    const ignored = [
        'de',
        'du',
        'des',
        'd',
        'le',
        'la',
        'les',
        'et',
        'a',
        'au',
        'aux',
        'pour',
        'egoutte',
        'egouttee',
        'feuille',
        'frais',
        'fraiche',
    ];
    const required = requested.filter(
        (word) =>
            !ignored.includes(word) &&
            !['cuit', 'cuite', 'sec', 'seche'].includes(word),
    );
    if (!required.length || !required.every((word) => offered.includes(word)))
        return false;
    // Reject a different food form rather than treating a cheap derivative as the ingredient.
    const forms = [
        'sauce',
        'boisson',
        'lait',
        'farine',
        'huile',
        'puree',
        'soupe',
        'confiture',
        'jus',
        'chips',
        'biscuit',
        'concentre',
        'concentree',
        'confit',
        'pane',
        'nugget',
        'seche',
        'sec',
        'pele',
        'pelee',
        'concasse',
        'concasee',
    ];
    if (
        forms.some(
            (word) => offered.includes(word) && !requested.includes(word),
        )
    )
        return false;
    const cooked = requested.some((word) => ['cuit', 'cuite'].includes(word));
    if (
        cooked &&
        !/\b(cuits?|cuites?|conserve|bocal)\b/.test(
            clean(product.name + ' ' + product.packaging),
        )
    )
        return false;
    if (
        requested.some((word) => ['sec', 'seche'].includes(word)) &&
        /\b(cuits?|cuites?|conserve|bocal)\b/.test(
            clean(product.name + ' ' + product.packaging),
        )
    )
        return false;
    return true;
}

export function packAmount(
    product: Pick<DriveProduct, 'name' | 'packaging'>,
    unit: string,
): number | null {
    const info = unitInfo(unit);
    if (!info) return null;
    const label = clean(product.packaging || product.name);
    if (/\+|offert|poids variable/.test(label)) return null;
    if (info[0] === 'piece') {
        const multiCount = label.match(
            /\b(\d+)\s*x\s*(\d+)\s*(?:oeufs?|pieces?|unites?)\b/,
        );
        if (multiCount)
            return Number(multiCount[1]) * Number(multiCount[2]) || null;
        const count =
            label.match(/\b(\d+)\s*(?:oeufs?|pieces?|unites?)\b/) ||
            label.match(/\b(?:boite|lot|paquet|filet)\s+(?:de\s+)?(\d+)\b/);
        if (count) return Number(count[1]) > 0 ? Number(count[1]) : null;
        return /\b(?:la piece|a la piece)\b/.test(label) ? 1 : null;
    }
    const multi = label.match(
        /\b(\d+)\s*x\s*(\d+(?:[.,]\d+)?)\s*(kg|g|ml|cl|l)\b/,
    );
    const single = label.match(/\b(\d+(?:[.,]\d+)?)\s*(kg|g|ml|cl|l)\b/);
    if (!multi && !single) return null;
    const sizes = [
        ...label.matchAll(/\b(\d+(?:[.,]\d+)?)\s*(kg|g|ml|cl|l)\b/g),
    ];
    if (!multi && sizes.length !== 1) return null;
    if (multi && sizes.length > 1) return null;
    const amount = multi
        ? Number(multi[1]) * Number(multi[2].replace(',', '.'))
        : Number(single![1].replace(',', '.'));
    const packageUnit = unitInfo(multi ? multi[3] : single![2]);
    if (!packageUnit || packageUnit[0] !== info[0] || !(amount > 0))
        return null;
    return (amount * packageUnit[1]) / info[1];
}

export function validSelection(
    line: ProductSelection | undefined,
    now: number,
): line is ProductSelection {
    return Boolean(
        line &&
        line.product.quoteId &&
        Number.isSafeInteger(line.product.priceCents) &&
        line.product.priceCents > 0 &&
        Number.isSafeInteger(line.product.priceCents * Number(line.quantity)) &&
        Number.isInteger(Number(line.quantity)) &&
        Number(line.quantity) >= 1 &&
        Number(line.quantity) <= 99 &&
        Number.isFinite(line.product.createdAt) &&
        now >= line.product.createdAt &&
        now - line.product.createdAt < TTL,
    );
}

export function cheapestSelection(
    need: ShoppingNeed,
    products: DriveProduct[],
    now: number,
): ProductSelection | null {
    if (
        need.quantity === null ||
        !Number.isFinite(need.quantity) ||
        need.quantity <= 0
    )
        return null;
    const options = products
        .flatMap((product) => {
            if (!compatibleProduct(need, product)) return [];
            const amount = packAmount(product, need.unit);
            if (amount === null) return [];
            const line = {
                product,
                quantity: String(Math.ceil(need.quantity! / amount)),
            };
            return validSelection(line, now)
                ? [
                      {
                          line,
                          total: product.priceCents * Number(line.quantity),
                          surplus:
                              amount * Number(line.quantity) - need.quantity!,
                      },
                  ]
                : [];
        })
        .sort(
            (a, b) =>
                a.total - b.total ||
                a.surplus - b.surplus ||
                a.line.product.id.localeCompare(b.line.product.id),
        );
    return options[0]?.line ?? null;
}

export function coversNeed(
    need: ShoppingNeed,
    line: ProductSelection | undefined,
    now: number,
): boolean {
    if (!validSelection(line, now)) return false;
    const amount = packAmount(line.product, need.unit);
    if (need.quantity !== null && need.quantity > 0 && amount !== null) {
        return (
            amount * Number(line.quantity) >= need.quantity &&
            (compatibleProduct(need, line.product) || line.confirmed === true)
        );
    }
    return line.confirmed === true;
}

export function fuelCost(
    roundTripKm: string,
    pricePerLitre: string,
): number | null {
    if (!roundTripKm.trim() || !pricePerLitre.trim()) return null;
    const distance = Number(roundTripKm.replace(',', '.'));
    const price = Number(pricePerLitre.replace(',', '.'));
    if (
        !Number.isFinite(distance) ||
        distance < 0 ||
        distance > 20000 ||
        !Number.isFinite(price) ||
        price <= 0 ||
        price > 20
    )
        return null;
    return Math.round(((distance * 6) / 100) * price * 100);
}
