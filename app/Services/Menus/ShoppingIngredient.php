<?php

namespace App\Services\Menus;

use Illuminate\Support\Str;

class ShoppingIngredient
{
    /** @return array{name:string,key:string,unit:string,factor:int}|null */
    public function normalize(string $name, string $unit): ?array
    {
        $key = Str::lower(Str::ascii(Str::squish($name)));
        if (preg_match('/^eau(?: pour .+| de cuisson)?$/', $key)) {
            return null;
        }
        $countable = false;
        foreach ([
            'Œuf' => 'oeufs?(?: frais)?', 'Banane' => 'bananes?(?: fraiches?)?',
            'Pomme' => 'pommes?(?: fraiches?)?', 'Poire' => 'poires?(?: fraiches?)?',
            'Citron' => 'citrons?', 'Orange' => 'oranges?', 'Clémentine' => 'clementines?',
            'Kiwi' => 'kiwis?', 'Concombre' => 'concombres?', 'Carotte' => 'carottes?',
            'Tomate' => 'tomates?', 'Oignon' => 'oignons?',
        ] as $label => $pattern) {
            if (preg_match('/^(?:'.$pattern.')$/', $key)) {
                $name = $label;
                $key = Str::lower(Str::ascii($label));
                $countable = true;
                break;
            }
        }
        foreach ([
            'Pois chiches cuits' => 'pois chiches? cuits?(?: egouttes?)?',
            'Lentilles cuites' => 'lentilles? cuites?(?: egouttees?)?',
        ] as $label => $pattern) {
            if (preg_match('/^(?:'.$pattern.')$/', $key)) {
                $name = $label;
                $key = Str::lower(Str::ascii($label));
                break;
            }
        }
        $unitKey = Str::lower(Str::ascii(trim($unit)));
        [$unit, $factor] = match ($unitKey) {
            'kg', 'kilogramme', 'kilogrammes' => ['g', 1000],
            'g', 'gramme', 'grammes' => ['g', 1],
            'l', 'litre', 'litres' => ['ml', 1000],
            'cl' => ['ml', 10], 'ml', 'millilitre', 'millilitres' => ['ml', 1],
            'piece', 'pieces', 'unite', 'unites' => ['pièce', 1],
            '' => [$countable ? 'pièce' : '', 1],
            default => [trim($unit), 1],
        };

        return ['name' => trim($name), 'key' => $key, 'unit' => $unit, 'factor' => $factor];
    }
}
