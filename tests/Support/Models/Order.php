<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Test entity used as the instanceable subject in workflow tests.
 */
class Order extends Model
{
    protected $keyType    = 'string';
    public $incrementing  = false;

    protected $fillable = ['status', 'amount', 'customer_name'];

    public static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
