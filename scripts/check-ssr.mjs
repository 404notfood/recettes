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
    welcome: {
        auth: { user: null },
        recipes: [],
        categories: [],
        recipeCount: 0,
    },
    'menus/index': {
        plans: [],
        aiConfigured: false,
        catalogueCount: 0,
        today: '2026-10-19',
        occasions: {
            everyday: 'Repas du quotidien',
            christmas: 'Noël',
            child_birthday: 'Anniversaire enfant',
        },
    },
    'menus/show': {
        plan: {
            ...plan,
            criteria: { ...plan.criteria, courses: ['main'] },
            meals: [
                {
                    id: 1,
                    date: '2026-10-19',
                    meal_type: 'lunch',
                    quick: false,
                    locked: false,
                    total_minutes: 20,
                    starter_recipe_id: null,
                    main_recipe_id: 1,
                    dessert_recipe_id: null,
                    snapshot: { main: recipe },
                    recipe_links: { main: '/recettes/verification' },
                },
            ],
        },
        shoppingCategories: [],
    },
    'menus/recipe': { plan, recipe },
    'menus/drives': {
        plan,
        items: [
            { id: 'carotte', name: 'Carottes', quantity: 600, unit: 'g' },
            { id: 'riz', name: 'Riz', quantity: 300, unit: 'g' },
        ],
    },
    'recipes/index': {
        menuLibraryAvailable: true,
        filters: { source: 'menus' },
        categories: [],
        recipes: {
            total: 1,
            current_page: 1,
            last_page: 1,
            links: [],
            data: [
                {
                    ...recipe,
                    slug: 'verification-privee',
                    author_name: user.name,
                    image_url: '/images/recipe-placeholder.svg',
                    total_time: 20,
                    difficulty: 'facile',
                    category: { id: 1, name: 'Plats', slug: 'plats' },
                    tags: [],
                    rating: null,
                    private_preview: true,
                },
            ],
        },
    },
};

for (const [component, props] of Object.entries(fixtures)) {
    const page = {
        component,
        url: component === 'welcome' ? '/' : '/menus/1',
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
    const marker =
        {
            welcome: 'home-hero',
            'menus/show': 'Imprimer menus et courses',
            'menus/drives': 'Nécessite l’extension 0.1.2.',
            'recipes/index': 'Privée · en validation',
        }[component] ?? 'menu-page';
    if (
        !response.ok ||
        typeof rendered.body !== 'string' ||
        !rendered.body.includes(marker)
    ) {
        throw new Error(
            `Le SSR actif ne rend pas ${component}. Recompiler avec npm run build et redémarrer le processus SSR. ${rendered.error ?? ''}`,
        );
    }
    console.log(`SSR actif : ${component} OK`);
}

for (const [occasion, label, marker] of [
    ['christmas', 'Noël', 'Votre menu · Noël'],
    ['everyday', 'Repas du quotidien', 'Un jour, de bonnes idées.'],
]) {
    const response = await fetch('http://127.0.0.1:13714/render', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            component: 'menus/show',
            url: '/menus/1',
            version: null,
            props: {
                auth: { user },
                siteUrl: 'http://localhost',
                errors: {},
                ...fixtures['menus/show'],
                plan: {
                    ...fixtures['menus/show'].plan,
                    days: 1,
                    criteria: { courses: ['main'], occasion },
                },
                occasionLabel: label,
            },
        }),
        signal: AbortSignal.timeout(10000),
    });
    const rendered = await response.json();
    if (
        !response.ok ||
        typeof rendered.body !== 'string' ||
        !rendered.body.includes(marker) ||
        rendered.body.includes('Sept jours, de bonnes idées.')
    ) {
        throw new Error(`Titre du menu d’un jour incorrect pour ${occasion}.`);
    }
    console.log(`SSR actif : menu d’un jour ${occasion} OK`);
}
