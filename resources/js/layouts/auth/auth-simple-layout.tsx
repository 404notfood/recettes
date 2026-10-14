import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Brand } from '@/components/food/brand';
import type { AuthLayoutProps } from '@/types';

const labels: Record<string, string> = {
    'Log in to your account': 'Votre carnet vous attend.',
    'Enter your email and password below to log in':
        'Retrouvez vos recettes, vos favoris et vos prochaines bonnes idées.',
    'Create an account': 'Un carnet rien que pour vous.',
    'Enter your details below to create your account':
        'Gardez vos coups de cœur et partagez vos petits succès.',
    'Forgot password': 'On retrouve votre carnet.',
    'Enter your email to receive a password reset link':
        'Indiquez votre e-mail pour recevoir un lien de réinitialisation.',
    'Reset password': 'Un nouveau départ.',
    'Please enter your new password below':
        'Choisissez un nouveau mot de passe pour votre compte.',
    'Verify email': 'Encore un petit geste.',
    'Please verify your email address by clicking on the link we just emailed to you.':
        'Confirmez votre e-mail avec le lien envoyé pour ouvrir votre carnet.',
    'Confirm password': 'Un petit contrôle de sécurité.',
    'This is a secure area of the application. Please confirm your password before continuing.':
        'Confirmez votre mot de passe pour continuer.',
};
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="food-site auth-food">
            <aside className="auth-editorial">
                <img src="/images/bowl.webp" alt="" aria-hidden="true" />
                <Brand light />
                <div className="auth-quote">
                    <h2>
                        Le goût des choses simples.
                        <br />
                        <em>Et des idées partagées.</em>
                    </h2>
                    <p>
                        Bienvenue dans le carnet des gens qui aiment se régaler.
                    </p>
                </div>
            </aside>
            <main className="auth-panel">
                <div className="auth-panel-content">
                    <Link href="/">
                        <ArrowLeft size={14} />
                        Revenir aux bonnes idées
                    </Link>
                    <h1>{labels[title || ''] || title}</h1>
                    <p>{labels[description || ''] || description}</p>
                    {children}
                </div>
            </main>
        </div>
    );
}
