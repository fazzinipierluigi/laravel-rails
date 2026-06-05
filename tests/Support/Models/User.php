<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Support\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Minimal authenticatable user for permission and auth tests.
 */
class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];
}
