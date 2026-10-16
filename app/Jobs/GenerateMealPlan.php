<?php

namespace App\Jobs;

use App\Models\MealPlan;
use App\Services\Menus\HybridMenuPlanner;
use App\Services\Menus\PlanningException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateMealPlan implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public bool $failOnTimeout = true;

    public function __construct(public int $planId, public ?int $replaceMealId = null, public ?string $replaceCourse = null)
    {
        $this->onConnection(config('queue.default') === 'sync' ? 'sync' : 'menus');
        $this->onQueue('menus');
    }

    public function handle(HybridMenuPlanner $planner): void
    {
        $claimed = MealPlan::whereKey($this->planId)->where('status', 'queued')->update(['status' => 'generating', 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }
        try {
            $planner->generate(MealPlan::findOrFail($this->planId), $this->replaceMealId, $this->replaceCourse);
        } catch (Throwable $error) {
            // Do not report the exception itself: HTTP/SQL messages can contain private data.
            Log::error('menus.generation_failed', [
                'plan_id' => $this->planId,
                'exception_class' => $error::class,
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'diagnostics' => $error instanceof PlanningException ? $error->diagnostics : [],
            ]);
            // Never store upstream HTTP bodies, credentials or user prompts in an error.
            MealPlan::whereKey($this->planId)->update([
                'status' => 'failed',
                'error_message' => $error instanceof PlanningException ? $error->getMessage() : 'Un problème technique a interrompu la génération. Réessayez plus tard.',
            ]);
        }
    }

    public function failed(?Throwable $error): void
    {
        MealPlan::whereKey($this->planId)->whereIn('status', ['queued', 'generating'])->update([
            'status' => 'failed', 'error_message' => 'La génération a été interrompue. Vous pouvez la relancer.',
        ]);
    }
}
