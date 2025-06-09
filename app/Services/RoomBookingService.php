<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class RoomBookingService
{
    public function getAvailableRooms(array $filters = [])
    {
        $query = Room::where('is_available', true);

        if (isset($filters['check_in_date']) && isset($filters['check_out_date'])) {
            $checkIn = Carbon::parse($filters['check_in_date']);
            $checkOut = Carbon::parse($filters['check_out_date']);

            if ($checkIn->isPast() || $checkOut->lte($checkIn)) {
                throw new \Exception('Invalid check-in or check-out date.');
            }

            $query->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->where('status', 'confirmed')
                    ->where(function ($subQ) use ($checkIn, $checkOut) {
                        $subQ->whereBetween('check_in_date', [$checkIn, $checkOut])
                            ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                            ->orWhere(function ($q) use ($checkIn, $checkOut) {
                                $q->where('check_in_date', '<=', $checkIn)
                                    ->where('check_out_date', '>=', $checkOut);
                            });
                    });
            });
        }

        if (isset($filters['room_type'])) {
            $query->where('type', $filters['room_type']);
        }

        if (isset($filters['max_occupancy'])) {
            $query->where('max_occupancy', '>=', (int) $filters['max_occupancy']);
        }

        return $query->get();
    }

    public function getAvailableRoom($roomId)
    {
        try {
            return Room::where('id', $roomId)
                ->where('is_available', true)
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new \Exception('Room not found or not available.');
        }
    }

    public function reserveRoom($roomId, array $data)
    {
        try {
            $room = Room::where('id', $roomId)
                ->where('is_available', true)
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new \Exception('Room not found or not available.');
        }

        $checkIn = Carbon::parse($data['check_in_date']);
        $checkOut = Carbon::parse($data['check_out_date']);

        if ($checkIn->isPast() || $checkOut->lte($checkIn)) {
            throw new \Exception('Invalid check-in or check-out date.');
        }

        $existingBooking = Booking::where('room_id', $roomId)
            ->where('status', 'confirmed')
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->whereBetween('check_in_date', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                    ->orWhere(function ($q) use ($checkIn, $checkOut) {
                        $q->where('check_in_date', '<=', $checkIn)
                            ->where('check_out_date', '>=', $checkOut);
                    });
            })
            ->exists();

        if ($existingBooking) {
            throw new \Exception('Room is already booked for the selected dates.');
        }

        $nights = $checkIn->diffInDays($checkOut);
        $totalPrice = $nights * $room->price_per_night;

        $booking = Booking::create([
            'room_id' => $room->id,
            'booking_reference' => Str::uuid()->toString(),
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'total_price' => $totalPrice,
            'status' => 'pending',
        ]);

        // $room->update(['is_available' => false]);

        return $booking;
    }

    public function listBookings(array $filters = [])
    {
        $query = Booking::query();

        if (isset($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['check_in_date'])) {
            $query->whereDate('check_in_date', '>=', Carbon::parse($filters['check_in_date']));
        }

        if (isset($filters['check_out_date'])) {
            $query->whereDate('check_out_date', '<=', Carbon::parse($filters['check_out_date']));
        }

        return $query->get();
    }

    public function getBooking($bookingReference)
    {
        try {
            return Booking::where('booking_reference', $bookingReference)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new \Exception('Booking not found.');
        }
    }

    public function updateBooking($bookingReference, array $data)
    {
        try {
            $booking = Booking::where('booking_reference', $bookingReference)
                ->where('status', 'confirmed')
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new \Exception('Booking not found or not confirmed.');
        }

        if (Carbon::parse($booking->check_in_date)->isPast()) {
            throw new \Exception('Cannot update past bookings.');
        }

        $checkIn = isset($data['check_in_date']) ? Carbon::parse($data['check_in_date']) : $booking->check_in_date;
        $checkOut = isset($data['check_out_date']) ? Carbon::parse($data['check_out_date']) : $booking->check_out_date;

        if ($checkIn->isPast() || $checkOut->lte($checkIn)) {
            throw new \Exception('Invalid check-in or check-out date.');
        }

        $existingBooking = Booking::where('room_id', $booking->room_id)
            ->where('id', '!=', $booking->id)
            ->where('status', 'confirmed')
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->whereBetween('check_in_date', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                    ->orWhere(function ($q) use ($checkIn, $checkOut) {
                        $q->where('check_in_date', '<=', $checkIn)
                            ->where('check_out_date', '>=', $checkOut);
                    });
            })
            ->exists();

        if ($existingBooking) {
            throw new \Exception('Room is already booked for the selected dates.');
        }

        $nights = $checkIn->diffInDays($checkOut);
        $totalPrice = $nights * $booking->room->price_per_night;

        $booking->update([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'total_price' => $totalPrice,
        ]);

        return $booking;
    }

    public function cancelBooking($bookingReference)
    {
        try {
            $booking = Booking::where('booking_reference', $bookingReference)
                ->where('status', 'confirmed')
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new \Exception('Booking not found or not confirmed.');
        }

        if (Carbon::parse($booking->check_in_date)->isPast()) {
            throw new \Exception('Cannot cancel past bookings.');
        }

        $booking->update(['status' => 'cancelled']);
        $booking->room->update(['is_available' => true]);

        return $booking;
    }
}
