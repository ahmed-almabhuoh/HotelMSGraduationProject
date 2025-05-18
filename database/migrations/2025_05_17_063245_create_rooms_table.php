<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number', 10)->unique();
            $table->enum('type', ['single', 'double', 'suite', 'deluxe']);
            $table->decimal('price_per_night', 8, 2);
            $table->boolean('is_available')->default(true);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('max_occupancy')->default(1);
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
