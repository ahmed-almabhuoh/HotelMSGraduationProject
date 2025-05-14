<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@hotel.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        User::create([
            'name' => 'Regular User',
            'email' => 'user@hotel.com',
            'password' => Hash::make('password'),
            'is_admin' => false,
        ]);

        // API Client
        $apiClient = ApiClient::create([
            'name' => 'External System',
            'is_active' => true,
            'permissions' => ['rooms.index', 'bookings.store'],
        ]);
        $apiClient->token = $apiClient->createToken('External System')->plainTextToken;
        $apiClient->save();

        // API Request Logs
        ApiRequestLog::create([
            'api_client_id' => $apiClient->id,
            'endpoint' => 'api/rooms',
            'method' => 'GET',
            'status_code' => 200,
            'requested_at' => now()->subHour(),
        ]);
    }
}
