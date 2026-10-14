# Ressources visuelles

Les photographies de présentation sont servies localement dans `public/images`. Elles sont des illustrations éditoriales. Les recettes et textes ont été rédigés pour ce projet.

| Fichier                        | Photographie source Unsplash                                 |
| ------------------------------ | ------------------------------------------------------------ |
| salad.webp                     | https://images.unsplash.com/photo-1512621776951-a57141f2eefd |
| pasta.webp                     | https://images.unsplash.com/photo-1551183053-bf91a1d81141    |
| soup.webp (assiette de saumon) | https://images.unsplash.com/photo-1547592180-85f173990554    |
| toast.webp                     | https://images.unsplash.com/photo-1484723091739-30a097e8f929 |
| pizza.webp                     | https://images.unsplash.com/photo-1565299624946-b28f40a0ae38 |
| cake.webp                      | https://images.unsplash.com/photo-1578985545062-69928b1d9587 |
| bread.webp                     | https://images.unsplash.com/photo-1509440159596-0249088772ff |
| bowl.webp                      | https://images.unsplash.com/photo-1490645935967-10de6ba17061 |

Le symbole Cuistot Club, le favicon et l’illustration de remplacement sont des SVG créés pour le projet.

Les 102 couvertures de la collection sont de vraies photographies obtenues sur Wikimedia Commons, dont les licences ont été vérifiées sur les fiches source. Elles sont servies localement dans `public/images/collection/*.webp`, sans requête à un service tiers. Les anciennes illustrations SVG ne sont plus utilisées par cette collection.

Le fichier `resources/data/recipe-photo-credits.json` associe à chaque photographie son auteur, son titre original, sa page source et sa licence. Ces crédits apparaissent sous la photo de la fiche publique. Les photographies restent sous leur licence d’origine, indépendamment du code du site. Les adaptations consistent en un redimensionnement, une conversion en WebP et le recadrage d’affichage ; les fiches les signalent par « image adaptée ». Il s’agit de photos d’illustration de plats et de variantes, pas de photographies réalisées par la rédaction pour ces recettes.

Les fiches de la collection sont rédigées pour Cuistot Club. Quelques points culinaires ont été vérifiés auprès de [Bretagne.com pour le kig ha farz](https://www.bretagne.com/la-bretagne/sa-gastronomie/ses-recettes/kig-ha-farz) et le [kouign-amann](https://www.bretagne.com/la-bretagne/sa-gastronomie/ses-recettes/kouign-amann), ainsi que de Just One Cookbook pour l’[oyakodon](https://www.justonecookbook.com/oyakodon/), la [soupe miso](https://www.justonecookbook.com/homemade-miso-soup/) et le [teriyaki](https://www.justonecookbook.com/chicken-teriyaki/). Le repère de cuisson à cœur du poulet est celui du [tableau USDA](https://www.fsis.usda.gov/food-safety/safe-food-handling-and-preparation/food-safety-basics/safe-temperature-chart).

- shadcn/ui : https://ui.shadcn.com
- Magic UI Blur Fade : https://magicui.design/docs/components/blur-fade
- Magic UI Marquee : https://magicui.design/docs/components/marquee
- Polices DM Sans et DM Serif Display : obtenues via Bunny Fonts pendant la compilation, puis servies localement.

Les animations sont désactivées quand le visiteur demande des mouvements réduits. Les recettes restent lisibles sans JavaScript lorsque le SSR est actif.

# Police des menus et impressions

DM Serif Display est utilisée pour conserver la charte Cuistot Club dans les PDF. Sa version TTF et sa licence SIL Open Font License sont fournies dans `public/fonts/menu`, depuis le [dépôt officiel Google Fonts](https://github.com/google/fonts/tree/main/ofl/dmserifdisplay).
