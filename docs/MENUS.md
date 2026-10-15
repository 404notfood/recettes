# Menus Cuistot Club

Cette fonctionnalité appartient au projet `idee`. Elle utilise les recettes JSON, leurs adresses `/recettes/{slug}`, l'authentification et la modération existantes.

## Composer et cuisiner

- Comptes connectés et vérifiés, plannings privés à leur propriétaire.
- 1, 7 ou 14 jours ; midi et/ou soir ; entrée, plat et dessert ; portions du foyer.
- Difficulté maximale, ingrédients exclus, option végétarienne, créneaux rapides par repas.
- Repères de composition : légumes, féculents et protéines dans le repas salé, variété des protéines, desserts gourmands occasionnels. Ces règles éditoriales ne constituent pas un calcul nutritionnel individuel.
- Le catalogue publié et les recettes automatiques en attente du propriétaire passent en premier. Les groupes sont repérés prudemment à partir des ingrédients déclarés ; les petites quantités d'assaisonnement ne suffisent pas. Les informations ambiguës peuvent être complétées par l'IA.
- Les recettes manquantes sont ajoutées avec ingrédients et étapes détaillées. Elles sont consultables immédiatement par leur propriétaire et restent privées jusqu'à modération.
- Une recette est limitée à deux utilisations sur 1/7 jours et quatre sur 14 jours. Les repas verrouillés sont conservés ; chaque recette d'un repas libre peut être remplacée.
- Les fiches et leurs étapes sont conservées dans les plannings. Modifier une recette ne modifie pas les proportions d'un planning déjà enregistré. Les métadonnées de sélection sont recalculées après modification de ses ingrédients ou de ses durées.

## PDF et impression

Le PDF propose menus seuls, courses seules ou les deux. Une semaine tient sur une page A4 paysage ; les titres très longs peuvent être abrégés si nécessaire. Les liens des recettes sont conservés dans le PDF. Les courses suivent en portrait, avec catégorie répétée lors d'une continuation, cases à cocher et quantités additionnées par nom et unité. Une quantité déclarée sans unité reste une quantité connue ; aucune unité n'est inventée.

**Imprimer des recettes** ouvre un choix de fiches : toutes ou une sélection. Les répétitions identiques sont retirées. Le résultat contient les quantités adaptées au foyer et les étapes, sans image. L'utilisateur peut imprimer l'aperçu ou télécharger le PDF des fiches choisies. Les sélections sont vérifiées côté serveur dans le planning du propriétaire.

La police DM Serif Display est conservée avec sa licence OFL dans `public/fonts/menu`. Les PDF sont produits localement par Dompdf et FPDI, sans service externe.

## Configuration et exécution

Configurer `OPENAI_API_KEY` dans l'environnement privé du serveur et éventuellement `OPENAI_MODEL`. La clé ne passe jamais dans les pages ni dans une variable Vite. Le choix de réutiliser une clé existante est celui retenu pour ce projet ; aucun secret n'a été copié depuis les projets voisins.

```sh
composer install
php artisan migrate
npm run build:ssr
php artisan config:clear
php artisan queue:restart
composer menus:work
```

`npm run build` et `npm run build:ssr` produisent tous deux les bundles navigateur et SSR et vérifient que les pages des menus sont incluses. Après recompilation, redémarrer le processus SSR selon son mode de supervision. `npm run check:ssr` vérifie le rendu des trois pages dans le processus actif.

Le worker dédié écoute la connexion `menus`, la file `menus`, avec une seule tentative, un timeout de 3600 secondes et une réservation de 3700 secondes. En production, le superviser séparément du worker par défaut. Sans clé, seules les combinaisons réalisables avec les recettes existantes peuvent aboutir ; le planning explique quand des recettes supplémentaires sont nécessaires.

Le modèle `deploy/menus-worker.service.template` permet de créer ce service sur le VPS, en remplaçant `__PROJECT__` par `cuistot-recettes` et `__PHP__` par le chemin du PHP installé. L'installer sous un nom distinct, par exemple `cuistot-recettes-menus.service`. Aucun déploiement du VPS n'est effectué par l'intégration locale.

Les appels OpenAI utilisent Responses avec un schéma strict et n'ont pas de relance automatique. Les recettes et repas sont enregistrés ensemble après validation complète. Un échec de régénération conserve les anciens repas. Les appels déjà effectués peuvent être facturés par le fournisseur.

## Vérifications

Les tests utilisent exclusivement la base PostgreSQL dédiée du projet, avec HTTP simulé. Ils couvrent 1/7/14 jours, les données JSON du catalogue, l'import privé, les liens par slug, les copies après archivage, la sélection d'impression, les quantités, les orientations des PDF et les droits d'accès. L'accès public aux recettes non publiées reste bloqué, à l'exception de l'aperçu personnel des recettes créées pour les menus.
