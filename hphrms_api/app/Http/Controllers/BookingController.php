<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Booking;

class BookingController extends Controller
{
    public function index(): array
    {
        return ['data' => Booking::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'customer_id' => 'required',
            'room_type' => 'required',
            'check_in' => 'required',
            'check_out' => 'required',
            'amount' => 'required',
        ])) {
            return $response;
        }

        $data = $request->all();
        $data['payment_status'] = $data['payment_status'] ?? 'pending';
        $booking = Booking::create($data);
        return $this->json(['data' => $booking], 201);
    }

    public function show(Request $request, array $params)
    {
        $booking = Booking::find((int)$params['id']);
        if (!$booking) {
            return $this->json(['message' => 'Booking not found'], 404);
        }

        return $this->json(['data' => $booking]);
    }

    public function update(Request $request, array $params)
    {
        $booking = Booking::find((int)$params['id']);
        if (!$booking) {
            return $this->json(['message' => 'Booking not found'], 404);
        }

        $updated = Booking::update((int)$params['id'], $request->all());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Booking::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Booking not found'], 404);
        }

        return $this->json(['message' => 'Booking deleted']);
    }

    public function cancel(Request $request, array $params)
    {
        $booking = Booking::find((int)$params['id']);
        if (!$booking) {
            return $this->json(['message' => 'Booking not found'], 404);
        }

        $data = $request->all();
        $updates = [
            'payment_status' => 'cancelled',
        ];
        if (isset($data['refund_amount'])) {
            $updates['refund_amount'] = $data['refund_amount'];
        }

        $updated = Booking::update((int)$params['id'], $updates);
        return $this->json(['message' => 'Booking cancelled', 'data' => $updated]);
    }
}
