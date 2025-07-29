<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    //
public function success(Request $request) // 2. Type-hint the Request object
    {
        // Get the 'booking' query parameter from the request
        $bookingId = $request->query('booking');

        Booking::where('booking_reference', $bookingId)->update(['status' => 'confirmed']);

        // You can now use the $bookingId to find the booking and update its status
        // For example: Booking::where('id', $bookingId)->update(['status' => 'paid']);

        // info($bookingId); // This will log the ID to your laravel.log file

        return view('payment.success', ['bookingId' => $bookingId]);
    }

    public function cancel(Request $request)
    {
        $bookingId = $request->query('booking');

        Booking::where('booking_reference', $bookingId)->update(['status' => 'cancelled']);

        return view('payment.cancel');
    }
}
