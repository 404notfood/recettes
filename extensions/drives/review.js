const id = new URL(location.href).searchParams.get('job');
const status = document.querySelector('#status');
const confirm = document.querySelector('#confirm');
const euro = (cents) =>
    (cents / 100).toLocaleString('fr-FR', {
        style: 'currency',
        currency: 'EUR',
    });
async function render() {
    const response = await chrome.runtime.sendMessage({
        operation: 'review',
        payload: { id },
    });
    if (response.error) {
        status.textContent = response.error;
        return;
    }
    const job = response.value;
    document.querySelector('#store').textContent =
        `${job.retailerLabel} · ${job.store.name}`;
    const body = document.querySelector('#lines');
    body.replaceChildren();
    for (const line of job.lines) {
        const row = document.createElement('tr');
        for (const value of [
            line.name,
            line.quantity,
            euro(line.priceCents * line.quantity),
        ]) {
            const cell = document.createElement('td');
            cell.textContent = value;
            row.append(cell);
        }
        body.append(row);
    }
    document.querySelector('#retailer').href = job.store.url;
    confirm.disabled =
        job.status !== 'pending' || Date.now() - job.createdAt >= 600000;
    document.querySelector('#cancel').disabled = job.status !== 'pending';
    status.textContent =
        job.message ||
        `Total des produits sélectionnés : ${euro(job.lines.reduce((sum, line) => sum + line.quantity * line.priceCents, 0))}`;
}
confirm.addEventListener('click', async () => {
    confirm.disabled = true;
    status.textContent =
        'Transfert en cours. Gardez les onglets des drives ouverts…';
    const response = await chrome.runtime.sendMessage({
        operation: 'run',
        payload: { id },
    });
    if (response.error) status.textContent = response.error;
    await render();
});
document.querySelector('#cancel').addEventListener('click', async () => {
    await chrome.runtime.sendMessage({ operation: 'cancel', payload: { id } });
    await render();
});
await render();
