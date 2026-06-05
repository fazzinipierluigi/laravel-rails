<?php

namespace Fazzinipierluigi\LaravelRails\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExecutionLog extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'id';
    protected $keyType    = 'string';
    public $incrementing  = false;

    protected $casts = [
        'data'        => 'array',
        'occurred_at' => 'datetime',
    ];

    public static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->occurred_at)) {
                $model->occurred_at = now();
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function instance()
    {
        return $this->belongsTo(Instance::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeForInstance($query, string $instanceId)
    {
        return $query->where('instance_id', $instanceId);
    }

    public function scopeForSubject($query, string $type, string $id)
    {
        return $query->where('subject_type', $type)->where('subject_id', $id);
    }

    public function scopeForState($query, string $stateId)
    {
        return $this->scopeForSubject($query, 'state', $stateId);
    }

    public function scopeForTransition($query, string $transitionId)
    {
        return $this->scopeForSubject($query, 'transition', $transitionId);
    }

    public function scopeOfEvent($query, string $event)
    {
        return $query->where('event', $event);
    }
}
