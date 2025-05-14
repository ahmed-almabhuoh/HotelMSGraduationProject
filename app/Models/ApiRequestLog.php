<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    protected $fillable = [
        'api_client_id',
        'endpoint',
        'method',
        'status_code',
        'requested_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    public function apiClient()
    {
        return $this->belongsTo(ApiClient::class);
    }
}
