import { appOrigin } from './policy.js';
const status = document.querySelector('#status');
document.querySelector('#open').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    status.textContent = 'Ouverture des trois drives…';
    try {
        const response = await chrome.runtime.sendMessage({
            operation: 'open',
        });
        if (response.error) throw new Error(response.error);
        if (response.value.tabs.length !== 3)
            throw new Error(
                'Certains onglets n’ont pas pu être ouverts. Réessayez ou ouvrez les liens du site un par un.',
            );
        status.textContent =
            'Les trois drives sont ouverts. Choisissez vos magasins dans leurs onglets.';
    } catch (error) {
        status.textContent = error.message;
    } finally {
        button.disabled = false;
    }
});
const saved = await chrome.storage.local.get(['appOrigin', 'appMatch']);
const [activeTab] = await chrome.tabs.query({
    active: true,
    currentWindow: true,
});
status.textContent = saved.appOrigin
    ? `Site relié : ${saved.appOrigin}`
    : 'Aucun site relié.';
document.querySelector('#pair').addEventListener('click', async () => {
    try {
        const tab = activeTab;
        const origin = appOrigin(tab.url);
        const parsed = new URL(origin);
        const match = `${parsed.protocol}//${parsed.hostname}/*`;
        if (!(await chrome.permissions.request({ origins: [match] })))
            throw new Error('Autorisation du site refusée.');
        await chrome.scripting.unregisterContentScripts();
        await chrome.scripting.registerContentScripts([
            {
                id: 'cuistot-site',
                matches: [match],
                js: ['bridge.js'],
                runAt: 'document_idle',
            },
        ]);
        await chrome.storage.local.set({ appOrigin: origin, appMatch: match });
        await chrome.storage.session.clear();
        if (saved.appMatch && saved.appMatch !== match)
            await chrome.permissions.remove({ origins: [saved.appMatch] });
        // Reload removes bridges left behind by an older extension context.
        await chrome.tabs.reload(tab.id);
        status.textContent = `Site relié : ${origin}. La page a été rechargée, revenez à vos menus.`;
    } catch (error) {
        status.textContent = error.message;
    }
});
document.querySelector('#disconnect').addEventListener('click', async () => {
    const state = await chrome.storage.local.get('appMatch');
    await chrome.scripting.unregisterContentScripts();
    await chrome.storage.local.clear();
    await chrome.storage.session.clear();
    if (state.appMatch)
        await chrome.permissions.remove({ origins: [state.appMatch] });
    status.textContent = 'Site déconnecté.';
});
