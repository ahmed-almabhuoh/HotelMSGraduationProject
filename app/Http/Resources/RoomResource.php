<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_number' => $this->room_number,
            'type' => $this->type,
            'price_per_night' => $this->price_per_night,
            'is_available' => $this->is_available,
            'description' => $this->description,
            'max_occupancy' => $this->max_occupancy,
            'image_path' => $this->image_path ? asset('storage/' . $this->image_path) : null,
        ];
    }
}
