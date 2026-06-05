<?php

namespace Fazzinipierluigi\LaravelRails\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Fazzinipierluigi\LaravelRails\Contracts\PermissionResolverInterface;

class State extends Model
{
    use HasFactory;

    public const TYPE_SIMPLE      = 'simple';
    public const TYPE_CONDITIONAL = 'conditional';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'is_start'        => 'boolean',
        'is_end'          => 'boolean',
        'view_permissions' => 'array',
    ];

    public function isSimple(): bool      { return ($this->type ?? self::TYPE_SIMPLE) === self::TYPE_SIMPLE; }
    public function isConditional(): bool { return $this->type === self::TYPE_CONDITIONAL; }

    public function canView(mixed $user = null): bool
    {
        $permissions = $this->view_permissions ?? [];
        if (empty($permissions)) {
            return true;
        }

        if ($user === null) {
            $user = auth()->user();
        }
        if ($user === null) {
            return false;
        }

        return app(PermissionResolverInterface::class)
            ->check($user, $permissions, $this->view_operator ?? 'OR');
    }

    public static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        static::deleting(function ($model) {
            $model->actions()->delete();
            $model->transitions()->each(fn ($t) => $t->delete());
        });
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function transitions()
    {
        return $this->hasMany(Transition::class, 'from')->orderBy('sort');
    }

    public function actions()
    {
        return $this->morphMany(Action::class, 'actionable');
    }

    public function next(Instance $instance): ?Transition
    {
        foreach ($this->transitions()->get() as $transition) {
            if ($transition->can($instance)) {
                return $transition;
            }
        }

        return null;
    }

    private function codeCompare(string $code): array|false
    {
        $internal = explode('_', $this->code ?? '');
        $parts = explode('_', $code);

        if (count($internal) === 2 && count($parts) === 2 && $internal[0] === $parts[0]) {
            return [$internal[1], $parts[1]];
        }

        return false;
    }

    public function codeEQ(string $code): bool
    {
        $resp = $this->codeCompare($code);
        return $resp !== false && $resp[0] == $resp[1];
    }

    public function codeGT(string $code): bool
    {
        $resp = $this->codeCompare($code);
        return $resp !== false && $resp[0] > $resp[1];
    }

    public function codeGTE(string $code): bool
    {
        $resp = $this->codeCompare($code);
        return $resp !== false && $resp[0] >= $resp[1];
    }

    public function codeLT(string $code): bool
    {
        $resp = $this->codeCompare($code);
        return $resp !== false && $resp[0] < $resp[1];
    }

    public function codeLTE(string $code): bool
    {
        $resp = $this->codeCompare($code);
        return $resp !== false && $resp[0] <= $resp[1];
    }
}
