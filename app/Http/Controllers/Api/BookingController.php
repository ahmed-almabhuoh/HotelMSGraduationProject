<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Services\RoomBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    protected $bookingService;

    public function __construct(RoomBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'sometimes|exists:rooms,id',
            'status' => 'sometimes|in:confirmed,cancelled',
            'check_in_date' => 'sometimes|date',
            'check_out_date' => 'sometimes|date|after_or_equal:check_in_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $filters = $request->only(['room_id', 'status', 'check_in_date', 'check_out_date']);
            $bookings = $this->bookingService->listBookings($filters);
            return BookingResource::collection($bookings);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show($bookingReference)
    {
        try {
            $booking = $this->bookingService->getBooking($bookingReference);
            return new BookingResource($booking);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function update(Request $request, $bookingReference)
    {
        $validator = Validator::make($request->all(), [
            'check_in_date' => 'required|date|after:today',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $booking = $this->bookingService->updateBooking($bookingReference, $request->all());
            return new BookingResource($booking);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy($bookingReference)
    {
        try {
            $booking = $this->bookingService->cancelBooking($bookingReference);
            return new BookingResource($booking);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
