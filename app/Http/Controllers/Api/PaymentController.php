<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Services\RoomBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    protected $bookingService;

    public function __construct(RoomBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function confirmPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_intent_id' => 'required|string',
            'room_id' => 'required|exists:rooms,id',
            'check_in_date' => 'required|date|after:today',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // try {
        //     $booking = $this->bookingService->confirmPayment(
        //         $request->payment_intent_id,
        //         $request->room_id,
        //         \Carbon\Carbon::parse($request->check_in_date),
        //         \Carbon\Carbon::parse($request->check_out_date)
        //     );
        //     return new BookingResource($booking);
        // } catch (\Exception $e) {
        //     return response()->json(['error' => $e->getMessage()], 400);
        // }
    }
}
