<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->string('course_type')->nullable()->index();
            $table->unsignedSmallInteger('total_minutes')->nullable();
            $table->boolean('vegetarian')->nullable();
            $table->string('main_ingredient')->nullable();
            $table->json('food_groups')->nullable();
            $table->string('protein_source')->nullable();
            $table->string('dessert_profile')->nullable();
            $table->boolean('is_ai_generated')->default(false);
            $table->boolean('planning_estimated')->default(false);
        });
        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('start_date');
            $table->unsignedTinyInteger('days');
            $table->unsignedTinyInteger('people');
            $table->json('criteria');
            $table->string('status')->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
        Schema::create('planned_meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('meal_type');
            $table->boolean('quick')->default(false);
            $table->boolean('locked')->default(false);
            $table->unsignedSmallInteger('total_minutes');
            foreach (['starter', 'main', 'dessert'] as $course) {
                $table->foreignId($course.'_recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            }
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['meal_plan_id', 'date', 'meal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_meals');
        Schema::dropIfExists('meal_plans');
        Schema::table('recipes', fn (Blueprint $table) => $table->dropColumn(['course_type', 'total_minutes', 'vegetarian', 'main_ingredient', 'food_groups', 'protein_source', 'dessert_profile', 'is_ai_generated', 'planning_estimated']));
    }
};
