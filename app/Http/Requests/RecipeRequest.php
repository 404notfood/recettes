<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'author_name' => [$this->user() ? 'nullable' : 'required', 'string', 'max:80'],
            'guest_email' => [$this->user() ? 'nullable' : 'required', 'email', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=10000,max_height=10000'],
            'prep_time' => ['required', 'integer', 'min:1', 'max:1440'],
            'cook_time' => ['required', 'integer', 'min:0', 'max:1440'],
            'servings' => ['required', 'integer', 'min:1', 'max:100'],
            'difficulty' => ['required', 'in:facile,moyen,avance'],
            'ingredients' => ['required', 'array', 'min:1', 'max:50'],
            'ingredients.*.name' => ['required', 'string', 'max:150'],
            'ingredients.*.quantity' => ['nullable', 'numeric', 'min:0.01', 'max:100000'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:25'],
            'steps' => ['required', 'array', 'min:1', 'max:30'],
            'steps.*' => ['required', 'string', 'min:10', 'max:3000'],
            'tags' => ['nullable', 'array', 'max:5'],
            'tags.*' => ['string', 'in:vegetarien,vegan,sans-gluten,rapide,economique,de-saison'],
            'intent' => ['required', 'in:submit,draft'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['required' => 'Ce champ est nécessaire.', 'min' => 'Ajoutez un peu plus de détails.', 'image.max' => 'La photo doit peser moins de 5 Mo.', 'ingredients.*.name.required' => 'Indiquez le nom de cet ingrédient.', 'steps.*.min' => 'Décrivez cette étape en au moins 10 caractères.'];
    }
}
