export type Category = {
    id: number;
    name: string;
    slug: string;
    icon: string;
    description?: string;
    recipes_count?: number;
};
export type Ingredient = {
    quantity: number | null;
    unit: string;
    name: string;
};
export type Recipe = {
    id: number;
    slug: string;
    title: string;
    description: string;
    author_name: string;
    image_url: string;
    image_credit?: {
        image: string;
        title: string;
        author: string;
        source: string;
        license: string;
        license_url: string;
    };
    prep_time: number;
    cook_time: number;
    total_time: number;
    servings: number;
    difficulty: string;
    category: Category;
    tags: string[];
    featured: boolean;
    rating: number | null;
    reviews_count: number;
    ingredients?: Ingredient[];
    steps?: string[];
    published_at?: string | null;
    status?: string;
    has_revision?: boolean;
    moderation_note?: string | null;
    guest_email?: string | null;
    private_preview?: boolean;
    is_ai_generated?: boolean;
};
export type Pagination<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
};
export const tagLabels: Record<string, string> = {
    vegetarien: 'Végétarien',
    vegan: 'Vegan',
    'sans-gluten': 'Sans gluten',
    rapide: 'Rapide',
    economique: 'Petit budget',
    'de-saison': 'De saison',
};
export const statusLabels: Record<string, string> = {
    draft: 'Brouillon',
    pending: 'En validation',
    published: 'Publiée',
    changes_requested: 'À compléter',
};
