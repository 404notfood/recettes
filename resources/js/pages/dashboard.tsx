import { Link, router, usePage } from '@inertiajs/react';
import { ArrowUpRight, ChefHat, Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Seo } from '@/components/food/seo';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { statusLabels, type Recipe } from '@/types/food';

export default function Dashboard({
    recipes,
    stats,
}: {
    recipes: Recipe[];
    stats: {
        total: number;
        published: number;
        pending: number;
        favorites: number;
    };
}) {
    const user = usePage().props.auth.user;
    const [archive, setArchive] = useState<Recipe | null>(null);
    return (
        <>
            <Seo title="Mon carnet de recettes" path="/dashboard" />
            <div className="site-container page-heading page-heading-row">
                <div>
                    <div className="eyebrow">
                        LE PLAISIR DE DIRE « C’EST MOI QUI L’AI FAIT »
                    </div>
                    <h1>
                        Bonjour {user.name.split(' ')[0]}.<br />
                        <em>Qu’est-ce qu’on mijote ?</em>
                    </h1>
                    <p>
                        Vos recettes, vos propositions et vos prochains petits
                        succès.
                    </p>
                </div>
                <Button asChild className="pill-button">
                    <Link href="/proposer">
                        <Plus size={16} />
                        Une nouvelle recette
                    </Link>
                </Button>
            </div>
            <section className="site-container page-content">
                <nav className="account-nav">
                    <Link href="/dashboard" className="active">
                        Mes recettes
                    </Link>
                    <Link href="/mes-favoris">Mes favoris</Link>
                    <Link href="/menus">Mes menus</Link>
                    <Link href="/settings/profile">Mon compte</Link>
                    {Boolean(user.is_admin) && (
                        <Link href="/administration">Administration</Link>
                    )}
                </nav>
                <div className="stats-grid">
                    {[
                        [stats.total, 'Recettes dans mon carnet'],
                        [stats.published, 'Recettes publiées'],
                        [stats.pending, 'Propositions en validation'],
                        [stats.favorites, 'Recettes favorites'],
                    ].map(([value, label]) => (
                        <div className="stat-card" key={label}>
                            <strong>{value}</strong>
                            <span>{label}</span>
                        </div>
                    ))}
                </div>
                {recipes.map((recipe) => (
                    <article className="manage-card" key={recipe.id}>
                        <img src={recipe.image_url} alt={recipe.title} />
                        <div>
                            <span
                                className={`status-badge status-${recipe.status}`}
                            >
                                {recipe.has_revision
                                    ? recipe.moderation_note
                                        ? 'Modification à compléter'
                                        : 'Modification en validation'
                                    : statusLabels[recipe.status || '']}
                            </span>
                            <h3>{recipe.title}</h3>
                            <small>
                                {recipe.category.name} · {recipe.total_time} min
                            </small>
                            {recipe.moderation_note && (
                                <p className="moderation-note">
                                    L’équipe vous propose :{' '}
                                    {recipe.moderation_note}
                                </p>
                            )}
                        </div>
                        <div className="manage-actions">
                            {recipe.status === 'published' && (
                                <Button asChild variant="ghost" size="icon">
                                    <Link
                                        href={`/recettes/${recipe.slug}`}
                                        aria-label={`Voir ${recipe.title}`}
                                    >
                                        <Eye size={17} />
                                    </Link>
                                </Button>
                            )}
                            <Button asChild variant="outline" size="icon">
                                <Link
                                    href={`/mes-recettes/${recipe.id}/modifier`}
                                    aria-label={`Modifier ${recipe.title}`}
                                >
                                    <Pencil size={16} />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Archiver ${recipe.title}`}
                                onClick={() => setArchive(recipe)}
                            >
                                <Trash2 size={16} />
                            </Button>
                        </div>
                    </article>
                ))}
                {!recipes.length && (
                    <div className="empty-state">
                        <ChefHat />
                        <h2>Le début de votre histoire.</h2>
                        <p>
                            Votre première recette n’attend que vous. Une idée
                            toute simple suffit.
                        </p>
                        <Button asChild className="pill-button">
                            <Link href="/proposer">
                                Partager ma première recette{' '}
                                <ArrowUpRight size={16} />
                            </Link>
                        </Button>
                    </div>
                )}
                <Dialog
                    open={Boolean(archive)}
                    onOpenChange={(open) => !open && setArchive(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Archiver cette recette ?</DialogTitle>
                            <DialogDescription>
                                « {archive?.title} » sera retirée du catalogue
                                et de votre carnet. Son enregistrement est
                                conservé en base.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setArchive(null)}
                            >
                                Annuler
                            </Button>
                            <Button
                                onClick={() => {
                                    if (archive)
                                        router.delete(
                                            `/mes-recettes/${archive.id}`,
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    setArchive(null),
                                            },
                                        );
                                }}
                            >
                                Archiver
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </section>
        </>
    );
}
