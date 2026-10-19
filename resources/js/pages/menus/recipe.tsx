import { allergenLabels } from '@/types/dietary';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { type MenuRecipe } from '@/types/menus';

export default function SavedRecipe({
    plan,
    recipe,
}: {
    plan: { id: number; title: string; people: number };
    recipe: MenuRecipe;
}) {
    return (
        <article className="site-container menu-page menu-saved-recipe">
            <Head title={recipe.title}>
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <Link className="menu-back" href={`/menus/${plan.id}`}>
                <ArrowLeft size={15} /> Retour à mon menu
            </Link>
            <div className="menu-heading">
                <div>
                    <span className="menu-eyebrow">
                        LA FICHE CONSERVÉE DANS VOTRE MENU
                    </span>
                    <h1>{recipe.title}</h1>
                    <p>
                        Pour {plan.people} personnes · {recipe.total_minutes}{' '}
                        min
                    </p>
                </div>
            </div>
            {recipe.accompaniment && (
                <p>Accompagnement : {recipe.accompaniment}</p>
            )}
            <p className="my-4 rounded-xl border p-3 text-sm">
                Allergènes :{' '}
                {recipe.allergens == null
                    ? 'analyse non renseignée'
                    : recipe.allergens.length
                      ? recipe.allergens
                            .map((key) => allergenLabels[key] ?? key)
                            .join(', ')
                      : 'aucun identifié dans les ingrédients déclarés'}
                . Étiquettes et traces à vérifier.
            </p>
            <h2>Dans le panier</h2>
            <ul className="menu-saved-ingredients">
                {recipe.ingredients.map((item, index) => (
                    <li key={index}>
                        <span>{item.name}</span>
                        <strong>
                            {item.quantity !== null
                                ? `${Number(((Number(item.quantity) * plan.people) / recipe.servings).toFixed(2)).toLocaleString('fr-FR')} ${item.unit}`
                                : 'Quantité à vérifier'}
                        </strong>
                    </li>
                ))}
            </ul>
            <h2>À vous de jouer</h2>
            <ol className="menu-saved-steps">
                {(recipe.steps || [recipe.description]).map((step, index) => (
                    <li key={index}>{step}</li>
                ))}
            </ol>
        </article>
    );
}
