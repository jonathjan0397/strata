<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WorkflowEngine
{
    /**
     * Fire all active workflows matching the given trigger.
     */
    public static function fire(string $trigger, Model $target): void
    {
        $workflows = Workflow::where('trigger', $trigger)
            ->where('is_active', true)
            ->with(['conditions', 'actions'])
            ->get();

        foreach ($workflows as $workflow) {
            static::run($workflow, $trigger, $target);
        }
    }

    private static function run(Workflow $workflow, string $trigger, Model $target): void
    {
        $log = [];

        // Evaluate all conditions (must ALL pass)
        foreach ($workflow->conditions as $condition) {
            $passed = static::evaluateCondition($condition->field, $condition->operator, $condition->value, $target);
            $log[] = "Condition [{$condition->field} {$condition->operator} {$condition->value}]: " . ($passed ? 'pass' : 'fail');

            if (! $passed) {
                WorkflowRun::create([
                    'workflow_id' => $workflow->id,
                    'trigger'     => $trigger,
                    'target_type' => class_basename($target),
                    'target_id'   => $target->getKey(),
                    'status'      => 'skipped',
                    'log'         => $log,
                    'ran_at'      => now(),
                ]);
                return;
            }
        }

        // Execute all actions
        foreach ($workflow->actions as $action) {
            if ($action->delay_minutes > 0) {
                // Schedule delayed actions via a queued job
                \App\Jobs\ExecuteWorkflowAction::dispatch($action->id, $target->getMorphClass(), $target->getKey())
                    ->delay(now()->addMinutes($action->delay_minutes));
                $log[] = "Action [{$action->type}]: queued with {$action->delay_minutes}m delay";
                continue;
            }

            try {
                static::executeAction($action->type, $action->config ?? [], $target);
                $log[] = "Action [{$action->type}]: executed";
            } catch (\Throwable $e) {
                $log[] = "Action [{$action->type}]: failed — {$e->getMessage()}";
                Log::error("WorkflowEngine action failed", [
                    'workflow' => $workflow->id,
                    'action'   => $action->type,
                    'error'    => $e->getMessage(),
                ]);

                WorkflowRun::create([
                    'workflow_id' => $workflow->id,
                    'trigger'     => $trigger,
                    'target_type' => class_basename($target),
                    'target_id'   => $target->getKey(),
                    'status'      => 'failed',
                    'log'         => $log,
                    'ran_at'      => now(),
                ]);
                return;
            }
        }

        WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'trigger'     => $trigger,
            'target_type' => class_basename($target),
            'target_id'   => $target->getKey(),
            'status'      => 'completed',
            'log'         => $log,
            'ran_at'      => now(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Condition evaluation
    // -----------------------------------------------------------------------

    private static function evaluateCondition(string $field, string $operator, string $expected, Model $target): bool
    {
        $actual = static::resolveField($field, $target);

        return match ($operator) {
            'eq'       => (string) $actual === $expected,
            'neq'      => (string) $actual !== $expected,
            'gt'       => (float) $actual > (float) $expected,
            'lt'       => (float) $actual < (float) $expected,
            'gte'      => (float) $actual >= (float) $expected,
            'lte'      => (float) $actual <= (float) $expected,
            'contains' => str_contains((string) $actual, $expected),
            default    => false,
        };
    }

    /**
     * Resolve a dot-notated field path against the target model.
     * E.g. "invoice.total", "client.role", "service.status"
     */
    private static function resolveField(string $field, Model $target): mixed
    {
        $parts  = explode('.', $field);
        $object = $target;

        foreach ($parts as $i => $part) {
            if ($i === 0) {
                // First segment: the model type — skip if it matches, else try relation
                $modelName = strtolower(class_basename($target));
                if ($part === $modelName) {
                    continue;
                }
                // Try it as an attribute anyway
                $object = $object->{$part} ?? null;
            } else {
                $object = is_object($object) ? ($object->{$part} ?? null) : null;
            }
        }

        return $object;
    }

    // -----------------------------------------------------------------------
    // Action execution
    // -----------------------------------------------------------------------

    public static function executeAction(string $type, array $config, Model $target): void
    {
        match ($type) {
            'send.email'       => static::actionSendEmail($config, $target),
            'create.ticket'    => static::actionCreateTicket($config, $target),
            'suspend.service'  => static::actionSuspendService($config, $target),
            'add.credit'       => static::actionAddCredit($config, $target),
            'call.webhook'     => static::actionCallWebhook($config, $target),
            default            => throw new \RuntimeException("Unknown action type: {$type}"),
        };
    }

    private static function actionSendEmail(array $config, Model $target): void
    {
        $to      = $config['to'] ?? null;
        $subject = $config['subject'] ?? 'Notification';
        $body    = $config['body'] ?? '';

        // Resolve recipient: 'client' means the user associated with the target
        if ($to === 'client') {
            $user = match (true) {
                $target instanceof Invoice => $target->user,
                $target instanceof Service => $target->user,
                $target instanceof User    => $target,
                default                    => null,
            };
            $to = $user?->email;
        }

        if (! $to) {
            throw new \RuntimeException('send.email: no recipient resolved');
        }

        Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
    }

    private static function actionCreateTicket(array $config, Model $target): void
    {
        $user = match (true) {
            $target instanceof Invoice => $target->user,
            $target instanceof Service => $target->user,
            $target instanceof User    => $target,
            default                    => null,
        };

        if (! $user) {
            throw new \RuntimeException('create.ticket: no user resolved');
        }

        \App\Models\SupportTicket::create([
            'user_id'     => $user->id,
            'subject'     => $config['subject'] ?? 'Automated Ticket',
            'department'  => $config['department'] ?? 'general',
            'priority'    => $config['priority'] ?? 'medium',
            'status'      => 'open',
            'last_reply_at' => now(),
        ]);
    }

    private static function actionSuspendService(array $config, Model $target): void
    {
        $service = $target instanceof Service ? $target : null;

        if (! $service) {
            throw new \RuntimeException('suspend.service: target is not a Service');
        }

        $service->update(['status' => 'suspended']);
    }

    private static function actionAddCredit(array $config, Model $target): void
    {
        $user = match (true) {
            $target instanceof Invoice => $target->user,
            $target instanceof Service => $target->user,
            $target instanceof User    => $target,
            default                    => null,
        };

        if (! $user) {
            throw new \RuntimeException('add.credit: no user resolved');
        }

        $amount      = (float) ($config['amount'] ?? 0);
        $description = $config['description'] ?? 'Automated credit';

        if ($amount <= 0) {
            throw new \RuntimeException('add.credit: amount must be positive');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $amount, $description) {
            \App\Models\ClientCredit::create([
                'user_id'     => $user->id,
                'amount'      => $amount,
                'description' => $description,
            ]);
            $user->increment('credit_balance', $amount);
        });
    }

    private static function actionCallWebhook(array $config, Model $target): void
    {
        $url = $config['url'] ?? null;

        if (! $url) {
            throw new \RuntimeException('call.webhook: no URL configured');
        }

        $payload = array_merge($config['payload'] ?? [], [
            'trigger'     => class_basename($target),
            'target_id'   => $target->getKey(),
            'target_type' => class_basename($target),
        ]);

        $response = Http::timeout(10)->post($url, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException("call.webhook: HTTP {$response->status()} from {$url}");
        }
    }
}
