<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Illuminate\Support\Facades\Validator;
use Stripe\Checkout\Session;

class StripeController extends Controller
{
    //

    public function createCheckoutSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'room_id' => 'required|integer|exists:rooms,id', // assuming you have a rooms table
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $request->amount * 100,
                    'product_data' => [
                        'name' => 'Hotel Reservation for Room #' . $request->room_id,
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $request->success_url ?? url('/success'),
            'cancel_url' => $request->cancel_url ?? url('/cancel'),
        ]);

        return response()->json(['id' => $session->id]);
    }
}
