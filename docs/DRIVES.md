# Mes drives — version expérimentale

Périmètre : **E.Leclerc Drive, Carrefour Drive et Intermarché**, sans Jow. Le projet cible est `idee`.

La page privée `/menus/{id}/drives` part d'un planning complet appartenant au membre connecté et vérifié. Elle reprend les quantités de la liste existante, permet de retirer les ingrédients déjà disponibles, de rechercher des références dans chaque magasin ouvert, de choisir les produits et les paquets, puis de comparer les montants sélectionnés.

## Connexion et utilisation

1. Télécharger `/downloads/cuistot-drives.zip` depuis la page, décompresser, puis charger le dossier dans Chrome ou Edge via la page des extensions, mode développeur.
2. Depuis `https://idee.test` (ou le site public en HTTPS), ouvrir l'extension et cliquer sur **Relier le site de cet onglet**. Un seul origin exact est autorisé à la fois. Les autorisations de l'ancien site sont retirées au changement.
3. Ouvrir les drives, se connecter et choisir les magasins directement sur leurs sites. Pour Leclerc, ouvrir la page du magasin sur son domaine `fdN-courses.leclercdrive.fr`.
4. Cliquer sur **Détecter mes drives** et choisir explicitement un onglet si plusieurs magasins d'une enseigne sont ouverts.
5. Rechercher les ingrédients, sélectionner les références et indiquer le nombre entier de paquets couvrant le besoin. La conversion grammes → paquets n'est pas inventée : le conditionnement doit être vérifié par l'utilisateur. Les produits Intermarché au poids ou au volume variable sont exclus de cette première version.
6. **Préparer le transfert** ouvre un récapitulatif appartenant à l'extension. Seule sa confirmation autorise les ajouts. Les produits existants sont conservés et les quantités sont augmentées. L'extension ne réserve aucun créneau et ne passe aucune commande.
7. Recharger le drive pour vérifier le panier final, les promotions et frais, puis commander sur le site de l'enseigne.

Les identifiants et cookies d'authentification restent dans le navigateur sur les sites marchands. Le serveur Laravel ne reçoit ni mots de passe, ni jetons, ni contenu des paniers marchands. Les références, prix et transferts sont conservés temporairement dans `chrome.storage.session` ; ils disparaissent à la fermeture du navigateur ou à la déconnexion de l'extension.

## État et limites

**Il s'agit d'un prototype d'intégration non officiel, pas d'une compatibilité validée en conditions réelles. Aucun transfert réel n'a été exécuté lors du développement.** Les routes privées utilisées par les frontends peuvent changer et les protections des enseignes peuvent bloquer les requêtes. L'extension s'arrête sur ces erreurs et demande à l'utilisateur de vérifier le site ; elle ne contourne ni authentification, ni CAPTCHA.

- Les prix correspondent exclusivement au magasin choisi ; un prix d'un autre magasin n'est jamais utilisé en remplacement.
- Les résultats sont limités à cinq candidats par recherche, sans promesse de prix minimal ni de couverture exhaustive.
- Les totaux sont calculés en centimes sur les paquets sélectionnés. Un panier incomplet reste un sous-total et ne peut pas être transféré depuis la page. Aucun classement automatique ne compare des paniers de qualités ou quantités différentes.
- Promotions conditionnelles, fidélité, frais, consignes et produits à prix variable ne sont pas recalculés. Le montant final est celui du drive.
- Les propositions expirent après dix minutes ; magasin, référence, prix et disponibilité sont revérifiés avant chaque ajout. La quantité retournée par le marchand doit confirmer l'ajout.
- Les produits sélectionnés plusieurs fois sont regroupés avant transfert. Les écritures sont séquentielles, sans relance automatique. Un transfert interrompu peut être partiel ; vérifier le panier réel avant de recommencer.
- Le bouton de confirmation n'est accessible que dans la page de l'extension. Le site relié ne peut pas appeler l'opération d'écriture directement.
- Chrome/Edge sur ordinateur uniquement. Aucun support mobile ou Firefox à ce stade. Lidl et les appareils de cuisson sont hors de cette livraison.

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
