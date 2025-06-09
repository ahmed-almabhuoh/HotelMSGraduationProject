<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\RoomResource;
use App\Services\RoomBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use Stripe\Checkout\Session;

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

    // public function reserveRoom(Request $request, $id)
    // {
    //     $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
    //         'id' => 'required|exists:rooms,id',
    //         'check_in_date' => 'required|date|after:today',
    //         'check_out_date' => 'required|date|after:check_in_date',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['errors' => $validator->errors()], 422);
    //     }

    //     try {
    //         $booking = $this->bookingService->reserveRoom($id, $request->all());
    //         return new BookingResource($booking);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 400);
    //     }
    // }
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
            // Step 1: Reserve the room
            $booking = $this->bookingService->reserveRoom($id, $request->all());

            // Step 2: Create Stripe Checkout session
            Stripe::setApiKey(config('services.stripe.secret'));

            $amountInDollars = $booking->total_price ?? 100; // default fallback
            $session = Session::create([
                'payment_method_types' => ['card'],
                'metadata' => [
                    'booking_reference' => $booking['booking_reference'],
                ],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $amountInDollars * 100,
                        'product_data' => [
                            'name' => 'Hotel Reservation - Ref: ' . $booking['booking_reference'],
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('payment.success') . '?booking=' . $booking['booking_reference'],
                'cancel_url' => route('payment.cancel') . '?booking=' . $booking['booking_reference'],
            ]);

            // Optionally save the session ID or URL to the booking record
            // $booking->stripe_session_id = $session->id;
            // $booking->save();

            return response()->json([
                'booking_reference' => $booking['booking_reference'],
                'checkout_url' => $session->url,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
