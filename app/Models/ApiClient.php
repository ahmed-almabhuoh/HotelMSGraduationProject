<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class ApiClient extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'description',
        'token',
        'is_active',
        'permissions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'permissions' => 'array',
    ];

    public function requestLogs()
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }
}
