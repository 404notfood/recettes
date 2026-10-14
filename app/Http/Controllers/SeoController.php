<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
        foreach (['/', '/recettes', '/a-propos'] as $path) {
            $xml->addChild('url')->addChild('loc', htmlspecialchars(url($path), ENT_XML1));
        }
        foreach (Recipe::where('status', 'published')->get(['slug', 'updated_at']) as $recipe) {
            $item = $xml->addChild('url');
            $item->addChild('loc', htmlspecialchars(url('/recettes/'.$recipe->slug), ENT_XML1));
            $item->addChild('lastmod', $recipe->updated_at->toAtomString());
        }
        $content = $xml->asXML();
        if ($content === false) {
            throw new \RuntimeException('Le sitemap ne peut pas être généré.');
        }

        return response($content, 200)->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
