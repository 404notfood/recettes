<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Le panier - Cuistot Club</title><style>
    @include('menus.pdf-style')
    @page { margin: 28pt 35pt 42pt; }
    .intro { margin: 8pt 0 9pt; font-size: 7pt; color: #78816a; }
    .items { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .items tr { page-break-inside: avoid; }
    .category td { padding: 4pt 0 0; }
    .heading { page-break-after: avoid; }
    h2 { font-family: 'Menu Serif', serif; font-size: 14pt; font-weight: normal; background: #eef0e4; padding: 3pt 10pt; margin: 0; }
    .item td { padding: 3pt 10pt; border-bottom: 0.6pt solid #e6e5da; vertical-align: middle; }
    .box-cell { width: 4%; }
    .check { width: 8pt; height: 8pt; border: 0.7pt solid #889579; }
    .amount { width: 24%; text-align: right; color: #56704b; }
    .name { width: 72%; overflow-wrap: anywhere; word-wrap: break-word; }
    .unknown { color: #b56842; font-size: 7pt; }
</style></head><body>
<table class="masthead"><tr><td class="brand">cuistot<span class="brand-dot">.</span> <span class="club">CLUB</span></td><td class="edition">LES COURSES</td></tr></table><div class="rule"></div>
<div class="eyebrow">IL NE MANQUE PLUS QUE LE PANIER</div><h1>Le goût des bonnes choses.</h1><div class="subtitle">{{ $dateLabel }} / {{ $plan->people }} personnes / {{ $plan->meals->count() }} repas</div>
<div class="intro">Les quantités sont additionnées pour tout le foyer. Cochez ce que vous avez déjà. Les pièces sont arrondies au supérieur ; les conditionnements vendus peuvent varier.</div>
@foreach($groups as $group)<table class="items"><thead>
<tr><td style="width:4%;height:0;padding:0"></td><td style="width:72%;height:0;padding:0"></td><td style="width:24%;height:0;padding:0"></td></tr>
    <tr class="category heading"><td colspan="3"><h2>{{ $group['label'] }}</h2></td></tr>
</thead><tbody>
    @foreach($group['items'] as $item)<tr class="item"><td class="box-cell"><div class="check"></div></td><td class="name">{{ $item['name'] }}</td><td class="amount {{ $item['quantity'] === null ? 'unknown' : '' }}">{{ $formatQuantity($item) }}</td></tr>@endforeach
</tbody></table>@endforeach
@if(!$groups)<p>Aucun ingrédient déclaré dans ces recettes.</p>@endif
</body></html>
