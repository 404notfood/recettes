<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Les menus - Cuistot Club</title>
<style>
    @include('menus.pdf-style')
    body { font-size: {{ $fontSize }}pt; }
    .masthead { margin-bottom: 6pt; }
    .brand { font-size: 20pt; }
    .rule { margin-bottom: 8pt; }
    h1 { font-size: 24pt; }
    .eyebrow { margin-bottom: 3pt; }
    .intro { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
    .intro td { vertical-align: middle; }
    .badge { text-align: right; font-size: 9pt; }
    .planning { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .planning th { background: #294b36; color: #fffdf6; text-align: left; padding: 6pt 10pt; font-size: 9pt; font-weight: normal; }
    .planning td { vertical-align: middle; border-bottom: 0.6pt solid #e6e5da; padding: 2pt 10pt; }
    .planning tr { page-break-inside: avoid; }
    .day { width: 12%; background: #eef0e4; }
    .meal { width: 44%; background: #fffdf9; }
    .dinner { background: #f7f5ed; border-left: 1pt solid white; }
    .day-name { font-family: 'Menu Serif', serif; font-size: 12pt; text-transform: capitalize; }
    .day-date { font-size: 6.5pt; color: #78816a; margin-top: 2pt; }
    .time { font-size: 6pt; color: #78816a; margin-bottom: 0; line-height: 1; }
    .quick { color: #b56842; }
    .course { margin: 0; line-height: 1; overflow-wrap: anywhere; word-wrap: break-word; }
    .label { font-size: {{ min(6, $fontSize) }}pt; color: #9b704d; }
    .course a { color: #294b36; text-decoration: none; }
    .empty { color: #9aa08e; font-size: 8pt; }
    .note { margin-top: 6pt; }
</style></head><body>
<table class="masthead"><tr><td class="brand">cuistot<span class="brand-dot">.</span> <span class="club">CLUB</span></td><td class="edition">LE CARNET DU FOYER / SEMAINE {{ $week }}</td></tr></table><div class="rule"></div>
<table class="intro"><tr><td><div class="eyebrow">LES BONNES IDÉES FINISSENT DANS L’ASSIETTE</div><h1>On passe à table.</h1><div class="subtitle">{{ $dateLabel }}</div></td><td class="badge">{{ $plan->people }} {{ $plan->people > 1 ? 'personnes' : 'personne' }}<br><span class="subtitle">{{ implode(', ', array_map(fn ($course) => \App\Services\Menus\MenuOptions::COURSES[$course], \App\Services\Menus\MenuOptions::courses($plan->criteria))) }} @if($plan->criteria['vegetarian'] ?? false)<br>Menus végétariens @endif</span></td></tr></table>
<table class="planning"><thead><tr><th class="day">Cette semaine</th><th class="meal">Le midi</th><th class="meal">Le soir</th></tr></thead><tbody>
@foreach($days as $date => $meals)
    @php($day = \Carbon\CarbonImmutable::parse($date)->locale('fr'))
    <tr><td class="day"><div class="day-name">{{ $day->translatedFormat('l') }}</div><div class="day-date">{{ $day->translatedFormat('j F') }}</div></td>
    @foreach(['lunch', 'dinner'] as $type)
        @php($meal = $meals->firstWhere('meal_type', $type))
        <td class="meal {{ $type === 'dinner' ? 'dinner' : '' }}">
            @if($meal)
                <div class="time">{{ $meal->total_minutes }} min @if($meal->quick) / <span class="quick">Vite fait, bien fait</span>@endif</div>
                @foreach(['starter' => 'Entrée', 'main' => 'Plat', 'dessert' => 'Dessert'] as $course => $label)
                    @continue(!isset($meal->snapshot[$course]))
                    <div class="course"><span class="label">{{ $label }}</span> &nbsp; <a href="{{ $recipeLink($meal, $course) }}">{{ $recipeTitle($meal->snapshot[$course]['title']) }}</a></div>
                    @if($course === 'main' && !empty($meal->snapshot[$course]['accompaniment']))
                        <div class="course"><span class="label">Accomp.</span> &nbsp; {{ $recipeTitle($meal->snapshot[$course]['accompaniment']) }}</div>
                    @endif
                @endforeach
            @else<div class="empty">Un repas à votre guise</div>@endif
        </td>
    @endforeach</tr>
@endforeach
</tbody></table>
<div class="note">Durées estimées, préparation, cuisson et repos compris. Les quantités du panier sont adaptées au foyer.@if($abbreviated) Titres longs abrégés : cliquez pour retrouver la fiche complète.@endif @include('menus.dietary-note')</div>
</body></html>
