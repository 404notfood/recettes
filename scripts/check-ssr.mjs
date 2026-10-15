const user = {
    id: 1,
    name: 'Vérification SSR',
    email: 'ssr@example.test',
    is_admin: false,
};
const plan = {
    id: 1,
    title: 'Vérification SSR',
    start_date: '2026-10-19',
    days: 7,
    people: 4,
    status: 'ready',
    criteria: { vegetarian: false },
    meals: [],
    error_message: null,
};
const recipe = {
    id: 1,
    title: 'Vérification des recettes',
    description: 'Une fiche de vérification du rendu.',
    servings: 4,
    total_minutes: 20,
    ingredients: [],
    steps: ['Préparer les ingrédients.'],
};
const fixtures = {
    'menus/index': {
        plans: [],
        aiConfigured: false,
        catalogueCount: 0,
        today: '2026-10-19',
    },
    'menus/show': { plan, shoppingCategories: [] },
    'menus/recipe': { plan, recipe },
};

for (const [component, props] of Object.entries(fixtures)) {
    const page = {
        component,
        url: '/menus/1',
        version: null,
        props: {
            auth: { user },
            siteUrl: 'http://localhost',
            errors: {},
            ...props,
        },
    };
    const response = await fetch('http://127.0.0.1:13714/render', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(page),
        signal: AbortSignal.timeout(10000),
    });
    const rendered = await response.json();
    if (
        !response.ok ||
        typeof rendered.body !== 'string' ||
        !rendered.body.includes('menu-page')
    ) {
        throw new Error(
            `Le SSR actif ne rend pas ${component}. Recompiler avec npm run build et redémarrer le processus SSR. ${rendered.error ?? ''}`,
        );
    }
    console.log(`SSR actif : ${component} OK`);
}
