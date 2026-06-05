<?php

namespace Fazzinipierluigi\LaravelRails\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trigger types
 *  'scheduled'    — fires on a cron schedule; configuration: {cron, entity_class, entity_scope?}
 *  'manual'       — fires via HTTP endpoint or programmatic call; configuration: {entity_class, label, permission?}
 *  'entity_event' — fires when an entity is created/updated; configuration: {entity_class, event, conditions?}
 */
class WorkflowTrigger extends Model
{
    use HasFactory;

    public const TYPE_SCHEDULED    = 'scheduled';
    public const TYPE_MANUAL       = 'manual';
    public const TYPE_ENTITY_EVENT = 'entity_event';

    protected $primaryKey = 'id';
    protected $keyType    = 'string';
    public $incrementing  = false;

    protected $casts = [
        'configuration' => 'array',
        'is_active'     => 'boolean',
        'last_run_at'   => 'datetime',
    ];

    public static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    // ── Type helpers ───────────────────────────────────────────────────

    public function isScheduled(): bool    { return $this->type === self::TYPE_SCHEDULED; }
    public function isManual(): bool       { return $this->type === self::TYPE_MANUAL; }
    public function isEntityEvent(): bool  { return $this->type === self::TYPE_ENTITY_EVENT; }

    public function getEntityClass(): ?string
    {
        return $this->configuration['entity_class'] ?? null;
    }

    public function getLabel(): string
    {
        return $this->configuration['label'] ?? $this->name;
    }

    public function getPermission(): ?string
    {
        return $this->configuration['permission'] ?? null;
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ── Fire ───────────────────────────────────────────────────────────

    public function fire($entity): Instance
    {
        return $this->workflow->instantiate($entity);
    }

    // ── Cron helper (scheduled type) ───────────────────────────────────

    public function isDue(?\DateTimeInterface $now = null): bool
    {
        if (!$this->isScheduled()) {
            return false;
        }

        $cron = $this->configuration['cron'] ?? null;
        if (empty($cron)) {
            return false;
        }

        try {
            $expression = new \Cron\CronExpression($cron);
            return $expression->isDue($now ?? 'now');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ── Condition check (entity_event type) ───────────────────────────

    public function matchesConditions($entity): bool
    {
        $conditions = $this->configuration['conditions'] ?? null;
        if (empty($conditions)) {
            return true;
        }

        try {
            $data = ['entity' => $entity->toArray(), 'request' => request()->all()];
            return (bool) \JWadhams\JsonLogic::apply($conditions, $data);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
