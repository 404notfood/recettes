<?php

namespace App\Services\Menus;

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
        'family' => 'Repas familial convivial, plats généreux à partager et saveurs accessibles.',
        'christmas' => 'Menu de Noël festif, présentation soignée et saveurs de fin d’année. Ne pas imposer un ingrédient contraire au régime.',
        'new_year' => 'Réveillon festif et raffiné du Nouvel An, recettes à préparer à l’avance lorsque possible.',
        'easter' => 'Menu de Pâques convivial et printanier si la date le permet, dessert festif si demandé.',
        'birthday' => 'Anniversaire en famille, repas convivial et dessert de célébration si le dessert est sélectionné.',
        'adult_birthday' => 'Anniversaire adulte, repas festif à partager, dessert ou gâteau de célébration si demandé.',
        'child_birthday' => 'Anniversaire enfant, saveurs accessibles, présentation ludique et portions adaptées à l’âge indiqué. Sans alcool, même cuit. Si un dessert est demandé, proposer un gâteau ou dessert de fête compatible avec les exclusions.',
        'friends' => 'Repas convivial entre amis, plats à partager et préparation permettant de profiter des invités.',
        'romantic' => 'Dîner en amoureux, présentation soignée et recettes réalisables sans passer toute la soirée en cuisine.',
        'brunch' => 'Brunch convivial : adapter les rôles sélectionnés en petites préparations de légumes, plat salé complet et note sucrée si demandée.',
        'buffet' => 'Buffet à partager : préparations faciles à servir et à portionner, plat salé complet avec ses accompagnements.',
        'barbecue' => 'Repas au barbecue : grillades ou alternatives végétales, légumes et féculents inclus dans le plat.',
        'picnic' => 'Pique-nique : préparations transportables, faciles à manger et compatibles avec un transport au frais.',
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
}
