<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;
use App\Models\Booking;
use App\Models\Room;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET'); // Get this from your Stripe dashboard

        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle successful payment
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            // Find booking by reference stored in success URL (optional) or metadata (recommended)
            // Here assuming you passed booking_reference in metadata when creating session
            $bookingReference = $session->metadata->booking_reference ?? null;

            if ($bookingReference) {
                $booking = Booking::where('booking_reference', $bookingReference)->first();

                if ($booking) {
                    // Update booking status
                    $booking->status = 'confirmed';
                    $booking->save();

                    // Update room availability
                    $room = Room::find($booking->room_id);
                    if ($room) {
                        $room->is_available = false;
                        $room->save();
                    }
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}
