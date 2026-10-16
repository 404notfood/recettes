import { readFileSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const clientRoot = resolve(root, 'public/build');
const ssrRoot = resolve(root, 'bootstrap/ssr');
const client = JSON.parse(
    readFileSync(resolve(clientRoot, 'manifest.json'), 'utf8'),
);
const ssr = JSON.parse(
    readFileSync(resolve(ssrRoot, 'ssr-manifest.json'), 'utf8'),
);
const entry = readFileSync(resolve(ssrRoot, 'app.js'), 'utf8');

for (const name of [
    'welcome',
    'menus/index',
    'menus/show',
    'menus/recipe',
    'menus/drives',
    'recipes/index',
]) {
    const source = `resources/js/pages/${name}.tsx`;
    if (
        !client[source] ||
        !ssr[source] ||
        !entry.includes(`./pages/${name}.tsx`)
    ) {
        throw new Error(
            `Build incomplet : ${name} manque dans le navigateur ou le SSR. Relancer npm run build.`,
        );
    }
    if (!existsSync(resolve(clientRoot, client[source].file))) {
        throw new Error(`Asset navigateur manquant : ${client[source].file}`);
    }
    for (const asset of ssr[source]) {
        const relative = asset.replace(/^\/?(?:build\/|bootstrap\/ssr\/)?/, '');
        if (!existsSync(resolve(ssrRoot, relative))) {
            throw new Error(`Asset SSR manquant : ${asset}`);
        }
    }
}
console.log(
    'Build navigateur et SSR vérifié : accueil, menus et drives présents.',
);
