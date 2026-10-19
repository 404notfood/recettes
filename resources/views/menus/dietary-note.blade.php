@if(($plan->criteria['occasion'] ?? 'everyday') !== 'everyday') {{ \App\Services\Menus\MenuOptions::occasionLabel($plan->criteria) }}. @endif
@if(($plan->criteria['occasion'] ?? '') === 'child_birthday') Sans alcool, même cuit. @endif
@if($plan->criteria['seasonal'] ?? false) Priorité aux produits de saison : France métropolitaine, conserves et surgelés possibles. @endif
@if(!empty($plan->criteria['diets'])) Choix : {{ implode(', ', array_map(fn ($key) => \App\Services\Menus\DietaryRules::DIETS[$key] ?? $key, $plan->criteria['diets'])) }}. @endif
@if(!empty($plan->criteria['allergens'])) Allergènes exclus : {{ implode(', ', array_map(fn ($key) => \App\Services\Menus\DietaryRules::ALLERGENS[$key] ?? $key, $plan->criteria['allergens'])) }}. Vérifier étiquettes et traces. @endif
