<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\RoomResource;
use App\Services\RoomBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomBookingController extends Controller
{
    protected $bookingService;

    public function __construct(RoomBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function listAvailableRooms(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'check_in_date' => 'sometimes|date|after_or_equal:today',
            'check_out_date' => 'sometimes|date|after:check_in_date',
            'room_type' => 'sometimes|in:single,double,suite,deluxe',
            'max_occupancy' => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $filters = $request->only(['check_in_date', 'check_out_date', 'room_type', 'max_occupancy']);
            $rooms = $this->bookingService->getAvailableRooms($filters);
            return RoomResource::collection($rooms);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function showRoom(Request $request, $id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|exists:rooms,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $room = $this->bookingService->getAvailableRoom($id);
            return new RoomResource($room);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function reserveRoom(Request $request, $id)
    {
        $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
            'id' => 'required|exists:rooms,id',
            'check_in_date' => 'required|date|after:today',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $booking = $this->bookingService->reserveRoom($id, $request->all());
            return new BookingResource($booking);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
