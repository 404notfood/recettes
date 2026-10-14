import { Link } from '@inertiajs/react';
import { Seo } from '@/components/food/seo';

export default function Privacy() {
    return (
        <>
            <Seo title="Vos données et votre carnet" path="/confidentialite" />
            <div className="site-container page-heading">
                <div className="eyebrow">VOS DONNÉES, EN TOUTE CLARTÉ</div>
                <h1>
                    Un carnet ouvert.
                    <br />
                    <em>Des informations privées.</em>
                </h1>
            </div>
            <div className="site-container prose-page">
                <h2>Consulter les recettes</h2>
                <p>
                    La consultation du catalogue et des fiches recettes ne
                    nécessite pas de compte. Les cookies techniques servent
                    notamment à sécuriser les formulaires et à gérer les
                    sessions. Ce site n’intègre pas de publicité ni d’outil de
                    suivi publicitaire.
                </p>
                <h2>Proposer une recette sans compte</h2>
                <p>
                    Votre nom accompagne la recette si elle est publiée. Votre
                    adresse e-mail est conservée avec la proposition pour
                    permettre à l’équipe de vous contacter au sujet de celle-ci
                    ; elle est accessible uniquement aux administrateurs et
                    n’est jamais affichée sur les pages publiques.
                </p>
                <h2>Créer un carnet personnel</h2>
                <p>
                    Le compte utilise votre nom, votre e-mail et un mot de passe
                    stocké sous forme hachée. Vos favoris sont privés. Les
                    commentaires que vous publiez sont visibles avec votre nom.
                    Vous pouvez gérer votre compte depuis{' '}
                    <Link href="/settings/profile" className="underline">
                        les paramètres du profil
                    </Link>
                    .
                </p>
                <h2>Partager du contenu</h2>
                <p>
                    Proposez uniquement des textes et des photos que vous avez
                    créés ou que vous êtes autorisé à publier. En soumettant une
                    recette, vous autorisez sa publication sur Cuistot Club
                    après validation, sous votre nom. N’incluez pas
                    d’informations personnelles sur d’autres personnes dans une
                    recette ou un commentaire.
                </p>
                <h2>Version locale du projet</h2>
                <p>
                    Cette version de développement fonctionne sur idee.test.
                    Avant l’ouverture d’un site public, les coordonnées de
                    l’éditeur, les modalités de contact et les durées de
                    conservation doivent être complétées dans cette page.
                </p>
            </div>
        </>
    );
}
