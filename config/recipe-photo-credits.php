<?php

$contents = file_get_contents(resource_path('data/recipe-photo-credits.json'));

if ($contents === false) {
    throw new RuntimeException('Recipe photograph credits are missing.');
}

return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
