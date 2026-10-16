export type Retailer = 'leclerc' | 'carrefour' | 'intermarche';
export const retailers: { id: Retailer; name: string; url: string }[] = [
    { id: 'leclerc', name: 'E.Leclerc', url: 'https://www.leclercdrive.fr/' },
    { id: 'carrefour', name: 'Carrefour', url: 'https://www.carrefour.fr/' },
    {
        id: 'intermarche',
        name: 'Intermarché',
        url: 'https://www.intermarche.com/',
    },
];
export type DriveStore = { id: string; name: string; url: string };
export type DriveTab = {
    tabId: number;
    retailer: Retailer;
    store?: DriveStore;
    error?: string;
};
export type DriveProduct = {
    id: string;
    quoteId: string;
    name: string;
    packaging: string;
    priceCents: number;
    unitPrice: string;
    createdAt: number;
};
export type SearchResult = { store: DriveStore; products: DriveProduct[] };
export type Transfer = {
    id: string;
    status: string;
    message: string;
    added?: number;
};
export const euros = (cents: number) =>
    (cents / 100).toLocaleString('fr-FR', {
        style: 'currency',
        currency: 'EUR',
    });

// A single listener for this page; no retailer session or credential reaches Laravel.
export function createDriveClient() {
    const pending = new Map<
        string,
        {
            resolve: (value: unknown) => void;
            reject: (reason: Error) => void;
            timer: ReturnType<typeof setTimeout>;
        }
    >();
    const receive = (event: MessageEvent) => {
        if (
            event.source !== window ||
            event.origin !== window.location.origin ||
            event.data?.channel !== 'cuistot-drives:response'
        )
            return;
        const request = pending.get(event.data.id);
        if (!request) return;
        clearTimeout(request.timer);
        pending.delete(event.data.id);
        if (event.data.error)
            request.reject(new Error(String(event.data.error)));
        else request.resolve(event.data.value);
    };
    window.addEventListener('message', receive);
    return {
        request<T>(
            operation: 'status' | 'open' | 'search' | 'prepare' | 'job',
            payload: unknown = {},
        ): Promise<T> {
            return new Promise((resolve, reject) => {
                const id = Array.from(
                    window.crypto.getRandomValues(new Uint32Array(4)),
                    (value) => value.toString(16),
                ).join('-');
                const timer = setTimeout(
                    () => {
                        pending.delete(id);
                        reject(
                            new Error(
                                operation === 'status'
                                    ? 'Extension non détectée. Installez-la, reliez ce site puis rechargez la page.'
                                    : 'Réponse non reçue. Vérifiez les onglets du drive et de l’extension.',
                            ),
                        );
                    },
                    operation === 'status' ? 30000 : 90000,
                );
                pending.set(id, {
                    resolve: (value) => resolve(value as T),
                    reject,
                    timer,
                });
                window.postMessage(
                    {
                        channel: 'cuistot-drives:request',
                        id,
                        operation,
                        payload,
                    },
                    window.location.origin,
                );
            });
        },
        dispose() {
            window.removeEventListener('message', receive);
            for (const request of pending.values()) {
                clearTimeout(request.timer);
                request.reject(new Error('Page fermée.'));
            }
            pending.clear();
        },
    };
}
