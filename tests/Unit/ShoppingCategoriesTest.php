<?php

namespace Tests\Unit;

use App\Services\Menus\ShoppingCategories;
use PHPUnit\Framework\TestCase;

class ShoppingCategoriesTest extends TestCase
{
    public function test_grocery_categories_handle_accents_and_compound_names(): void
    {
        $categories = new ShoppingCategories;
        foreach ([
            'Betterave' => 'vegetables', 'Carottes' => 'vegetables', 'Pommes de terre' => 'vegetables',
            'Haricots verts' => 'vegetables', 'Petits pois' => 'vegetables', 'Pommes' => 'fruit',
            'Fromage frais' => 'dairy', 'Crème fraîche' => 'dairy', 'Lait' => 'dairy',
            'Œufs' => 'eggs', 'Poulet' => 'meat', 'Saumon' => 'fish', 'Riz complet' => 'starch',
            'Lentilles' => 'legumes', 'Pois chiches' => 'legumes', 'Haricots rouges' => 'legumes',
            'Lait de coco' => 'pantry', 'Beurre de cacahuète' => 'pantry', 'Persil frais' => 'herbs',
            'Produit inconnu' => 'other',
            'Panais' => 'vegetables', 'Wakamé séché' => 'vegetables', 'Reblochon' => 'dairy',
            'Crozets' => 'starch', 'Châtaignes déjà cuites' => 'starch', 'Tranches de brioche' => 'starch',
            'Feuilles de sauge' => 'herbs', 'Sauce soja' => 'pantry', 'Pesto végétarien' => 'pantry',
            'Dashi de kombu préparé, sans bonite' => 'pantry', 'Mirin' => 'pantry', 'Miso' => 'pantry',
            'Sucre pour le moule' => 'pantry', 'Œufs pour les choux' => 'eggs', 'Chocolat noir pour la crème' => 'pantry',
        ] as $name => $expected) {
            $this->assertSame($expected, $categories->classify($name), $name);
        }
    }

    public function test_grouping_keeps_quantities_and_omits_empty_categories(): void
    {
        $items = [
            ['name' => 'Fromage frais', 'quantity' => 400, 'unit' => 'g'],
            ['name' => 'Betterave', 'quantity' => 800, 'unit' => 'g'],
            ['name' => 'Carotte', 'quantity' => 800, 'unit' => 'g'],
        ];
        $groups = (new ShoppingCategories)->group($items);
        $this->assertSame(['Légumes', 'Laitages'], array_column($groups, 'label'));
        $this->assertSame([$items[1], $items[2]], $groups[0]['items']);
        $this->assertSame([$items[0]], $groups[1]['items']);
    }
}
