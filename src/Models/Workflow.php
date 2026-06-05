<?php

namespace Fazzinipierluigi\LaravelRails\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Workflow extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'variables' => 'array',
    ];

    public static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public static function getBySlug(?string $slug = null): ?self
    {
        if (empty($slug) || !is_string($slug)) {
            return null;
        }

        return static::where('slug', '=', strtolower(trim($slug)))->first();
    }

    public function states()
    {
        return $this->hasMany(State::class);
    }

    public function instances()
    {
        return $this->hasMany(Instance::class);
    }

    public function getFirst(): ?State
    {
        return State::where('workflow_id', '=', $this->id)
                    ->where('is_start', '=', true)
                    ->first();
    }

    public function instantiate($entity, string $triggeredBy = 'system'): Instance
    {
        $className = get_class($entity);

        if (Instance::where('instanceable_type', '=', $className)
                     ->where('instanceable_id', '=', (string) $entity->getKey())
                     ->where('workflow_id', '=', $this->id)
                     ->count() > 0
        ) {
            throw new \Exception('You have already instantiated this workflow for this entity');
        }

        $firstState = $this->getFirst();
        if (empty($firstState)) {
            throw new \Exception('This workflow has no start state');
        }

        $instance = new Instance();
        $instance->instanceable_type = $className;
        $instance->instanceable_id = (string) $entity->getKey();
        $instance->workflow_id = $this->id;
        $instance->state_id = $firstState->id;
        $instance->save();

        $logger = $instance->logger($triggeredBy);
        $logger->instanceStarted();
        $logger->stateEntered($firstState, 'start');

        $instance->checkAutoAdvance();

        return $instance;
    }
}
