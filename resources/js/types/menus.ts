export type Course = 'starter' | 'main' | 'dessert';
export type MealType = 'lunch' | 'dinner';
export const courseLabels: Record<Course, string> = {
    starter: 'Entrée',
    main: 'Plat',
    dessert: 'Dessert',
};
export const mealLabels: Record<MealType, string> = {
    lunch: 'Midi',
    dinner: 'Soir',
};
export const difficultyLabels: Record<number, string> = {
    1: 'Facile',
    2: 'Intermédiaire',
    3: 'Avancée',
};
export const statusLabels: Record<string, string> = {
    queued: 'En attente',
    generating: 'En préparation',
    ready: 'Prêt',
    failed: 'À relancer',
};
export interface MenuRecipe {
    id: number | null;
    title: string;
    description: string;
    slug?: string;
    steps?: string[];
    servings: number;
    total_minutes: number;
    difficulty: number;
    is_ai_generated: boolean;
    planning_estimated: boolean;
    food_groups?: string[];
    protein_source?: string | null;
    dessert_profile?: string | null;
    ingredients: {
        name: string;
        quantity: number | string | null;
        unit: string | null;
    }[];
}
export interface PlannedMeal {
    id: number;
    date: string;
    meal_type: MealType;
    quick: boolean;
    locked: boolean;
    total_minutes: number;
    starter_recipe_id: number | null;
    main_recipe_id: number | null;
    dessert_recipe_id: number | null;
    snapshot: Record<Course, MenuRecipe>;
    recipe_links: Record<Course, string>;
}
export interface MealPlanSummary {
    id: number;
    title: string;
    start_date: string;
    days: number;
    people: number;
    status: string;
}
export interface MealPlan extends MealPlanSummary {
    error_message: string | null;
    criteria: {
        quick_minutes: number;
        normal_minutes: number;
        difficulty: number;
        vegetarian: boolean;
    };
    meals: PlannedMeal[];
}
