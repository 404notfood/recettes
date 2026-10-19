<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            // null means not assessed; [] means none identified in declared ingredients.
            $table->json('allergens')->nullable();
            $table->string('accompaniment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', fn (Blueprint $table) => $table->dropColumn(['allergens', 'accompaniment']));
    }
};
