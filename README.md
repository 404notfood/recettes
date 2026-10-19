# Cuistot Club

Le projet de référence est **idee**. Les dossiers voisins `cuisine` et `recettu` sont des sources de comparaison ; les fonctionnalités retenues sont intégrées ici, dans les modèles, les routes et la charte Cuistot Club.

Site de recettes en français, construit sur le starter officiel Laravel 13 / React 19 / Inertia 3 / TypeScript, avec PostgreSQL, Tailwind, shadcn/ui et Magic UI (Blur Fade et Marquee).

## Version locale

- Site : https://idee.test
- Catalogue public : `/recettes`
- Proposer une recette sans compte : `/proposer`
- Espace membre : `/dashboard`
- Favoris : `/mes-favoris`
- Administration : `/administration`

Le virtual host Laragon doit pointer sur `public/`. La configuration Nginx reste gérée par Laragon. La base `idee` et les identifiants de connexion sont ceux de `.env`.

Le compte administrateur local a un mot de passe aléatoire, enregistré dans `storage/app/private/local-admin.txt`. Ce fichier est privé, ignoré par Git et absent du disque public. Le seeder crée ce compte uniquement si `APP_ENV=local`. Aucun compte administrateur avec mot de passe prédéfini n’est créé en production.

```powershell
# Utiliser un PHP avec pdo_pgsql, fileinfo et GD activés.
php artisan migrate
php artisan storage:link
npm run build:ssr
php artisan inertia:start-ssr
```

Le script `scripts/Start-Local.ps1` lance le rendu SSR en arrière-plan sur la machine locale, avec des journaux dans `storage/app/private`. Laragon continue de servir les requêtes HTTP. Relancer le SSR après une nouvelle compilation.

`php artisan db:seed` ajoute les 8 recettes de présentation et une collection de 102 recettes supplémentaires, avec leurs catégories, sans supprimer les données existantes. Le catalogue de départ comprend 110 recettes dans 10 catégories : au moins 10 recettes par catégorie, dont 16 dans « Fait maison » et 14 dans « Petites douceurs ». Ces recettes sont signées par la rédaction ; aucun faux commentaire, avis ou compteur de visites n’est ajouté. Ne pas lancer ce seeder sur un catalogue public sans vouloir ajouter ce contenu.

La collection est conservée dans `database/seeders/data/recipe-collection.json` : entrées, plats, cuisine végétale, mijotés, desserts, potages, recettes japonaises, salades, brunch, pains et pâtes de base. `php artisan db:seed --class=RecipeCollectionSeeder` permet de l’ajouter seule. Une nouvelle exécution ne crée pas de doublons et conserve les recettes déjà modifiées ou archivées. Les 102 couvertures sont de vraies photographies, servies localement en WebP. Leurs crédits sont conservés dans `resources/data/recipe-photo-credits.json` et affichés sous la photo de chaque fiche ; ils ne s’affichent plus si cette photo est remplacée. Les temps de préparation incluent les repos et levées indiqués dans les fiches. Les pâtes crues précisent que la cuisson se poursuit dans la recette qui les utilise.

## Fonctionnement

Les membres vérifiés peuvent composer leurs menus dans **Les menus** (`/menus`) : 1, 7 ou 14 jours, midi et soir, nombre de personnes, difficulté et créneaux rapides. Chaque recette mène à sa fiche ; une copie privée reste accessible depuis le planning si l'original est archivé. Les exports PDF conservent une page paysage par semaine et regroupent les courses par catégories. **Imprimer des recettes** permet de choisir les fiches, d'adapter les quantités au foyer et d'imprimer ou télécharger les étapes sans photographie. Le fonctionnement hybride et le worker sont décrits dans [docs/MENUS.md](docs/MENUS.md).

Le formulaire des menus propose aussi la saison selon la date, les choix sans porc, sans alcool, sans gluten ou lactose, le végétalien et les 14 catégories d’allergènes. Le mode varié compose des plats complets avec accompagnement et évite les répétitions dans chaque semaine, tout en conservant les règles de diversité existantes. Les mêmes filtres se retrouvent dans la recherche de recettes. Les déclarations d’allergènes ne certifient pas l’absence de traces ni la conformité halal ou casher.

Les visiteurs consultent toutes les recettes publiées, recherchent par titre, description ou ingrédient et filtrent par catégorie, temps total, difficulté ou tag. Ils peuvent proposer une recette en indiquant un nom et un e-mail privé. Toute proposition anonyme arrive en modération.

Les membres disposent de brouillons privés, d’un carnet de favoris et du suivi de leurs propositions. Les favoris, avis et opérations de gestion nécessitent un compte dont l’e-mail a été vérifié. En local, `MAIL_MAILER=log` écrit les courriels de confirmation dans les journaux Laravel ; un transport SMTP doit être configuré pour envoyer réellement des e-mails.

Les administrateurs relisent les propositions, publient les recettes, demandent des corrections, sélectionnent les recettes mises en avant et gèrent catégories et rôles. Un administrateur ne peut pas retirer son propre rôle. Les catégories contenant des recettes, y compris archivées, ne peuvent pas être supprimées.

Une modification d’une recette publiée reste privée dans `proposed_changes` jusqu’à validation. La version publique et son adresse restent stables. Une demande de corrections conserve le travail de l’auteur. L’archivage utilise les suppressions logiques Laravel et conserve les données.

Les fiches comprennent les ingrédients avec quantités adaptables, les cases à cocher, un mode cuisine étape par étape, une minuterie, l’impression et le partage du lien. Les membres peuvent publier ou mettre à jour un avis par recette. L’auteur ou un administrateur peut répondre. L’auteur ne peut pas attribuer une note à sa propre recette.

Les images envoyées sont limitées à 5 Mo et converties en WebP, avec une dimension maximale de sortie de 1600 px. Les SVG ne sont pas acceptés dans les téléversements. Les propositions disposent d’une limitation de fréquence et d’un champ piège anti-spam.

## Les drives

Depuis la liste de courses d'un planning complet, **Comparer mes drives** ouvre une première intégration expérimentale pour E.Leclerc, Carrefour et Intermarché, sans Jow. La connexion passe par une extension Chrome/Edge et les sessions ouvertes sur les drives ; les mots de passe marchands ne sont pas stockés sur le site. Les produits et quantités sont sélectionnés avant une confirmation du transfert dans l'extension. Les connecteurs n'ont pas encore été validés sur des comptes réels. Installation, limites et validation : [docs/DRIVES.md](docs/DRIVES.md).

## Référencement

Le SSR est configuré via le plugin Inertia. En production, maintenir le processus Node SSR actif sous supervision (systemd ou Supervisor). Son écoute est limitée à `127.0.0.1`.

Les pages publiques disposent de titres, descriptions, URL canoniques et aperçus Open Graph. Les recettes émettent un JSON-LD Recipe ; les notes agrégées apparaissent seulement après de vrais avis. `/sitemap.xml` contient uniquement les recettes publiées.

## Vérification

La suite utilise PostgreSQL, dans une base dédiée **`idee_mijote_tests_20261014`**. `tests/TestCase.php` refuse une autre connexion ou base, avant les migrations des tests. Elle ne doit jamais utiliser `idee`.

```text
npm run check
npm run types:check
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php vendor/phpunit/phpunit/phpunit
npm run build:ssr
```

Les nouveaux tests couvrent l’accès public, la confidentialité, les propositions anonymes et leur validation, les images WebP, les permissions entre auteurs, la modération, les révisions privées, les favoris, les avis, les recherches PostgreSQL et l’archivage.

## Pour un VPS

Adresse retenue : **https://recettes.cuistot-club.fr**. La procédure complète est dans [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md), avec la configuration JSON à importer dans ton générateur, `.env.production.example`, le service Inertia SSR et la commande `php artisan app:create-admin adresse@example.fr`. Le catalogue s'importe avec `php artisan db:seed --force` : 110 recettes et 10 catégories, sans doublons ni écrasement des modifications ou archives.

Utiliser Nginx, PHP-FPM, PostgreSQL et Node pour le SSR. Compiler les assets, lancer les migrations sans `migrate:fresh`, configurer le stockage, un transport de courriels, les tâches en arrière-plan et des sauvegardes externes des données et des images. Mettre `APP_ENV=production`, `APP_DEBUG=false`, l’URL publique et les secrets dans l’environnement du serveur. Compléter les coordonnées de l’éditeur et les durées de conservation dans la page de confidentialité avant ouverture publique.

Les composants et les photographies de présentation sont documentés dans `ASSETS.md`.
"# recettes"
