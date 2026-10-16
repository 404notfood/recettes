# Mes drives — version expérimentale

Périmètre : **E.Leclerc Drive, Carrefour Drive et Intermarché**, sans Jow. Le projet cible est `idee`.

La page privée `/menus/{id}/drives` part d'un planning complet appartenant au membre connecté et vérifié. Elle reprend les quantités de la liste existante, permet de retirer les ingrédients déjà disponibles, de rechercher des références dans chaque magasin ouvert, de choisir les produits et les paquets, puis de comparer les montants sélectionnés.

## Connexion et utilisation

1. Télécharger `/downloads/cuistot-drives.zip` depuis la page, décompresser, puis charger le dossier dans Chrome ou Edge via la page des extensions, mode développeur.
2. Depuis `https://idee.test` (ou le site public en HTTPS), ouvrir l'extension et cliquer sur **Relier le site de cet onglet**. Un seul origin exact est autorisé à la fois. Les autorisations de l'ancien site sont retirées au changement.
3. Cliquer sur **Ouvrir les 3 drives** : l'extension ouvre E.Leclerc, Carrefour et Intermarché en arrière-plan et réutilise les onglets déjà présents. Cette ouverture passe par `chrome.tabs.create`, sans boucle de fenêtres surgissantes. Se connecter et choisir les magasins directement sur leurs sites. Pour Leclerc, ouvrir la page du magasin sur son domaine `fdN-courses.leclercdrive.fr`. Les liens individuels restent disponibles sans l'extension.
4. Cliquer sur **Détecter mes drives** et choisir explicitement un onglet si plusieurs magasins d'une enseigne sont ouverts.
5. Cliquer sur **Rechercher et préremplir** pour chercher les ingrédients cochés dans les magasins détectés. Pour chaque ligne, la sélection compare le coût du nombre entier de paquets nécessaire, parmi les références compatibles trouvées. La recherche avance dans chaque magasin avec une progression et peut être arrêtée. Les formats inconnus, quantités manquantes, unités incompatibles ou formes alimentaires différentes restent à vérifier manuellement. Les produits Intermarché au poids ou au volume variable sont exclus de cette première version.
6. **Préparer le transfert** ouvre un récapitulatif appartenant à l'extension. Seule sa confirmation autorise les ajouts. Les produits existants sont conservés et les quantités sont augmentées. L'extension ne réserve aucun créneau et ne passe aucune commande.
7. Recharger le drive pour vérifier le panier final, les promotions et frais, puis commander sur le site de l'enseigne.

Les identifiants et cookies d'authentification restent dans le navigateur sur les sites marchands. Le serveur Laravel ne reçoit ni mots de passe, ni jetons, ni contenu des paniers marchands. Les références, prix et transferts sont conservés temporairement dans `chrome.storage.session` ; ils disparaissent à la fermeture du navigateur ou à la déconnexion de l'extension.

Le bouton d'ouverture groupée nécessite l'extension **0.1.1** : après mise à jour du ZIP, décompresser la nouvelle version, recharger son dossier dans la page des extensions puis recharger la page des menus. Le site ne peut pas mettre à jour lui-même une extension chargée manuellement.

## État et limites

**Il s'agit d'un prototype d'intégration non officiel, pas d'une compatibilité validée en conditions réelles. Aucun transfert réel n'a été exécuté lors du développement.** Les routes privées utilisées par les frontends peuvent changer et les protections des enseignes peuvent bloquer les requêtes. L'extension s'arrête sur ces erreurs et demande à l'utilisateur de vérifier le site ; elle ne contourne ni authentification, ni CAPTCHA.

- Les prix correspondent exclusivement au magasin choisi ; un prix d'un autre magasin n'est jamais utilisé en remplacement.
- Les résultats sont limités à cinq candidats par recherche, sans promesse de prix minimal ni de couverture exhaustive.
- Les totaux sont calculés en centimes sur les paquets sélectionnés. Un panier incomplet reste un sous-total et ne peut pas être transféré depuis la page. Une quantité connue insuffisante doit être augmentée ; un produit ou format incertain peut être confirmé explicitement par l'utilisateur après vérification. Les prix expirés sont exclus du classement.
- Promotions conditionnelles, fidélité, frais, consignes et produits à prix variable ne sont pas recalculés. Le montant final est celui du drive.
- Les propositions expirent après dix minutes ; magasin, référence, prix et disponibilité sont revérifiés avant chaque ajout. La quantité retournée par le marchand doit confirmer l'ajout.
- Les produits sélectionnés plusieurs fois sont regroupés avant transfert. Les écritures sont séquentielles, sans relance automatique. Un transfert interrompu peut être partiel ; vérifier le panier réel avant de recommencer.
- Le bouton de confirmation n'est accessible que dans la page de l'extension. Le site relié ne peut pas appeler l'opération d'écriture directement.
- Chrome/Edge sur ordinateur uniquement. Aucun support mobile ou Firefox à ce stade. Lidl et les appareils de cuisson sont hors de cette livraison.

## Comparer courses et essence

Renseigner le **prix du carburant en €/L** et la **distance totale aller-retour en km pour chaque magasin**. La consommation est fixée à **6 L/100 km**. Le coût du trajet est `distance aller-retour × 6 ÷ 100 × prix au litre`, arrondi au centime. Exemple : 20 km aller-retour à 1,80 €/L coûtent 2,16 €.

Le classement additionne le panier et l'essence uniquement si les lignes sont complètes et les données de trajet renseignées. Il indique le magasin le moins cher parmi ces paniers, sans prétendre connaître tout le catalogue marchand. Les cinq candidats trouvés, les magasins choisis et les formats disponibles limitent la comparaison. Les prix du carburant et distances sont saisis manuellement, restent dans la page et ne sont envoyés ni aux marchands ni à Laravel. Aucune géolocalisation ni consultation automatique des tarifs des stations n'est effectuée.

Le préremplissage ne lance aucun transfert. L'utilisateur peut changer les références, vérifier les produits et confirmer le transfert dans l'extension. Le prix et le stock sont toujours revérifiés au transfert. Cette fonction utilise l'extension 0.1.1 existante ; déployer les nouveaux builds navigateur et SSR, puis redémarrer le SSR.

## Références techniques

Implémentation indépendante fondée sur les observations documentées des interfaces marchandes :

- [Leclerc — contrat observé](https://github.com/skunkobi/mcp-leclerc-drive/blob/main/docs/api-capture.md) : HTML du catalogue, identifiant produit interne, quantité absolue dans `panier.aspx`.
- [Carrefour — contrat observé](https://github.com/machintrucbidule/cOURSesDrive/blob/main/docs/api-carrefour-contract.md) : magasin du panier actif, offres par magasin, quantité absolue en `PATCH /api/cart`, disponibilité revérifiée sur la fiche.
- [Intermarché — contrat observé](https://github.com/machintrucbidule/cOURSesDrive/blob/main/docs/api-intermarche-contract.md) : magasin sélectionné, identifiant produit interne, synchronisation du panier avec une variation de quantité.

Ces documents communautaires ne constituent pas un contrat officiel des enseignes. Avant toute ouverture publique, valider les trois parcours sur des comptes de test, vérifier les conditions d'intégration applicables et distribuer l'extension par un canal adapté.

## Développement et validation

```text
node --test tests/drives/*.test.mjs
php vendor/phpunit/phpunit/phpunit --filter DriveTest
npm run check
npm run types:check
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
npm run build
powershell -File scripts/Package-Drives.ps1
```

Les tests JavaScript simulent les réponses des marchands. Les tests Laravel utilisent exclusivement la base PostgreSQL dédiée imposée par `tests/TestCase.php`. Aucun compte marchand n'est nécessaire à ces tests, qui ne valident donc pas l'authentification réelle, les protections des sites ni leur schéma actuel.
