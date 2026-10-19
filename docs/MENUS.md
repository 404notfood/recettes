# Menus Cuistot Club

Cette fonctionnalité appartient au projet `idee`. Elle utilise les recettes JSON, leurs adresses `/recettes/{slug}`, l'authentification et la modération existantes.

## Composer et cuisiner

- Comptes connectés et vérifiés, plannings privés à leur propriétaire.
- 1, 7 ou 14 jours ; midi et/ou soir ; entrée, plat et dessert ; portions du foyer.
- Difficulté maximale, ingrédients exclus, option végétarienne, créneaux rapides par repas.
- Repères de composition : légumes, féculents et protéines dans le repas salé, variété des protéines, desserts gourmands occasionnels. Ces règles éditoriales ne constituent pas un calcul nutritionnel individuel.
- Le catalogue publié et les recettes automatiques en attente du propriétaire passent en premier. Les groupes sont repérés prudemment à partir des ingrédients déclarés ; les petites quantités d'assaisonnement ne suffisent pas. Les informations ambiguës peuvent être complétées par l'IA.
- Les recettes manquantes sont ajoutées avec ingrédients et étapes détaillées. Elles sont consultables immédiatement par leur propriétaire et restent privées jusqu'à modération.
- **Mes recettes de menus**, accessible depuis le planning, le carnet et `/recettes?source=menus`, retrouve aussi les créations des anciens menus. Les filtres et la recherche s'appliquent à cette bibliothèque personnelle. Les fiches en validation sont signalées comme privées ; les autres membres et les visiteurs ne peuvent pas les consulter. La validation habituelle par l'administration les ajoute au catalogue public.
- Les deux jours voisins sont comparés dans chaque rôle : pas de variante du même plat ni du même ingrédient dominant pendant cette période. Cela rapproche notamment croziflette et tartiflette, et repère les poireaux en accompagnement même si la fiche indique le poulet comme ingrédient principal. Les ingrédients dominants reconnus des plats principaux sont également limités à deux occurrences sur quatorze repas hebdomadaires, une sur sept repas. Les légumes dominants de l'entrée et du plat ne sont pas doublonnés dans un même repas. Les assaisonnements en petites quantités ne bloquent pas la sélection. Ces contrôles incluent les repas conservés avant et après le repas régénéré ; les choix verrouillés sont préservés. Une proposition IA répétitive peut être remplacée par deux nouvelles tentatives, avant tout enregistrement.
- Un plat principal ne revient pas deux fois dans une même semaine. Les familles de plats (couscous, pâtes, soupes, etc.) sont regroupées même si le titre ou la protéine change : une occurrence sur une semaine de sept repas, deux sur quatorze repas. En mode souple, les entrées et desserts restent limités à deux utilisations sur 1/7 jours et quatre sur 14 jours. Les repas verrouillés sont conservés ; chaque recette d'un repas libre peut être remplacée.
- Les envies en texte libre filtrent aussi les recettes du catalogue avant la sélection, par lots de quarante fiches maximum. Les nouvelles recettes sont vérifiées avec les mêmes envies avant tout enregistrement. Les contraintes de cuisine demandée, de temps et de variété peuvent nécessiter de nouvelles recettes même si le catalogue est fourni. Sans service IA configuré, une envie non vide déclenche un message explicite plutôt que d'être ignorée.
- Les fiches et leurs étapes sont conservées dans les plannings. Modifier une recette ne modifie pas les proportions d'un planning déjà enregistré. Les métadonnées de sélection sont recalculées après modification de ses ingrédients ou de ses durées.

## Saison, restrictions et menus plus complets

Le nouveau formulaire active par défaut les produits de saison et le mode **varié**. Les anciens plannings conservent leurs critères et leurs copies de recettes.

- Saison : calendrier éditorial de France métropolitaine appliqué aux ingrédients reconnus à la date de chaque repas, avec les périodes de conservation courantes. Les conserves, surgelés et produits transformés explicitement nommés sont possibles. Le calendrier ne certifie ni une origine locale ni tous les produits du monde. Référence : [Manger Bouger](https://www.mangerbouger.fr/manger-mieux/bien-manger-sans-se-ruiner/calendrier-de-saison).
- Mode varié : aucune recette répétée par bloc de sept jours, pour les trois rôles. Chaque plat apporte légumes, féculents et protéines en quantité significative, accompagnement compris. À partir de sept repas, deux plats de légumes secs ; poisson une fois sur 7 à 9 repas, deux fois à partir de 10 repas, sauf régime végétarien/végétalien ou allergie au poisson. Les autres règles de diversité existantes restent actives. Les plafonds de rotation s’adaptent aux familles de protéines autorisées, notamment en végétalien.
- Accompagnement : champ facultatif sur la recette, conservé dans les copies des menus, visible sur les fiches, impressions et PDF. Ses quantités, ingrédients et étapes font partie du plat ; les courses et les exclusions les comprennent.
- Choix cumulables : sans porc, sans alcool même cuit, sans bœuf/veau, sans viande avec poisson autorisé, végétalien, sans gluten et sans lactose. Le sans lactose utilise par prudence des alternatives végétales sans lait. Sans porc et sans alcool ne valent pas certification halal ou casher : les achats et la préparation sont à vérifier.
- Les [14 catégories d’allergènes à déclarer](https://www.service-public.gouv.fr/particuliers/vosdroits/F32192) ont des cases distinctes des ingrédients à éviter. `allergens=null` signifie non évalué ; `[]` signifie aucun identifié dans les ingrédients déclarés. Une recette non évaluée est écartée d’un menu excluant des allergènes, du gluten ou du lactose. Les dérivés connus sont contrôlés, même lorsqu’une déclaration est vide. Une composition inconnue doit rester non évaluée. La déclaration peut être renseignée dans l’éditeur et suit la modération des recettes publiées.
- La recherche publique et la bibliothèque personnelle proposent les mêmes choix, en conservant les filtres, les droits d’accès et la pagination existants. Le filtre de saison y utilise la date du jour.

Les filtres ne garantissent pas l’absence de traces ou de contamination croisée. Les étiquettes restent à vérifier. Les documents d’exemple ont servi à comprendre la structure des repas ; leur contenu n’est pas traité comme des instructions.

La migration `2026_10_19_120000_add_recipe_dietary_fields.php` ajoute deux colonnes de recettes. Sur le VPS, transférer les sources, exécuter `php artisan migrate --force`, recompiler les bundles navigateur **et SSR**, puis redémarrer le SSR et le worker selon leur supervision. Conserver la clé déjà présente dans le `.env` du VPS. Les tests simulent les réponses HTTP et utilisent la base PostgreSQL de test dédiée.

## PDF et impression

Le PDF propose menus seuls, courses seules ou les deux. Une semaine tient sur une page A4 paysage ; les titres très longs peuvent être abrégés si nécessaire. Les liens des recettes sont conservés dans le PDF. Les courses suivent en portrait, avec catégorie répétée lors d'une continuation, cases à cocher et quantités additionnées. Les variantes connues au singulier/pluriel sont regroupées, ainsi que kg/g et l/cl/ml. Les pièces sont explicitées pour les produits dénombrables connus comme œufs et bananes. Les quantités inconnues, poids et nombres de pièces restent séparés, tout comme les légumes secs et cuits. L'eau de cuisson est retirée.

**Imprimer menus et courses** ouvre une vue HTML privée qui permet aussi d'enregistrer un PDF avec le navigateur, sans dépendre de Dompdf. **Imprimer des recettes** présélectionne toutes les fiches ; l'utilisateur peut retirer celles qui ne l'intéressent pas.

**Imprimer des recettes** ouvre un choix de fiches : toutes ou une sélection. Les répétitions identiques sont retirées. Le résultat contient les quantités adaptées au foyer et les étapes, sans image. L'utilisateur peut imprimer l'aperçu ou télécharger le PDF des fiches choisies. Les sélections sont vérifiées côté serveur dans le planning du propriétaire.

La police DM Serif Display est conservée avec sa licence OFL dans `public/fonts/menu`. Les PDF sont produits localement par Dompdf et FPDI, sans service externe.

Si le journal indique `Class "Dompdf\\Options" not found`, les dépendances PHP du VPS sont incomplètes. Mettre à jour les sources et `composer.lock`, puis lancer `composer install --no-dev --prefer-dist --optimize-autoloader` et `php scripts/check-runtime.php` dans le dossier du site. Une copie de fichiers PHP ne remplace pas cette installation.

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

`npm run build` et `npm run build:ssr` produisent tous deux les bundles navigateur et SSR et vérifient que l'accueil et les quatre pages des menus sont inclus. Après recompilation, redémarrer le processus SSR selon son mode de supervision. `npm run check:ssr` vérifie leur rendu dans le processus actif.

Le worker dédié écoute la connexion `menus`, la file `menus`, avec une seule tentative, un timeout de 3600 secondes et une réservation de 3700 secondes. En production, le superviser séparément du worker par défaut. Sans clé, seules les combinaisons réalisables avec les recettes existantes peuvent aboutir ; le planning explique quand des recettes supplémentaires sont nécessaires.

Le modèle `deploy/menus-worker.service.template` permet de créer ce service sur le VPS, en remplaçant `__PROJECT__` par `cuistot-recettes` et `__PHP__` par le chemin du PHP installé. L'installer sous un nom distinct, par exemple `cuistot-recettes-menus.service`. Aucun déploiement du VPS n'est effectué par l'intégration locale.

Les appels OpenAI utilisent Responses avec un schéma strict et n'ont pas de relance automatique. Les recettes et repas sont enregistrés ensemble après validation complète. Un échec de régénération conserve les anciens repas. Les appels déjà effectués peuvent être facturés par le fournisseur.

Les étapes générées sont nettoyées avant validation : les chaînes vides ou composées seulement d'espaces sont retirées, les autres instructions gardent leur ordre et la liste est renumérotée. Une recette sans étape valable, contenant un type incorrect ou une instruction trop courte reste refusée. Le schéma et la consigne demandent explicitement des étapes non vides ; ce nettoyage n'effectue aucun appel API supplémentaire.

En cas d'échec, le journal Laravel contient une entrée `menus.generation_failed` avec l'identifiant du planning et un diagnostic filtré. Les réponses de recettes incomplètes sont signalées comme telles au visiteur ; les pannes imprévues affichent un message technique sans lui demander de modifier ses critères. Le journal conserve la classe, le fichier et la ligne de l'erreur, les noms des champs et règles de validation concernés, ou le statut HTTP et un code API reconnu. Il ne conserve ni exception brute, ni clé, ni réponse complète du fournisseur, ni préférences privées. Après déploiement de ce correctif, redémarrer le worker puis refaire un essai : les erreurs auparavant interceptées sans journal ne sont pas récupérables.

## Vérifications

Les tests utilisent exclusivement la base PostgreSQL dédiée du projet, avec HTTP simulé. Ils couvrent 1/7/14 jours, les données JSON du catalogue, l'import privé, les liens par slug, les copies après archivage, la sélection d'impression, les quantités, les orientations des PDF et les droits d'accès. L'accès public aux recettes non publiées reste bloqué, à l'exception de l'aperçu personnel des recettes créées pour les menus.
