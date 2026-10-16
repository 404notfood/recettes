<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Menus et courses - Cuistot Club</title>
<style>
@page { size: A4 landscape; margin: 12mm; }
* { box-sizing: border-box; }
body { margin: 0; color: #294b36; background: #faf9f3; font: 12px Arial, sans-serif; }
.toolbar { padding: 16px; display: flex; gap: 16px; align-items: center; }
button { padding: 10px 18px; border: 0; border-radius: 20px; color: white; background: #294b36; cursor: pointer; }
.sheet { max-width: 1100px; padding: 26px; margin: 0 auto 24px; background: white; }
.brand { font: 26px Georgia, serif; border-bottom: 1px solid #d9decf; padding-bottom: 12px; }
h1 { font: 28px Georgia, serif; margin: 16px 0 8px; }
.meta { color: #697962; margin-bottom: 16px; }
table { width: 100%; table-layout: fixed; border-collapse: collapse; }
th { color: white; background: #294b36; padding: 8px; text-align: left; }
td { padding: 8px; vertical-align: top; border-bottom: 1px solid #e6e5da; overflow-wrap: anywhere; }
tr { break-inside: avoid; }
.day { width: 13%; background: #eef0e4; }
.course { margin-top: 5px; }
.course span { display: block; color: #a16643; font-size: 9px; margin-bottom: 2px; }
.shopping { columns: 2; column-gap: 32px; }
.group { break-inside: avoid; margin-bottom: 18px; }
h2 { font: 20px Georgia, serif; background: #eef0e4; padding: 8px; margin: 0; }
.item { display: flex; justify-content: space-between; gap: 20px; border-bottom: 1px solid #e6e5da; padding: 7px; }
.amount { white-space: nowrap; }
@media print {
    body { background: white; font-size: 10px; }
    .toolbar { display: none; }
    .sheet { padding: 0; margin: 0; max-width: none; break-after: page; }
    .sheet:last-child { break-after: auto; }
    .brand { font-size: 22px; padding-bottom: 7px; }
    h1 { font-size: 24px; margin: 10px 0 6px; }
    .meta { margin-bottom: 10px; }
    th, td { padding: 5px 7px; }
    .course { margin-top: 3px; }
    .course span { display: inline; margin-right: 6px; }
}
</style></head><body>
<div class="toolbar"><button type="button" onclick="window.print()">Imprimer ou enregistrer en PDF</button><span>Choisissez votre imprimante ou « Enregistrer au format PDF ».</span></div>
@if($document !== 'shopping')
@for($offset = 0; $offset < $plan->days; $offset += 7)
<section class="sheet">
<div class="brand">cuistot. club</div><h1>{{ $plan->title }}</h1>
<div class="meta">Semaine {{ intdiv($offset, 7) + 1 }} · {{ $plan->people }} personnes</div>
<table><thead><tr><th class="day">Au fil des jours</th><th>Le midi</th><th>Le soir</th></tr></thead><tbody>
@for($day = $offset; $day < min($offset + 7, $plan->days); $day++)
@php($date = \Carbon\CarbonImmutable::parse($plan->start_date)->addDays($day)->locale('fr'))
<tr><td class="day">{{ $date->translatedFormat('l j F') }}</td>
@foreach(['lunch', 'dinner'] as $type)
@php($meal = $byDate->get($date->format('Y-m-d'), collect())->firstWhere('meal_type', $type))
<td>@if($meal)<small>{{ $meal->total_minutes }} min</small>
@foreach(['starter' => 'Entrée', 'main' => 'Plat', 'dessert' => 'Dessert'] as $course => $label)
<div class="course"><span>{{ $label }}</span>{{ $meal->snapshot[$course]['title'] ?? '' }}</div>
@endforeach
@else Repas libre @endif</td>
@endforeach</tr>
@endfor
</tbody></table></section>
@endfor
@endif
@if($document !== 'menus')
<section class="sheet"><div class="brand">cuistot. club</div><h1>La liste de courses</h1><div class="meta">Quantités pour {{ $plan->people }} personnes · Tout le planning</div>
<div class="shopping">@foreach($groups as $group)<section class="group"><h2>{{ $group['label'] }}</h2>
@foreach($group['items'] as $item)<div class="item"><span>☐ {{ $item['name'] }}</span><span class="amount">{{ $formatQuantity($item) }}</span></div>@endforeach
</section>@endforeach</div></section>
@endif
</body></html>
