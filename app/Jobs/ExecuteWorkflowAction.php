<?php

namespace App\Jobs;

use App\Models\WorkflowAction;
use App\Services\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteWorkflowAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $actionId,
        private string $targetClass,
        private int $targetId,
    ) {}

    public function handle(): void
    {
        $action = WorkflowAction::find($this->actionId);

        if (! $action) {
            return;
        }

        $target = app($this->targetClass)->find($this->targetId);

        if (! $target) {
            return;
        }

        WorkflowEngine::executeAction($action->type, $action->config ?? [], $target);
    }
}
