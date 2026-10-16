<?php

use Dompdf\Options;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

require __DIR__.'/../vendor/autoload.php';

$missing = array_values(array_filter([
    Dompdf\Dompdf::class, Options::class,
    Fpdi::class, StreamReader::class,
], fn (string $class): bool => ! class_exists($class)));
if ($missing) {
    fwrite(STDERR, 'Dépendances PDF manquantes : '.implode(', ', $missing).PHP_EOL);
    fwrite(STDERR, 'Exécuter composer install --no-dev --prefer-dist --optimize-autoloader dans le dossier du site.'.PHP_EOL);
    exit(1);
}
echo 'Dépendances PDF disponibles.'.PHP_EOL;
