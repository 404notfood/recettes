<?php

namespace App\Services\Menus;

use App\Models\MealPlan;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class MenuPdfExporter
{
    public function __construct(private ShoppingList $shopping) {}

    public function printable(MealPlan $plan, string $document = 'full'): string
    {
        $plan->loadMissing('meals');

        return view('menus.planning-print', [
            'plan' => $plan, 'document' => $document,
            'byDate' => $plan->meals->groupBy(fn ($meal) => $meal->date->format('Y-m-d')),
            'groups' => $this->shopping->grouped($this->shopping->forPlan($plan)),
            'formatQuantity' => fn ($item) => $this->formatQuantity($item),
        ])->render();
    }

    public function render(MealPlan $plan, string $document = 'full'): string
    {
        $plan->loadMissing('meals');
        $start = CarbonImmutable::parse($plan->start_date);
        $end = $start->addDays($plan->days - 1);
        $dateLabel = 'Du '.$start->format('d/m').' au '.$end->format('d/m/Y');
        $documents = [];

        if ($document !== 'shopping') {
            $byDate = $plan->meals->groupBy(fn ($meal) => $meal->date->format('Y-m-d'));
            for ($offset = 0; $offset < $plan->days; $offset += 7) {
                $days = collect();
                for ($day = $offset; $day < min($offset + 7, $plan->days); $day++) {
                    $date = $start->addDays($day)->format('Y-m-d');
                    $days->put($date, $byDate->get($date, collect()));
                }
                $first = CarbonImmutable::parse($days->keys()->first());
                $last = CarbonImmutable::parse($days->keys()->last());
                $data = [
                    'plan' => $plan, 'days' => $days, 'week' => (int) ($offset / 7) + 1,
                    'weekCount' => (int) ceil($plan->days / 7),
                    'dateLabel' => 'Du '.$first->format('d/m').' au '.$last->format('d/m/Y'),
                ];
                // Measure the actual PDF to keep every week on precisely one page.
                foreach ([false, true] as $abbreviated) {
                    foreach ([9, 8.4, 7.8, 7.2] as $fontSize) {
                        $pdf = $this->renderPage('menus.pdf', $data + [
                            'fontSize' => $fontSize, 'abbreviated' => $abbreviated,
                            'recipeTitle' => fn (string $title) => $abbreviated ? Str::limit($title, 60, '…') : $title,
                            'recipeLink' => fn ($meal, string $course) => isset($meal->snapshot[$course]['slug']) ? route('recipes.show', ['recipe' => $meal->snapshot[$course]['slug']]) : route('menus.recipe', ['mealPlan' => $plan->id, 'meal' => $meal->id, 'course' => $course]),
                        ], 'landscape');
                        if ($pdf->getCanvas()->get_page_count() === 1) {
                            $documents[] = $pdf->output();

                            continue 3;
                        }
                    }
                }
                throw ValidationException::withMessages(['document' => 'Cette semaine ne tient pas sur une page. Vérifiez les données du planning avant cet export.']);
            }
        }

        if ($document !== 'menus') {
            $groups = $this->shopping->grouped($this->shopping->forPlan($plan));
            $pdf = $this->renderPage('menus.shopping-pdf', [
                'plan' => $plan, 'dateLabel' => $dateLabel, 'groups' => $groups,
                'formatQuantity' => fn ($item) => $this->formatQuantity($item),
            ], 'portrait');
            $documents[] = $pdf->output();
        }

        // Preserve each page's orientation when combining menus and shopping.
        $combined = new Fpdi('P', 'pt', 'A4');
        $combined->SetAutoPageBreak(false);
        $combined->SetTitle('Menus et courses - Cuistot Club');
        foreach ($documents as $bytes) {
            $count = $combined->setSourceFile(StreamReader::createByString($bytes));
            for ($page = 1; $page <= $count; $page++) {
                $template = $combined->importPage($page, 'CropBox', true, true);
                $size = $combined->getTemplateSize($template);
                if (! is_array($size)) {
                    throw new \RuntimeException('Impossible de lire la taille de cette page PDF.');
                }
                $combined->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $combined->useTemplate($template);
            }
        }

        return $combined->Output('S');
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  view-string  $view
     */
    private function renderPage(string $view, array $data, string $orientation): Dompdf
    {
        $cache = storage_path('app/private/pdf-fonts');
        File::ensureDirectoryExists($cache);
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setFontCache($cache);
        $options->setTempDir($cache);
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot([public_path(), base_path('vendor/dompdf')]);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', $orientation);
        $pdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $color = [0.35, 0.43, 0.40];
        $canvas->page_text(30, $canvas->get_height() - 26, 'CUISTOT CLUB  /  Le plaisir se partage', $font, 7, $color);
        $footer = $orientation === 'landscape'
            ? 'Semaine '.$data['week'].' / '.$data['weekCount'].'  -  Durées estimées'
            : ($data['footerLabel'] ?? 'Liste de courses').'  /  {PAGE_NUM} sur {PAGE_COUNT}';
        $canvas->page_text($canvas->get_width() - 215, $canvas->get_height() - 26, $footer, $font, 7, $color);

        return $pdf;
    }

    /**
     * @param  list<array<string,mixed>>  $recipes
     */
    public function recipes(MealPlan $plan, array $recipes): string
    {
        return $this->renderPage('menus.recipes-print', ['plan' => $plan, 'recipes' => $recipes, 'pdf' => true, 'footerLabel' => 'Recettes à cuisiner'], 'portrait')->output();
    }

    /**
     * @param  array{name:string,unit:string,quantity:float|null}  $item
     */
    private function formatQuantity(array $item): string
    {
        if ($item['quantity'] === null) {
            return 'À vérifier';
        }
        $quantity = $item['quantity'];
        $unit = $item['unit'];
        if ($unit === 'g' && $quantity >= 1000) {
            $quantity /= 1000;
            $unit = 'kg';
        }
        if ($unit === 'ml' && $quantity >= 1000) {
            $quantity /= 1000;
            $unit = 'l';
        }
        if ($unit === 'pièce' && $quantity > 1) {
            $unit = 'pièces';
        }

        return rtrim(rtrim(number_format($quantity, 2, ',', ' '), '0'), ',').' '.$unit;
    }
}
