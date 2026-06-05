<?php

namespace Fazzinipierluigi\LaravelRails\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Action extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'configuration' => 'array',
    ];

    public static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function actionable()
    {
        return $this->morphTo();
    }

    public function execute(Instance $instance, $entity): bool
    {
        if (empty($this->action) || !class_exists($this->action)) {
            return false;
        }

        $handler = new $this->action;
        return $handler->execute($instance, $entity, $this->configuration, null) === true;
    }
}
