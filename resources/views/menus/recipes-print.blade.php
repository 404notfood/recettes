<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Recettes à cuisiner - Cuistot Club</title><style>
@font-face { font-family: 'Menu Serif'; src: url('{{ $pdf ? public_path('fonts/menu/DMSerifDisplay-Regular.ttf') : asset('fonts/menu/DMSerifDisplay-Regular.ttf') }}') format('truetype'); }
@page { size: A4 portrait; margin: 30pt 35pt 42pt; }
* { box-sizing: border-box; }
body { margin: 0; font-family: {{ $pdf ? "'DejaVu Sans'" : 'Arial' }}, sans-serif; color: #294b36; font-size: 10pt; line-height: 1.45; background: {{ $pdf ? '#fff' : '#fcfaf5' }}; }
.toolbar { margin: 24px auto; max-width: 760px; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 0 20px; }
button { background: #294b36; color: white; border: none; border-radius: 30px; padding: 12px 20px; cursor: pointer; font: inherit; }
.recipe { {{ $pdf ? '' : 'max-width:760px; margin:24px auto; background:white; padding:40px;' }} page-break-before: always; }
.recipe:first-of-type { page-break-before: auto; }
.brand { font-family: 'Menu Serif', Georgia, serif; font-size: 24pt; border-bottom: 0.6pt solid #d9decf; padding-bottom: 10pt; margin-bottom: 16pt; }
.brand span { color: #d87745; }
.eyebrow { color: #9b704d; font-size: 8pt; margin-bottom: 5pt; }
h1 { font-family: 'Menu Serif', Georgia, serif; font-weight: normal; font-size: 25pt; line-height: 1.1; margin: 0 0 10pt; }
h2 { font-family: 'Menu Serif', Georgia, serif; font-weight: normal; font-size: 18pt; margin: 18pt 0 8pt; }
.meta { color: #78816a; font-size: 9pt; margin-bottom: 16pt; }
.ingredients { width: 100%; border-collapse: collapse; table-layout: fixed; }
.ingredients tr { page-break-inside: avoid; }
.ingredients td { padding: 5pt 8pt; border-bottom: 0.5pt solid #e6e5da; }
.ingredients .amount { width: 28%; text-align: right; color: #56704b; }
ol { margin: 0; padding-left: 18pt; }
li { margin-bottom: 10pt; padding-left: 4pt; }
.step { white-space: pre-line; }
@media print { body { background: white; } .toolbar { display: none !important; } .recipe { width: auto; max-width: none; margin: 0; padding: 0; box-shadow: none; } }
@media (max-width:600px) { .recipe { margin: 10px; padding: 20px; } .toolbar { flex-wrap: wrap; } }
</style></head><body>
@if(!$pdf)<div class="toolbar"><span>{{ count($recipes) }} recette(s) / quantités pour {{ $plan->people }} personnes</span><button type="button" onclick="window.print()">Imprimer les recettes</button></div>@endif
@foreach($recipes as $recipe)
<article class="recipe">
    <div class="brand">cuistot<span>.</span> club</div>
    <div class="eyebrow">LE CARNET À CUISINER / {{ $loop->iteration }} SUR {{ count($recipes) }}</div>
    <h1>{{ $recipe['title'] }}</h1>
    <div class="meta">{{ $plan->people }} personnes @if(isset($recipe['total_minutes'])) / {{ $recipe['total_minutes'] }} min, durée estimée @endif</div>
    @if(!empty($recipe['accompaniment']))<p>Accompagnement : {{ $recipe['accompaniment'] }}</p>@endif
    <p class="meta">Allergènes : {{ !isset($recipe['allergens']) ? 'analyse non renseignée' : ($recipe['allergens'] ? implode(', ', array_map(fn ($key) => \App\Services\Menus\DietaryRules::ALLERGENS[$key] ?? $key, $recipe['allergens'])) : 'aucun identifié dans les ingrédients déclarés') }}. Étiquettes et traces à vérifier.</p>
    <h2>Dans le panier</h2>
    <table class="ingredients">
    @foreach($recipe['ingredients'] as $ingredient)
        @php
            $unit = $ingredient['unit'] ?? '';
            $quantity = isset($ingredient['quantity']) ? $ingredient['quantity'] * $plan->people / max(1, $recipe['servings']) : null;
            if ($quantity !== null && $unit === 'pièce') $quantity = ceil($quantity);
            $label = $quantity === null ? 'À vérifier' : rtrim(rtrim(number_format($quantity, 2, ',', ' '), '0'), ',').' '.$unit;
        @endphp
        <tr><td>{{ $ingredient['name'] }}</td><td class="amount">{{ $label }}</td></tr>
    @endforeach
    </table>
    <h2>À vous de jouer</h2>
    <ol>@foreach($recipe['steps'] ?? [] as $step)<li><div class="step">{{ $step }}</div></li>@endforeach</ol>
</article>
@endforeach
</body></html>
