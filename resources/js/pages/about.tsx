import { Link } from '@inertiajs/react';
import { ArrowUpRight, Heart } from 'lucide-react';
import { Seo } from '@/components/food/seo';
import { Button } from '@/components/ui/button';

export default function About() {
    return (
        <>
            <Seo title="L’esprit Cuistot Club" path="/a-propos" />
            <div className="site-container page-heading">
                <div className="eyebrow">
                    <Heart size={15} />
                    LA CUISINE EST UNE HISTOIRE DE PARTAGE
                </div>
                <h1>
                    Des bonnes recettes.
                    <br />
                    <em>Et des bonnes personnes.</em>
                </h1>
            </div>
            <div className="site-container prose-page">
                <p>
                    Cuistot Club est un carnet collectif pour les gens qui
                    aiment cuisiner, manger et transmettre. Une recette n’a pas
                    besoin d’être spectaculaire pour être précieuse. Elle peut
                    être celle qu’on prépare tous les mercredis, celle qu’on a
                    apprise en famille, ou celle qu’on a inventée un soir.
                </p>
                <h2>Un carnet ouvert à tous.</h2>
                <p>
                    Toutes les recettes publiées sont accessibles sans
                    inscription. Pour proposer une idée, un prénom et une
                    adresse e-mail suffisent. Un compte vous permet de retrouver
                    vos propres recettes, de garder vos favoris et de participer
                    aux discussions.
                </p>
                <h2>Du soin avant de servir.</h2>
                <p>
                    Chaque proposition passe par une relecture : des ingrédients
                    compréhensibles, des étapes assez précises pour être
                    reproduites et une photo adaptée. Les modifications d’une
                    recette publiée sont également relues ; la version actuelle
                    reste accessible pendant ce temps.
                </p>
                <h2>Votre recette, votre signature.</h2>
                <p>
                    Les contributions sont publiées sous le nom choisi par leur
                    auteur. Les recettes de départ sont signées « La rédaction
                    Cuistot Club » et leurs photos sont des illustrations issues
                    d’Unsplash. Les avis et notes affichés viennent uniquement
                    de contributions enregistrées.
                </p>
                <h2>Le plaisir des choses simples.</h2>
                <p>
                    Des produits à votre portée. Des gestes qu’on peut
                    apprendre. Et une table qui accueille tout le monde. C’est
                    tout l’esprit Cuistot Club.
                </p>
                <Button asChild className="pill-button mt-4">
                    <Link href="/proposer">
                        Ajouter ma bonne idée <ArrowUpRight size={16} />
                    </Link>
                </Button>
            </div>
        </>
    );
}
