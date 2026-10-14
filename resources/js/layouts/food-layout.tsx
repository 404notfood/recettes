import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    BookHeart,
    ChefHat,
    Menu,
    Plus,
    X,
    LogOut,
    Settings,
    Shield,
    ArrowRight,
    CalendarDays,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Brand } from '@/components/food/brand';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';

export default function FoodLayout({ children }: { children: ReactNode }) {
    const { auth, flash } = usePage().props;
    const user = auth.user;
    const message = flash as { success?: string; error?: string } | undefined;
    const [mobileOpen, setMobileOpen] = useState(false);
    const url = usePage().url;
    useEffect(() => {
        if (message?.success) toast.success(message.success);
        if (message?.error) toast.error(message.error);
    }, [message?.success, message?.error]);
    useEffect(() => {
        setMobileOpen(false);
    }, [url]);
    return (
        <div className="food-site">
            <a href="#main" className="skip-link">
                Aller au contenu
            </a>
            <div className="announcement">
                <span>Un peu de vous. Beaucoup de goût.</span>
                <span className="announcement-right">
                    Le plaisir se partage <ArrowUpRight size={12} />
                </span>
            </div>
            <header className="site-header">
                <div className="site-container header-inner">
                    <Brand />
                    <nav
                        className="desktop-nav"
                        aria-label="Navigation principale"
                    >
                        <Link
                            href="/recettes"
                            className={
                                url.startsWith('/recettes') ? 'active' : ''
                            }
                        >
                            Les recettes
                        </Link>
                        <Link href="/recettes?tag=de-saison">
                            De saison <span className="nav-dot" />
                        </Link>
                        <Link href="/a-propos">L’esprit Cuistot Club</Link>
                        <Link
                            href="/menus"
                            className={url.startsWith('/menus') ? 'active' : ''}
                        >
                            Les menus
                        </Link>
                    </nav>
                    <div className="header-actions">
                        {user ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        className="account-button"
                                    >
                                        <span className="user-initial">
                                            {user.name.charAt(0)}
                                        </span>
                                        <span className="hidden sm:inline">
                                            Mon carnet
                                        </span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem asChild>
                                        <Link href="/dashboard">
                                            <ChefHat />
                                            Mes recettes
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href="/menus">
                                            <CalendarDays /> Mes menus
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href="/mes-favoris">
                                            <BookHeart />
                                            Mes favoris
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href="/settings/profile">
                                            <Settings />
                                            Mon compte
                                        </Link>
                                    </DropdownMenuItem>
                                    {Boolean(user.is_admin) && (
                                        <DropdownMenuItem asChild>
                                            <Link href="/administration">
                                                <Shield />
                                                Administration
                                            </Link>
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onSelect={() => router.post('/logout')}
                                    >
                                        <LogOut />
                                        Déconnexion
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <Link href="/login" className="login-link">
                                Se connecter
                            </Link>
                        )}
                        <Button asChild className="pill-button header-submit">
                            <Link href="/proposer">
                                <Plus size={16} />
                                Proposer une recette
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="mobile-toggle"
                            onClick={() => setMobileOpen(!mobileOpen)}
                            aria-label={
                                mobileOpen ? 'Fermer le menu' : 'Ouvrir le menu'
                            }
                            aria-expanded={mobileOpen}
                        >
                            {mobileOpen ? <X /> : <Menu />}
                        </Button>
                    </div>
                </div>
                {mobileOpen && (
                    <nav className="mobile-nav" aria-label="Navigation mobile">
                        <Link href="/recettes">Les recettes</Link>
                        <Link href="/recettes?tag=de-saison">De saison</Link>
                        <Link href="/a-propos">L’esprit Cuistot Club</Link>
                        <Link href="/proposer">Proposer une recette</Link>
                        <Link href="/menus">Les menus</Link>
                        {!user && <Link href="/login">Se connecter</Link>}
                    </nav>
                )}
            </header>
            <main id="main">{children}</main>
            <footer className="site-footer">
                <div className="site-container footer-main">
                    <div>
                        <Brand light />
                        <p>
                            Les bonnes recettes méritent
                            <br />
                            de sortir du tiroir.
                        </p>
                    </div>
                    <div className="footer-links">
                        <span>À TABLE</span>
                        <Link href="/recettes">Toutes les recettes</Link>
                        <Link href="/recettes?time=30">
                            Prêtes en 30 minutes
                        </Link>
                        <Link href="/recettes?tag=vegetarien">
                            Cuisine végétarienne
                        </Link>
                    </div>
                    <div className="footer-links">
                        <span>ENSEMBLE</span>
                        <Link href="/proposer">Partager ma recette</Link>
                        <Link href={user ? '/mes-favoris' : '/register'}>
                            Mon carnet de recettes
                        </Link>
                        <Link href="/a-propos">L’esprit Cuistot Club</Link>
                    </div>
                    <div className="footer-note">
                        <span>FAIT AVEC ENVIE</span>
                        <p>
                            De vrais ingrédients.
                            <br />
                            De belles idées.
                            <br />
                            <em>Et vous, surtout.</em>
                        </p>
                        <Link href="/proposer">
                            À votre tour <ArrowRight size={17} />
                        </Link>
                    </div>
                </div>
                <div className="site-container footer-bottom">
                    <span>
                        © {new Date().getFullYear()}{' '}
                        <a
                            href="https://cuistot-club.fr/"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Cuistot Club
                        </a>
                        . Le goût des choses simples.
                    </span>
                    <Link href="/confidentialite">Confidentialité</Link>
                    <a
                        className="creator-credit"
                        href="https://404notfood.fr/"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Mijoté avec soin par 404NotFood, découvrir le créateur"
                    >
                        <span className="credit-heart" aria-hidden="true">
                            ♡
                        </span>
                        <span>Mijoté avec soin par</span>
                        <span className="creator-name">
                            <span className="creator-code">404</span>NotFood
                        </span>
                        <ArrowUpRight size={12} aria-hidden="true" />
                    </a>
                </div>
            </footer>
        </div>
    );
}
