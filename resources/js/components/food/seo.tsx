import { Head, usePage } from '@inertiajs/react';

export function Seo({
    title,
    description = 'Des recettes généreuses, des idées simples et le plaisir de cuisiner ensemble. Découvrez Cuistot Club et partagez vos bonnes recettes.',
    image = '/images/salad.webp',
    path = '/',
    schema,
    privatePreview = false,
}: {
    title: string;
    description?: string;
    image?: string;
    path?: string;
    schema?: Record<string, unknown>;
    privatePreview?: boolean;
}) {
    const siteUrl = String(
        usePage().props.siteUrl || 'https://idee.test',
    ).replace(/\/$/, '');
    const url = siteUrl + path;
    const imageUrl = image.startsWith('http') ? image : siteUrl + image;
    return (
        <Head title={title}>
            {privatePreview && (
                <meta
                    name="robots"
                    content="noindex,nofollow"
                    head-key="robots"
                />
            )}
            <meta
                name="description"
                content={description}
                head-key="description"
            />
            <link rel="canonical" href={url} head-key="canonical" />
            <meta
                property="og:title"
                content={`${title} · Cuistot Club`}
                head-key="og-title"
            />
            <meta
                property="og:description"
                content={description}
                head-key="og-description"
            />
            <meta property="og:url" content={url} head-key="og-url" />
            <meta property="og:image" content={imageUrl} head-key="og-image" />
            <meta
                property="og:type"
                content={schema ? 'article' : 'website'}
                head-key="og-type"
            />
            <meta name="twitter:card" content="summary_large_image" />
            {schema && (
                <script
                    type="application/ld+json"
                    head-key="recipe-schema"
                    dangerouslySetInnerHTML={{
                        __html: JSON.stringify(schema)
                            .replace(/</g, '\\u003c')
                            .replace(/>/g, '\\u003e')
                            .replace(/&/g, '\\u0026'),
                    }}
                />
            )}
        </Head>
    );
}
