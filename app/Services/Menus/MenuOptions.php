<?php

namespace App\Services\Menus;

use Illuminate\Support\Str;

class MenuOptions
{
    public const COURSES = ['starter' => 'Entrée', 'main' => 'Plat', 'dessert' => 'Dessert'];

    public const OCCASIONS = [
        'everyday' => 'Repas du quotidien',
        'family' => 'Repas familial',
        'christmas' => 'Noël',
        'new_year' => 'Réveillon du Nouvel An',
        'easter' => 'Pâques',
        'birthday' => 'Anniversaire en famille',
        'adult_birthday' => 'Anniversaire adulte',
        'child_birthday' => 'Anniversaire enfant',
        'friends' => 'Repas entre amis',
        'romantic' => 'Dîner en amoureux',
        'brunch' => 'Brunch',
        'buffet' => 'Buffet à partager',
        'barbecue' => 'Barbecue',
        'picnic' => 'Pique-nique',
    ];

    private const GUIDANCE = [
        'family' => 'Repas familial convivial : vrais plats à partager (gratin, lasagnes, rôti, poulet au four, blanquette ou mijoté de légumes secs selon saison et régime), accompagnements inclus, saveurs accessibles. Alterner avec des préparations plus légères ; ne pas proposer uniquement bols de riz et légumes. Desserts familiaux possibles dans les limites du quotidien.',
        'christmas' => 'Véritable menu de Noël : entrée travaillée et présentation de fête, plat de réception (volaille rôtie, poisson travaillé ou alternative végétale festive), accompagnements soignés. Si dessert sélectionné : bûche, entremets, vacherin, pavlova ou autre dessert de célébration construit. De simples fruits rôtis, une compote ou un laitage ne suffisent pas. Un potage ordinaire ou un sauté du quotidien ne devient pas festif en ajoutant Noël au titre. Adapter les préparations au temps et à la difficulté ; ne jamais imposer foie gras, fruits de mer, viande, alcool ou allergènes.',
        'new_year' => 'Réveillon du Nouvel An : préparations de réception soignées, entrée raffinée, plat travaillé et accompagnements, dessert de célébration (entremets, pavlova, charlotte, etc.) si sélectionné. Ni simple soupe ni fruits rôtis présentés comme un repas de fête. Préparation à l’avance possible dans le temps annoncé ; aucune obligation de champagne, fruits de mer ou autre ingrédient interdit.',
        'easter' => 'Pâques : repas familial de réception, rôti ou alternative végétale travaillée et légumes de printemps si la date le permet. Dessert construit de fête (nid de Pâques, entremets ou gâteau au chocolat) si sélectionné ; pas seulement un fruit ou un yaourt. Adapter aux régimes sans imposer agneau, œufs ou chocolat allergène.',
        'birthday' => 'Anniversaire en famille : plat convivial de réception à partager, présentation de célébration ; si dessert sélectionné, gâteau ou dessert de fête construit et partageable, jamais un simple fruit ou yaourt. Adapter aux allergies et au temps disponible.',
        'adult_birthday' => 'Anniversaire adulte : entrée et plat de réception, portions et présentation adaptées aux convives. Si dessert sélectionné, gâteau, entremets, charlotte ou autre dessert de célébration partageable ; pas seulement une compote ou des fruits rôtis. Aucune obligation d’alcool.',
        'child_birthday' => 'Anniversaire enfant, saveurs accessibles, présentation ludique et portions adaptées à l’âge indiqué. Sans alcool, même cuit. Si un dessert est demandé, proposer un gâteau ou dessert de fête compatible avec les exclusions.',
        'friends' => 'Repas entre amis : plats conviviaux à partager selon la saison (curry, lasagnes, mijoté, grillades, etc.), préparations anticipables pour profiter des invités. Pas uniquement des assiettes individuelles de régime ; respecter néanmoins les restrictions et les accompagnements complets.',
        'romantic' => 'Dîner en amoureux : présentation à l’assiette soignée, saveurs travaillées et portions adaptées, préparations réalisables sans passer toute la soirée en cuisine. Éviter le menu ordinaire simplement renommé romantique ; aucune obligation d’alcool ni d’ingrédient luxueux.',
        'brunch' => 'Véritable brunch : si entrée sélectionnée, petite préparation de légumes ; pour le plat, ensemble salé de brunch complet (œufs ou alternative, pain ou galette et légumes), plutôt qu’un ragoût de dîner. Note sucrée de brunch si dessert sélectionné. Inclure toutes les préparations et leurs quantités dans les rôles demandés ; ne pas ajouter de rôles.',
        'buffet' => 'Buffet à partager : petites portions ou plats faciles à servir sans dressage minute (terrine végétale, tarte salée accompagnée, salade complète, pièces à partager). Éviter steaks à cuire individuellement au dernier moment et préparations fragiles impossibles à servir en buffet. Détail des composants et accompagnements dans chaque recette.',
        'barbecue' => 'Véritable repas au barbecue : grillades, brochettes ou légumes farcis à griller, avec alternative végétale si nécessaire ; légumes et féculents inclus dans le plat. Pas de bouilli ni de mijoté rebaptisé barbecue. La durée inclut préparation du feu et cuisson si nécessaires.',
        'picnic' => 'Véritable pique-nique : préparations transportables, portionnables et faciles à manger (salades complètes, tartes salées, wraps ou galettes adaptés au régime) ; expliquer le transport au frais. Éviter soupes chaudes, plats en sauce à servir à l’assiette et cuisson de dernière minute sur place.',
    ];

    /** @param array<string,mixed> $criteria
     * @return list<string>
     */
    public static function courses(array $criteria): array
    {
        return array_values(array_intersect(array_keys(self::COURSES), $criteria['courses'] ?? array_keys(self::COURSES)));
    }

    /** @param array<string,mixed> $criteria */
    public static function occasionLabel(array $criteria): string
    {
        $label = self::OCCASIONS[$criteria['occasion'] ?? 'everyday'] ?? self::OCCASIONS['everyday'];
        if (($criteria['occasion'] ?? '') === 'child_birthday' && isset($criteria['child_age'])) {
            $label .= ' ('.$criteria['child_age'].' ans)';
        }

        return $label;
    }

    /** @param array<string,mixed> $criteria */
    public static function guidance(array $criteria): string
    {
        $guidance = self::GUIDANCE[$criteria['occasion'] ?? 'everyday'] ?? '';
        if (($criteria['occasion'] ?? '') === 'child_birthday' && isset($criteria['child_age'])) {
            $guidance .= ' Âge de l’enfant : '.$criteria['child_age'].' ans.';
        }

        return $guidance;
    }

    /** @param array<string,mixed> $criteria */
    public static function preferences(array $criteria): string
    {
        return trim(self::guidance($criteria)."\n".($criteria['preferences'] ?? ''));
    }

    /** @param array<string,mixed> $criteria */
    public static function celebration(array $criteria): bool
    {
        return in_array($criteria['occasion'] ?? '', ['christmas', 'new_year', 'easter', 'birthday', 'adult_birthday', 'child_birthday'], true);
    }

    /**
     * A minimum celebration dessert format, in addition to semantic occasion matching.
     * A festive adjective alone cannot turn everyday roasted fruit into a celebration dessert.
     *
     * @param  array<string,mixed>  $criteria
     */
    public static function suitableDessert(PlanningRecipe $recipe, array $criteria): bool
    {
        if (! self::celebration($criteria) || $recipe->course_type !== 'dessert') {
            return true;
        }

        $formats = 'buche|entremets?|vacherins?|pavlovas?|charlottes?|nougats? glace|parfaits? glace|domes?|pieces? montees?|omelettes? norvegiennes?|foret noire|trianons?|royal au chocolat|couronnes?|chalets?|maisons? en pain d.epices';
        $formats .= '|gateaux?|number cakes?|layer cakes?|cupcakes?|nids?|tartes?|tiramisus?|profiteroles?|fondants?|cheesecakes?|fraisiers?|framboisiers?';

        return preg_match('/\b(?:'.$formats.')\b/', Str::lower(Str::ascii($recipe->title))) === 1;
    }
}
