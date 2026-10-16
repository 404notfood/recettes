# Déployer Cuistot Club sur le VPS

Adresse retenue : **https://recettes.cuistot-club.fr**.
Nom proposé pour le dépôt GitHub et le compte Linux : **cuistot-recettes**.
Le nom du dépôt ne détermine pas le sous-domaine. Le projet local reste sur https://idee.test.

## 1. Préparer GitHub

Créer un dépôt `cuistot-recettes`, puis y envoyer les sources avec `main` comme branche principale. L'URL exacte du dépôt sera à renseigner dans le générateur. Aucun dépôt distant n'a été créé par cette préparation.

Inclure `composer.lock`, `package-lock.json`, `database/seeders/`, `resources/data/recipe-photo-credits.json`, `public/images/`, `deploy/` et `.env.production.example`. Les 110 photos sont déjà dans le projet : l'installation ne contacte aucun fournisseur d'images. Garder les crédits des photographies et consulter `ASSETS.md` pour les licences.

Les fichiers `.env`, `.env.testing`, les mots de passe, les journaux, les exports SQL, `storage/app/private`, les uploads dans `storage/app/public`, `vendor` et `node_modules` sont ignorés. Contrôler `git status` avant le premier commit. Le workflow GitHub utilise sa propre base PostgreSQL de test et ne se connecte pas au VPS.

## 2. Configurer le générateur

Ouvrir https://404notfood.fr/generateur/project.html et importer **`deploy/generator.config.example.json`** avec le bouton d'import JSON. Configuration préparée pour le générateur v1.3.0, à partir de son code consulté le 14 octobre 2026.

Compléter les champs avant de télécharger le ZIP :

| Champ                       | Valeur                                                                                 |
| --------------------------- | -------------------------------------------------------------------------------------- |
| Nom du projet               | `cuistot-recettes`                                                                     |
| Domaine principal           | `recettes.cuistot-club.fr`                                                             |
| Source                      | Git ; remplacer `https://github.com/TON-COMPTE/cuistot-recettes.git` par le vrai dépôt |
| Branche                     | `main`                                                                                 |
| Stack                       | Laravel + React                                                                        |
| PHP / Node                  | PHP 8.4 / Node 24, à confirmer installés par le bootstrap                              |
| Gestionnaire de paquets     | **npm**, explicitement ; le starter contient aussi un lockfile pnpm                    |
| Frontend                    | **Build uniquement**                                                                   |
| Base                        | PostgreSQL                                                                             |
| Environnement d'exemple     | `.env.production.example` ; environnement réel `.env`                                  |
| Arborescence                | Simple : `/home/cuistot-recettes/app`                                                  |
| Migrations                  | Activées                                                                               |
| Worker                      | Un worker, queue `database`                                                            |
| Redis / Horizon / scheduler | Désactivés, aucun besoin actuel                                                        |
| HTTPS                       | Activé ; renseigner ton vrai e-mail Let’s Encrypt                                      |

Le mode « Node permanent » du générateur attend un serveur React spécifique sous `/frontend/`. Notre rendu Inertia utilise le service SSR décrit plus bas, avec une écoute privée sur `127.0.0.1:13714`.

Deux hooks sont déjà configurés :

- `beforeMigration` : `npm run build:ssr-only`. Dans cette version du générateur, ce hook s'exécute après la compilation du client et les migrations Laravel ; il compile le bundle Inertia.
- `afterStart` : `php artisan db:seed --force`. Les seeders ajoutent les recettes manquantes et préservent les modifications et archives.

Créer l'enregistrement DNS A vers l'IPv4 du VPS. N'ajouter AAAA que si l'IPv6 fonctionne sur ce serveur. Préparer séparément l'accès SSH au dépôt si GitHub est privé, sans intégrer de token dans l'URL.

## 3. Installer l'application

Installer d'abord le bootstrap prévu par ton générateur sur Debian 13. Les extensions PHP doivent inclure `pdo_pgsql`, `gd`, `fileinfo`, `mbstring` et les extensions requises par Laravel. GD doit prendre en charge WebP pour les photos envoyées par les membres.

Télécharger le ZIP après avoir corrigé l'URL GitHub et l'e-mail SSL, le transférer dans un dossier d'administration du VPS, le décompresser et lancer depuis ce dossier :

```bash
sudo bash ./install.sh --preflight
sudo bash ./install.sh
```

Le générateur prépare le compte Linux, Nginx/SSL, la base et son utilisateur PostgreSQL, puis récupère le dépôt et installe les dépendances. Il génère le mot de passe SQL sur le VPS, renseigne `.env`, génère `APP_KEY`, compile le client, lance les migrations, compile le SSR et importe les recettes via les hooks. `APP_KEY` doit être conservée lors des mises à jour.

La commande `--provision` est également disponible pour préparer seulement l'hébergement et la base. Relancer sans ce flag pour installer les sources. Consulter le README du ZIP et `/var/log/vps-projects/cuistot-recettes.log` si une étape s'arrête.

Pour lancer les migrations et le catalogue manuellement si les hooks ont été désactivés :

```bash
sudo -u cuistot-recettes /bin/bash
cd /home/cuistot-recettes/app
/usr/bin/php8.4 artisan migrate --force
/usr/bin/php8.4 artisan db:seed --force
exit
```

Le catalogue contient **110 recettes, 10 catégories et au moins 10 recettes par catégorie**. L'import complet s'effectue dans une transaction. Aucun administrateur local ou mot de passe prédéfini n'est créé en production. Le compte rédaction possède un mot de passe aléatoire et aucun droit d'administration ; son adresse se configure par `RECIPE_EDITOR_EMAIL`. Choisir cette adresse avant le premier import et la conserver ensuite.

Ne jamais utiliser `migrate:fresh`, `db:wipe` ou les tests sur la base de production. Les seeders fournissent le catalogue ; les recettes, membres, favoris et avis ajoutés ensuite seront à sauvegarder depuis PostgreSQL.

## 4. Démarrer le rendu serveur Inertia

À la fin de l'installation, le générateur affiche le dossier Node sélectionné sous `/opt/nvm/versions/node/`. Identifier son chemin exact, par exemple avec :

```bash
find /opt/nvm/versions/node -path '*/v24.*/bin/node' -type f
```

Utiliser **le même Node 24 que le générateur**, en remplaçant `v24.X.Y` :

```bash
cd /home/cuistot-recettes/app
sudo bash deploy/install-ssr-service.sh /opt/nvm/versions/node/v24.X.Y/bin/node
sudo systemctl status cuistot-recettes-ssr --no-pager
```

Le script installe `cuistot-recettes-ssr.service`, le démarre et contrôle sa disponibilité. Les journaux sont accessibles avec `sudo journalctl -u cuistot-recettes-ssr -n 50`. Il refuse un port 13714 déjà occupé lors de la première installation. Ne pas exposer ce port dans Nginx ou le pare-feu.

Le compte Linux proposé et l'arborescence simple sont utilisés dans les exemples. Si tu changes le nom dans le générateur, adapter les chemins et passer le nouveau nom comme deuxième argument du script. Le script fourni attend PHP 8.4 et l'arborescence `/home/NOM/app`.

## 5. Créer ton administrateur et configurer les e-mails

Sous le compte Linux du projet, remplacer l'adresse d'exemple par la tienne :

```bash
sudo -u cuistot-recettes /bin/bash
cd /home/cuistot-recettes/app
/usr/bin/php8.4 artisan app:create-admin ton-adresse@example.fr --name="Ton prénom"
exit
```

Le mot de passe est saisi deux fois, masqué, avec au moins 12 caractères. La commande crée un nouveau compte administrateur vérifié. Elle refuse une adresse déjà inscrite et ne modifie aucun compte existant. Se connecter ensuite à `/login`, puis ouvrir `/administration`. Activer la double authentification dans les paramètres du compte.

L'exemple de production utilise encore `MAIL_MAILER=log` : **aucun e-mail n'est envoyé dans ce mode**. Avant d'ouvrir les inscriptions, compléter `.env` sur le VPS avec un vrai transport SMTP : `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` selon le fournisseur, `MAIL_FROM_ADDRESS` et `MAIL_FROM_NAME`. Les informations d'authentification restent uniquement sur le serveur. Configurer les DNS d'envoi demandés par ton fournisseur.

Après modification de `.env` :

```bash
sudo -u cuistot-recettes /bin/bash
cd /home/cuistot-recettes/app
/usr/bin/php8.4 artisan config:cache
/usr/bin/php8.4 artisan queue:restart
exit
sudo systemctl restart cuistot-recettes-ssr
```

## 6. Contrôler puis mettre à jour

Pour les correctifs des menus, du PDF et des drives du 16 octobre 2026, déployer ensemble le code, les vues, `composer.lock`, les deux builds et le ZIP de l'extension. Sur Hestia, sous le compte `cuistot`, après mise à jour des sources :

```bash
cd /home/cuistot/web/recettes.cuistot-club.fr/public_html/recettes
composer install --no-dev --prefer-dist --optimize-autoloader
php scripts/check-runtime.php
npm ci
npm run build:ssr
php artisan optimize:clear
php artisan queue:restart
```

Redémarrer ensuite le service SSR existant et contrôler `npm run check:ssr`. Si le SSR est lancé manuellement, utiliser `php artisan inertia:stop-ssr` puis `php artisan inertia:start-ssr` ; ne pas ajouter un second processus sur le même port. Le worker des menus doit aussi être supervisé pour repartir après `queue:restart`.

L'erreur `Class "Dompdf\\Options" not found` est corrigée par l'installation Composer ci-dessus. La nouvelle impression HTML des menus et des courses fonctionne aussi sans moteur PDF. Pour l'ouverture groupée des trois drives, mettre à jour puis recharger l'extension **0.1.2** depuis le nouveau ZIP, puis utiliser **Relier le site de cet onglet** depuis le site (la page se recharge automatiquement). L'extension dispose aussi de son propre bouton d'ouverture. Les corrections des courses et l'accès privé **Mes recettes de menus** s'appliquent aux plannings existants ; les règles de variété s'appliquent lors d'une nouvelle préparation ou d'une régénération des repas libres. Les créations restent en validation avant publication.

`npm run build` compile maintenant les assets du navigateur **et** le bundle SSR, puis vérifie la présence de l'accueil, des quatre pages des menus et de leurs fichiers compilés. `npm run build:ssr` reste un alias de cette compilation complète. Ne pas déployer uniquement `public/build` : le dossier `bootstrap/ssr` appartient à la même version.

Une erreur `Page not found: menus/show` indique que le processus SSR ne dispose pas de la page des menus. Compiler les deux bundles puis redémarrer le SSR : le processus Node conserve les modules chargés avant la recompilation.

Sur l'installation Hestia utilisant le chemin fourni dans les journaux, depuis le compte `cuistot` :

```bash
cd /home/cuistot/web/recettes.cuistot-club.fr/public_html/recettes
npm run build:ssr
php artisan optimize:clear
php artisan inertia:stop-ssr
php artisan inertia:start-ssr
```

La dernière commande démarre le SSR au premier plan. Si un service systemd, Supervisor ou autre gestionnaire le supervise déjà, redémarrer ce service après compilation à la place du démarrage manuel ; ne pas lancer deux processus sur le même port. Le nom du service dépend de l'installation.

Après installation des scripts de vérification de cette version, `npm run check:ssr` contrôle aussi le processus réellement actif : il demande le rendu de `welcome`, `menus/index`, `menus/show`, `menus/recipe` et `menus/drives` avec des données fictives, sans accès à la base ni à OpenAI.

Pour `Page not found: home` sur `/`, vérifier aussi les sources PHP déployées : l'accueil de cette version est le composant **`welcome`**, dans `resources/js/pages/welcome.tsx`. La méthode `RecipeController::home()` doit appeler `Inertia::render('welcome', ...)`. Le nom de route `home` peut être conservé ; il ne désigne pas le composant React. Recompiler uniquement le JavaScript ne corrige pas un contrôleur qui demande encore `home`.

```bash
grep -n 'Inertia::render' app/Http/Controllers/RecipeController.php
ls resources/js/pages/welcome.tsx
```

Mettre à jour les sources PHP et JavaScript de la même version, puis compiler et redémarrer le SSR comme ci-dessus. Si le fichier PHP demande bien `welcome` mais que de nouvelles erreurs mentionnent toujours `home`, vérifier le dossier réellement servi par le virtual host et recharger le service PHP-FPM concerné pour renouveler son OPcache. Les scripts de vérification incluent désormais l'accueil `welcome` dans les bundles et dans le rendu actif.

Une erreur de génération des menus est distincte d'une erreur de rendu SSR. Le worker doit être redémarré après la mise à jour du code :

```bash
php artisan queue:restart
```

Le worker dédié doit être supervisé et écouter `queue:work menus --queue=menus --tries=1 --timeout=3600`. Pour un lancement manuel, cette commande reste au premier plan. Après une nouvelle tentative de génération, le diagnostic filtré est disponible avec :

```bash
grep 'menus.generation_failed' storage/logs/laravel.log | tail -n 5
```

Ce diagnostic contient l'identifiant du planning, la classe de l'erreur, son fichier et sa ligne. Pour une réponse de recettes invalide, `diagnostics.operation` distingue `enrich` et `generate`, et `validation_rules` indique les champs et règles refusés. Pour une erreur HTTP, le statut et un code API reconnu permettent de distinguer notamment quota et limite de débit. Les messages bruts des exceptions, réponses du fournisseur, clés et préférences privées ne sont pas journalisés. Les anciennes erreurs déjà interceptées ne peuvent pas être reconstituées ; il faut refaire un essai après mise à jour et redémarrage du worker.

Vérifier `/`, `/recettes`, une fiche de recette avec sa photo et son crédit, `/sitemap.xml`, une proposition anonyme et un e-mail de vérification reçu dans une vraie boîte. Compléter les informations de l'éditeur dans la page de confidentialité avant l'ouverture publique.

Contrôle SSR sous le compte projet :

```bash
sudo -u cuistot-recettes /bin/bash
cd /home/cuistot-recettes/app
/usr/bin/php8.4 artisan inertia:check-ssr
exit
```

Pour une mise à jour, sauvegarder PostgreSQL, `.env` et `storage/app/public` sur un support externe. Mettre explicitement le dépôt à jour sous le compte projet (`git pull --ff-only` sur `main`), puis relancer le même `install.sh` depuis son dossier d'administration. Le générateur ne fait pas de `git pull` automatique et ne restaure pas les migrations ou les données applicatives en cas d'échec.

Après chaque compilation/déploiement réussi :

```bash
sudo systemctl restart cuistot-recettes-ssr
sudo systemctl restart cuistot-recettes-queue@1
```

Les uploads doivent être conservés entre déploiements, avec `public/storage` pointant vers `storage/app/public`. Le workflow GitHub est préparé mais son exécution distante et l'installation VPS restent à vérifier une fois le dépôt et le serveur disponibles.
