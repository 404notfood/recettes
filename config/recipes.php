<?php

return [
    'editor_email' => env('RECIPE_EDITOR_EMAIL', env('APP_ENV') === 'local' ? 'redaction@idee.test' : 'redaction@recettes.cuistot-club.fr'),
    'editor_name' => 'La rédaction Cuistot Club',
];
