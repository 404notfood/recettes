import { Link, router, useForm } from '@inertiajs/react';
import { Check, Eye, Pencil, Plus, Shield, Trash2, Star } from 'lucide-react';
import { useState, type FormEvent } from 'react';
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
import {
    statusLabels,
    type Category,
    type Pagination,
    type Recipe,
} from '@/types/food';

type Member = { id: number; name: string; email: string; is_admin: boolean };
function ModerationCard({ recipe }: { recipe: Recipe }) {
    const form = useForm({ action: 'publish', note: '' });
    const [expanded, setExpanded] = useState(false);
    function submit(action: string) {
        form.transform((d) => ({ ...d, action }));
        form.put(`/administration/recettes/${recipe.id}`, {
            preserveScroll: true,
        });
    }
    return (
        <article className="moderation-card">
            <div className="flex items-start gap-5">
                <img
                    src={recipe.image_url}
                    alt={recipe.title}
                    className="h-24 w-24 rounded-xl object-cover"
                />
                <div>
                    <span className="status-badge status-pending">
                        {recipe.has_revision
                            ? 'Modification à valider'
                            : 'Nouvelle proposition'}
                    </span>
                    <h3>{recipe.title}</h3>
                    <p>
                        {recipe.author_name} · {recipe.category.name} ·{' '}
                        {recipe.total_time} min
                    </p>
                    {recipe.guest_email && (
                        <small className="text-[10px] text-muted-foreground">
                            Contact privé : {recipe.guest_email}
                        </small>
                    )}
                </div>
            </div>
            <p>{recipe.description}</p>
            <button
                className="text-arrow mt-4"
                onClick={() => setExpanded(!expanded)}
            >
                {expanded ? 'Masquer' : 'Lire'} la recette complète
            </button>
            {expanded && (
                <div className="mt-5 grid gap-5 md:grid-cols-2">
                    <div>
                        <strong className="text-xs">
                            Ingrédients · {recipe.servings} personnes
                        </strong>
                        <ul>
                            {recipe.ingredients?.map((item, i) => (
                                <li key={i}>
                                    {item.quantity} {item.unit} {item.name}
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div>
                        <strong className="text-xs">Préparation</strong>
                        <ol>
                            {recipe.steps?.map((step, i) => (
                                <li key={i}>{step}</li>
                            ))}
                        </ol>
                    </div>
                </div>
            )}
            <div className="field">
                <label htmlFor={`note-${recipe.id}`}>
                    Message pour une demande de corrections
                </label>
                <textarea
                    id={`note-${recipe.id}`}
                    rows={2}
                    value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)}
                    placeholder="Expliquez ce qui doit être précisé…"
                />
                {form.errors.note && (
                    <p className="field-error">{form.errors.note}</p>
                )}
            </div>
            <div className="form-actions">
                <Button
                    className="pill-button"
                    disabled={form.processing}
                    onClick={() => submit('publish')}
                >
                    <Check size={16} />
                    Valider et publier
                </Button>
                <Button
                    className="pill-button"
                    variant="outline"
                    disabled={form.processing}
                    onClick={() => submit('changes')}
                >
                    Demander des précisions
                </Button>
            </div>
        </article>
    );
}
function CategoryRow({ category }: { category: Category }) {
    const form = useForm({
        name: category.name,
        description: category.description || '',
    });
    return (
        <tr>
            <td>
                <input
                    aria-label={`Nom de la catégorie ${category.name}`}
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                />
                {form.errors.name && (
                    <p className="field-error">{form.errors.name}</p>
                )}
            </td>
            <td>{category.recipes_count}</td>
            <td>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={form.processing}
                    onClick={() =>
                        form.put(`/administration/categories/${category.id}`, {
                            preserveScroll: true,
                        })
                    }
                >
                    Enregistrer
                </Button>
            </td>
            <td>
                <Button
                    variant="ghost"
                    size="icon"
                    disabled={Boolean(category.recipes_count)}
                    aria-label={`Supprimer la catégorie vide ${category.name}`}
                    onClick={() =>
                        router.delete(
                            `/administration/categories/${category.id}`,
                            { preserveScroll: true },
                        )
                    }
                >
                    <Trash2 size={15} />
                </Button>
            </td>
        </tr>
    );
}
export default function Admin({
    pending,
    recipes,
    categories,
    users,
    stats,
}: {
    pending: Recipe[];
    recipes: Pagination<Recipe>;
    categories: Category[];
    users: Member[];
    stats: {
        published: number;
        pending: number;
        members: number;
        categories: number;
    };
}) {
    const [tab, setTab] = useState('pending');
    const [archive, setArchive] = useState<Recipe | null>(null);
    const categoryForm = useForm({ name: '', description: '' });
    const [memberSearch, setMemberSearch] = useState('');
    const [roleChange, setRoleChange] = useState<Member | null>(null);
    function categorySubmit(e: FormEvent) {
        e.preventDefault();
        categoryForm.post('/administration/categories', {
            preserveScroll: true,
            onSuccess: () => categoryForm.reset(),
        });
    }
    return (
        <>
            <Seo title="Administration" path="/administration" />
            <div className="site-container page-heading">
                <div className="eyebrow">
                    <Shield size={15} />
                    LE CARNET, CÔTÉ COULISSES
                </div>
                <h1>
                    Faire grandir <em>les bonnes idées.</em>
                </h1>
                <p>
                    Relisez les propositions, accompagnez les auteurs et prenez
                    soin du catalogue.
                </p>
            </div>
            <section className="site-container page-content">
                <div className="stats-grid">
                    {[
                        [stats.published, 'Recettes publiées'],
                        [stats.pending, 'Propositions à relire'],
                        [stats.members, 'Comptes membres'],
                        [stats.categories, 'Catégories'],
                    ].map(([value, label]) => (
                        <div className="stat-card" key={label}>
                            <strong>{value}</strong>
                            <span>{label}</span>
                        </div>
                    ))}
                </div>
                <nav
                    className="admin-tabs"
                    aria-label="Sections de l’administration"
                >
                    {[
                        ['pending', `À valider (${pending.length})`],
                        ['recipes', 'Le catalogue'],
                        ['categories', 'Les catégories'],
                        ['members', 'Les membres'],
                    ].map(([value, label]) => (
                        <button
                            key={value}
                            className={tab === value ? 'active' : ''}
                            aria-pressed={tab === value}
                            onClick={() => setTab(value)}
                        >
                            {label}
                        </button>
                    ))}
                </nav>
                {tab === 'pending' &&
                    (pending.length ? (
                        pending.map((r) => (
                            <ModerationCard recipe={r} key={r.id} />
                        ))
                    ) : (
                        <div className="empty-state">
                            <Check />
                            <h2>Le carnet est à jour.</h2>
                            <p>Aucune proposition en attente de validation.</p>
                        </div>
                    ))}
                {tab === 'recipes' && (
                    <>
                        <div className="admin-table-wrap">
                            <table className="admin-table">
                                <thead>
                                    <tr>
                                        <th>Recette</th>
                                        <th>Auteur</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recipes.data.map((r) => (
                                        <tr key={r.id}>
                                            <td>{r.title}</td>
                                            <td>{r.author_name}</td>
                                            <td>
                                                <span
                                                    className={`status-badge status-${r.status}`}
                                                >
                                                    {
                                                        statusLabels[
                                                            r.status || ''
                                                        ]
                                                    }
                                                </span>
                                            </td>
                                            <td>
                                                <div className="flex gap-1">
                                                    {r.status ===
                                                        'published' && (
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <Link
                                                                href={`/recettes/${r.slug}`}
                                                                aria-label={`Voir ${r.title}`}
                                                            >
                                                                <Eye
                                                                    size={15}
                                                                />
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                    >
                                                        <Link
                                                            href={`/mes-recettes/${r.id}/modifier`}
                                                            aria-label={`Modifier ${r.title}`}
                                                        >
                                                            <Pencil size={15} />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Mettre en avant ${r.title}`}
                                                        onClick={() =>
                                                            router.put(
                                                                `/administration/recettes/${r.id}`,
                                                                {
                                                                    action: r.featured
                                                                        ? 'unfeature'
                                                                        : 'feature',
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Star
                                                            size={15}
                                                            fill={
                                                                r.featured
                                                                    ? 'currentColor'
                                                                    : 'none'
                                                            }
                                                        />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            setArchive(r)
                                                        }
                                                        aria-label={`Archiver ${r.title}`}
                                                    >
                                                        <Trash2 size={15} />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {recipes.last_page > 1 && (
                            <nav
                                className="pagination"
                                aria-label="Pagination du catalogue"
                            >
                                {recipes.links
                                    .filter((l) => l.url)
                                    .map((l, i) => (
                                        <Link
                                            key={i}
                                            href={l.url!}
                                            className={l.active ? 'active' : ''}
                                        >
                                            {l.label.includes('Previous') ||
                                            l.label.includes('précédent')
                                                ? '←'
                                                : l.label.includes('Next') ||
                                                    l.label.includes('suivant')
                                                  ? '→'
                                                  : l.label}
                                        </Link>
                                    ))}
                            </nav>
                        )}
                    </>
                )}
                {tab === 'categories' && (
                    <>
                        <form
                            className="admin-category-form"
                            onSubmit={categorySubmit}
                        >
                            <div className="field">
                                <label htmlFor="category-name">
                                    Nouvelle catégorie
                                </label>
                                <input
                                    id="category-name"
                                    value={categoryForm.data.name}
                                    onChange={(e) =>
                                        categoryForm.setData(
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    placeholder="Ex. Cuisine du monde"
                                />
                                {categoryForm.errors.name && (
                                    <p className="field-error">
                                        {categoryForm.errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="field">
                                <label htmlFor="category-description">
                                    Description
                                </label>
                                <input
                                    id="category-description"
                                    value={categoryForm.data.description}
                                    onChange={(e) =>
                                        categoryForm.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                className="pill-button"
                                disabled={categoryForm.processing}
                            >
                                <Plus size={15} />
                                Ajouter
                            </Button>
                        </form>
                        <div className="admin-table-wrap">
                            <table className="admin-table">
                                <thead>
                                    <tr>
                                        <th>Catégorie</th>
                                        <th>Recettes</th>
                                        <th>Modifier</th>
                                        <th>Supprimer si vide</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {categories.map((c) => (
                                        <CategoryRow category={c} key={c.id} />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
                {tab === 'members' && (
                    <>
                        <div className="field mb-5">
                            <label htmlFor="member-search">
                                Rechercher un membre
                            </label>
                            <input
                                id="member-search"
                                value={memberSearch}
                                onChange={(e) =>
                                    setMemberSearch(e.target.value)
                                }
                                placeholder="Nom ou adresse e-mail"
                            />
                        </div>
                        <div className="admin-table-wrap">
                            <table className="admin-table">
                                <thead>
                                    <tr>
                                        <th>Membre</th>
                                        <th>E-mail</th>
                                        <th>Rôle</th>
                                        <th>Modifier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users
                                        .filter((u) =>
                                            `${u.name} ${u.email}`
                                                .toLowerCase()
                                                .includes(
                                                    memberSearch.toLowerCase(),
                                                ),
                                        )
                                        .map((u) => (
                                            <tr key={u.id}>
                                                <td>{u.name}</td>
                                                <td>{u.email}</td>
                                                <td>
                                                    {u.is_admin
                                                        ? 'Administrateur'
                                                        : 'Membre'}
                                                </td>
                                                <td>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setRoleChange(u)
                                                        }
                                                    >
                                                        {u.is_admin
                                                            ? 'Retirer le rôle admin'
                                                            : 'Nommer administrateur'}
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
                <Dialog
                    open={Boolean(archive)}
                    onOpenChange={(open) => !open && setArchive(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Archiver cette recette ?</DialogTitle>
                            <DialogDescription>
                                « {archive?.title} » disparaîtra du catalogue.
                                Son enregistrement sera conservé en base.
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
                <Dialog
                    open={Boolean(roleChange)}
                    onOpenChange={(open) => !open && setRoleChange(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                Changer les droits de {roleChange?.name} ?
                            </DialogTitle>
                            <DialogDescription>
                                {roleChange?.is_admin
                                    ? 'Ce membre perdra l’accès à l’administration.'
                                    : 'Ce membre pourra valider les recettes et gérer les membres et catégories.'}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setRoleChange(null)}
                            >
                                Annuler
                            </Button>
                            <Button
                                onClick={() => {
                                    if (roleChange)
                                        router.put(
                                            `/administration/membres/${roleChange.id}`,
                                            { is_admin: !roleChange.is_admin },
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    setRoleChange(null),
                                            },
                                        );
                                }}
                            >
                                Confirmer
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </section>
        </>
    );
}
