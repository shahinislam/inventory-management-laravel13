<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create / update / delete of the auditable domain models.
 *
 * Registered per-model in AppServiceProvider rather than globally, so noisy or
 * internal tables (ActivityLog itself, cache, jobs) never feed back into the log.
 */
class AuditObserver
{
    /**
     * Attributes never worth storing in an audit row — either noise or secrets.
     *
     * @var array<int, string>
     */
    private const IGNORED = [
        'created_at', 'updated_at', 'deleted_at',
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    public function created(Model $model): void
    {
        ActivityLog::log(
            action: 'created',
            model: $model,
            newValues: $this->clean($model->getAttributes()),
            description: $this->describe($model, 'created'),
        );
    }

    public function updated(Model $model): void
    {
        $changes = $this->clean($model->getChanges());

        // A save that touched nothing but timestamps is not worth a row.
        if ($changes === []) {
            return;
        }

        ActivityLog::log(
            action: 'updated',
            model: $model,
            oldValues: array_intersect_key($this->clean($model->getOriginal()), $changes),
            newValues: $changes,
            description: $this->describe($model, 'updated'),
        );
    }

    public function deleted(Model $model): void
    {
        ActivityLog::log(
            action: 'deleted',
            model: $model,
            oldValues: $this->clean($model->getAttributes()),
            description: $this->describe($model, 'deleted'),
        );
    }

    /** Drop ignored attributes before they reach the log. */
    private function clean(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::IGNORED));
    }

    /** A short human-readable summary, e.g. "Product created: Blue Widget". */
    private function describe(Model $model, string $action): string
    {
        $label = class_basename($model);

        $name = $model->getAttribute('name')
            ?? $model->getAttribute('invoice_number')
            ?? $model->getAttribute('order_number')
            ?? $model->getKey();

        return "{$label} {$action}: {$name}";
    }
}
