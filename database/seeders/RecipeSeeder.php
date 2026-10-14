<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Plats du quotidien', 'plats', 'utensils', 'Du bon, du simple, à partager.'], ['Végétal & gourmand', 'vegetal', 'leaf', 'Les légumes ont le premier rôle.'], ['Petites douceurs', 'douceurs', 'cake', 'Une bonne raison de garder une place.'], ['Brunch & petit-déj', 'brunch', 'coffee', 'Les matins qui prennent leur temps.'], ['Fait maison', 'maison', 'wheat', 'Le plaisir de faire soi-même.']] as [$name, $slug, $icon, $description]) {
            Category::firstOrCreate(['slug' => $slug], compact('name', 'icon', 'description'));
        }
        $editor = User::firstOrCreate(['email' => config('recipes.editor_email')], ['name' => config('recipes.editor_name'), 'password' => Str::password(32), 'email_verified_at' => now()]);
        $ingredient = fn ($quantity, $unit, $name) => compact('quantity', 'unit', 'name');
        $recipes = [
            ['Bowl croquant, patate douce & avocat', 'bowl-croquant-patate-douce-avocat', 'vegetal', 'salad', 15, 25, 2, 'facile', ['vegetarien', 'vegan'],
                'Des légumes colorés, des pois chiches et une sauce au tahini. Un grand bol généreux qui fait du bien et ne laisse personne sur sa faim.',
                [$ingredient(1, '', 'patate douce'), $ingredient(1, '', 'avocat'), $ingredient(200, 'g', 'pois chiches cuits'), $ingredient(100, 'g', 'chou rouge'), $ingredient(100, 'g', 'tomates cerises'), $ingredient(2, 'c. à soupe', 'tahini'), $ingredient(1, '', 'citron'), $ingredient(1, 'c. à soupe', 'huile d’olive')],
                ['Préchauffez le four à 200 °C. Coupez la patate douce en dés, mélangez-la avec l’huile et une pincée de sel. Enfournez pour 25 minutes.', 'Rincez les pois chiches. Émincez le chou, coupez les tomates en deux et l’avocat en lamelles. Répartissez le tout dans deux bols.', 'Mélangez le tahini, le jus de citron et deux cuillères d’eau pour obtenir une sauce fluide. Ajoutez les dés rôtis et versez la sauce au moment de servir.']],
            ['Tagliatelles aux champignons & bœuf saisi', 'tagliatelles-champignons-boeuf', 'plats', 'pasta', 15, 15, 4, 'facile', ['de-saison', 'rapide'],
                'La rencontre des pâtes fraîches, des champignons dorés et d’une sauce toute simple. Un plat convivial, prêt le temps de mettre la table.',
                [$ingredient(400, 'g', 'tagliatelles'), $ingredient(300, 'g', 'champignons'), $ingredient(300, 'g', 'bœuf à saisir en lamelles'), $ingredient(150, 'g', 'tomates cerises'), $ingredient(100, 'ml', 'crème'), $ingredient(2, '', 'gousses d’ail'), $ingredient(2, 'c. à soupe', 'huile d’olive')],
                ['Portez une casserole d’eau salée à ébullition. Faites cuire les tagliatelles selon les indications du paquet et gardez une tasse d’eau de cuisson.', 'Émincez les champignons. Faites-les dorer à feu vif dans une grande poêle avec l’huile, puis ajoutez l’ail et les tomates.', 'Ajoutez le bœuf dans la poêle et saisissez-le jusqu’à la cuisson souhaitée. Versez la crème et un peu d’eau de cuisson.', 'Ajoutez les pâtes égouttées, mélangez une minute sur feu doux et servez immédiatement avec du persil.']],
            ['Brioche perdue, banane & myrtilles', 'brioche-perdue-banane-myrtilles', 'brunch', 'toast', 10, 10, 2, 'facile', ['vegetarien', 'rapide', 'economique'],
                'Une brioche de la veille, une poêle chaude et un peu de patience. Le petit-déjeuner devient un vrai moment de plaisir.',
                [$ingredient(4, '', 'tranches de brioche'), $ingredient(1, '', 'œuf'), $ingredient(150, 'ml', 'lait'), $ingredient(1, '', 'banane'), $ingredient(80, 'g', 'myrtilles'), $ingredient(15, 'g', 'beurre'), $ingredient(1, 'c. à soupe', 'sirop d’érable')],
                ['Fouettez l’œuf avec le lait dans une assiette creuse. Trempez chaque tranche de brioche quelques secondes sur les deux faces.', 'Faites fondre le beurre dans une poêle à feu moyen. Faites dorer la brioche trois à quatre minutes de chaque côté.', 'Coupez la banane en rondelles. Répartissez les fruits sur la brioche et ajoutez un filet de sirop d’érable.']],
            ['Saumon rôti, couscous & légumes verts', 'saumon-roti-couscous-legumes', 'plats', 'soup', 15, 20, 2, 'facile', [],
                'Un saumon fondant, de la semoule légère et une belle poignée de légumes. Le genre d’assiette qui transforme un dîner de semaine.',
                [$ingredient(2, '', 'pavés de saumon'), $ingredient(120, 'g', 'semoule de couscous'), $ingredient(200, 'g', 'haricots verts'), $ingredient(100, 'g', 'tomates cerises'), $ingredient(1, '', 'citron'), $ingredient(2, 'c. à soupe', 'huile d’olive')],
                ['Préchauffez le four à 190 °C. Posez le saumon dans un plat, ajoutez un filet d’huile, du sel et du poivre. Faites cuire environ 15 à 20 minutes selon l’épaisseur.', 'Faites cuire les haricots dans l’eau bouillante huit à dix minutes. Versez 150 ml d’eau bouillante sur la semoule, couvrez et laissez gonfler cinq minutes.', 'Égrainez la semoule à la fourchette. Ajoutez les tomates coupées et les haricots, puis servez avec le saumon et un quartier de citron.']],
            ['Pizza poulet, ananas & oignon rouge', 'pizza-poulet-ananas-oignon', 'plats', 'pizza', 15, 20, 4, 'facile', [],
                'Une pizza généreuse, entre douceur et caractère. À découper au centre de la table et à partager sans cérémonie.',
                [$ingredient(1, '', 'pâte à pizza'), $ingredient(150, 'g', 'poulet déjà cuit'), $ingredient(150, 'g', 'mozzarella'), $ingredient(120, 'g', 'ananas en morceaux'), $ingredient(100, 'g', 'sauce tomate'), $ingredient(0.5, '', 'oignon rouge')],
                ['Préchauffez le four à 230 °C. Étalez la pâte sur une plaque recouverte de papier cuisson.', 'Étalez la sauce tomate. Répartissez le poulet effiloché, la mozzarella, l’ananas égoutté et l’oignon émincé.', 'Enfournez pour 15 à 20 minutes, jusqu’à ce que la pâte soit dorée et le fromage fondu. Laissez reposer deux minutes avant de découper.']],
            ['Gâteau chocolat, ganache & grand plaisir', 'gateau-chocolat-ganache', 'douceurs', 'cake', 35, 30, 8, 'moyen', ['vegetarien'],
                'Un gâteau de fête au chocolat, moelleux et généreusement nappé. Une recette à préparer tranquillement, pour un beau moment à plusieurs.',
                [$ingredient(250, 'g', 'chocolat noir'), $ingredient(180, 'g', 'farine'), $ingredient(150, 'g', 'sucre'), $ingredient(150, 'g', 'beurre'), $ingredient(4, '', 'œufs'), $ingredient(200, 'ml', 'crème liquide'), $ingredient(8, 'g', 'levure chimique')],
                ['Préchauffez le four à 180 °C. Faites fondre 150 g de chocolat avec le beurre. Fouettez les œufs et le sucre, puis incorporez le chocolat fondu.', 'Ajoutez la farine et la levure. Versez dans un moule beurré de 20 cm et faites cuire environ 30 minutes. Vérifiez la cuisson et laissez refroidir complètement.', 'Chauffez la crème sans la faire bouillir. Versez-la sur le chocolat restant, attendez une minute puis mélangez jusqu’à obtenir une ganache lisse.', 'Nappez le gâteau refroidi. Pour une finition pochée, réservez un peu de ganache au frais, fouettez-la puis utilisez une poche à douille.']],
            ['Pain de campagne aux petites graines', 'pain-campagne-graines', 'maison', 'bread', 150, 40, 8, 'moyen', ['vegan', 'economique'],
                'Une croûte qui chante, une mie tendre et des graines qui croquent. Prévoyez deux heures de repos : le pain prend son temps.',
                [$ingredient(350, 'g', 'farine de blé'), $ingredient(150, 'g', 'farine complète'), $ingredient(320, 'ml', 'eau tiède'), $ingredient(7, 'g', 'levure boulangère sèche'), $ingredient(9, 'g', 'sel'), $ingredient(60, 'g', 'mélange de graines')],
                ['Mélangez les farines, la levure et l’eau. Ajoutez le sel puis pétrissez huit à dix minutes. Incorporez la moitié des graines.', 'Couvrez la pâte et laissez-la lever une heure dans un endroit tiède. Rabattez-la, formez une boule et posez-la sur du papier cuisson.', 'Humidifiez la surface, parsemez des graines restantes et laissez lever une heure. Préchauffez le four à 230 °C avec un petit récipient d’eau.', 'Incisez le pain et enfournez pour 15 minutes à 230 °C, puis 25 minutes à 200 °C. Laissez refroidir sur une grille avant de trancher.']],
            ['Assiette verte, œuf mollet & avocat', 'assiette-verte-oeuf-mollet', 'vegetal', 'bowl', 10, 7, 2, 'facile', ['vegetarien', 'rapide', 'sans-gluten'],
                'Du croquant, de la fraîcheur et un œuf au jaune encore tendre. Une assiette facile à adapter avec ce qu’il reste au réfrigérateur.',
                [$ingredient(2, '', 'œufs'), $ingredient(1, '', 'avocat'), $ingredient(100, 'g', 'pousses d’épinards'), $ingredient(100, 'g', 'tomates cerises'), $ingredient(1, '', 'carotte'), $ingredient(0.5, '', 'concombre'), $ingredient(1, 'c. à soupe', 'graines de sésame')],
                ['Plongez les œufs dans l’eau bouillante et laissez cuire six à sept minutes. Rafraîchissez-les dans l’eau froide puis écalez-les délicatement.', 'Lavez les légumes. Râpez la carotte, coupez le concombre en fines lamelles et l’avocat en deux.', 'Disposez les légumes dans deux assiettes. Ajoutez les œufs coupés, parsemez de sésame et assaisonnez avec une vinaigrette au citron.']],
        ];
        foreach ($recipes as $index => [$title, $slug, $category, $photo, $prep, $cook, $servings, $difficulty, $tags, $description, $ingredients, $steps]) {
            if (! is_file(public_path('images/'.$photo.'.webp'))) {
                throw new \RuntimeException('Photo du catalogue manquante : '.$slug);
            }
            Recipe::withTrashed()->firstOrCreate(['slug' => $slug], ['user_id' => $editor->id, 'author_name' => config('recipes.editor_name'), 'category_id' => Category::where('slug', $category)->firstOrFail()->id, 'title' => $title, 'description' => $description, 'image' => '/images/'.$photo.'.webp', 'prep_time' => $prep, 'cook_time' => $cook, 'servings' => $servings, 'difficulty' => $difficulty, 'tags' => $tags, 'ingredients' => $ingredients, 'steps' => $steps, 'status' => 'published', 'featured' => $index === 0, 'published_at' => now()->subHours($index)]);
        }
    }
}
