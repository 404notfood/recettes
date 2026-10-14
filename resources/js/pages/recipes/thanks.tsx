import { Link } from '@inertiajs/react';
import { ArrowRight, Check, Heart } from 'lucide-react';
import { Seo } from '@/components/food/seo';
import { Button } from '@/components/ui/button';

export default function Thanks() {
    return (
        <>
            <Seo
                title="Merci pour votre bonne idée"
                path="/proposition-envoyee"
            />
            <div className="site-container thanks-page">
                <div className="thanks-icon">
                    <Check size={40} />
                </div>
                <div className="eyebrow justify-center">
                    UNE NOUVELLE IDÉE DANS LE CARNET
                </div>
                <h1 className="mt-5">
                    Merci.
                    <br />
                    <em>Ça sent déjà bon !</em>
                </h1>
                <p>
                    Votre recette a bien été transmise à notre équipe. Nous
                    allons la relire et vérifier que chacun pourra la reproduire
                    avant de la publier avec votre nom.
                </p>
                <p>
                    Votre adresse e-mail reste privée. Vous pouvez déjà
                    découvrir les autres bonnes idées du carnet.
                </p>
                <div className="form-actions">
                    <Button asChild className="pill-button">
                        <Link href="/recettes">
                            Explorer les recettes <ArrowRight size={16} />
                        </Link>
                    </Button>
                    <Button asChild variant="outline" className="pill-button">
                        <Link href="/register">
                            <Heart size={16} />
                            Créer mon carnet
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
