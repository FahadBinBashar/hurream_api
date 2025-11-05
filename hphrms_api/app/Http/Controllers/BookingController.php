<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\Booking;
use App\Support\Validator;

use function array_flip;
use function array_intersect_key;
use function array_merge;
use function in_array;
use function is_numeric;
use function is_string;
use function strtotime;
use function trim;

class BookingController extends Controller
{
    public function index(): array
    {
        return ['data' => Booking::all()];
    }

    private const PAYMENT_METHODS = ['bkash', 'nagad', 'card', 'bank', 'cash'];
    private const STATUSES = ['pending', 'paid', 'cancelled'];

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'customer_id' => 'required|numeric|min:1',
            'room_type' => 'required',
            'guest_count' => 'required|numeric|min:1',
            'check_in' => 'required|date',
            'check_out' => 'required|date',
            'price_plan' => 'required',
            'payment_method' => 'required|in:' . implode(',', self::PAYMENT_METHODS),
            'amount' => 'required|numeric|min:0',
            'payment_status' => 'in:' . implode(',', self::STATUSES),
        ])) {
            return $response;
        }

        $payload = $this->preparePayload($request->all());
        if (!$this->validStayPeriod($payload['check_in'], $payload['check_out'])) {
            return $this->json([
                'message' => 'Validation failed',
                'errors' => ['check_out' => ['Check-out date must be after check-in date.']],
            ], 422);
        }

        $payload['payment_status'] = $payload['payment_status'] ?? 'pending';
        $booking = Booking::create($payload);
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

        $rules = [
            'customer_id' => 'required|numeric|min:1',
            'room_type' => 'required',
            'guest_count' => 'required|numeric|min:1',
            'check_in' => 'required|date',
            'check_out' => 'required|date',
            'price_plan' => 'required',
            'payment_method' => 'required|in:' . implode(',', self::PAYMENT_METHODS),
            'amount' => 'required|numeric|min:0',
            'payment_status' => 'in:' . implode(',', self::STATUSES),
        ];

        $merged = array_merge($booking, $request->all());
        $errors = Validator::make($merged, $rules);
        if (!empty($errors)) {
            return $this->json(['message' => 'Validation failed', 'errors' => $errors], 422);
        }

        $payload = $this->preparePayload($merged);
        if (!$this->validStayPeriod($payload['check_in'], $payload['check_out'])) {
            return $this->json([
                'message' => 'Validation failed',
                'errors' => ['check_out' => ['Check-out date must be after check-in date.']],
            ], 422);
        }

        $updated = Booking::update((int)$params['id'], $payload);
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
        if (isset($data['refund_amount']) && is_numeric($data['refund_amount'])) {
            $updates['refund_amount'] = (float)$data['refund_amount'];
        }

        $updated = Booking::update((int)$params['id'], $updates);
        return $this->json(['message' => 'Booking cancelled', 'data' => $updated]);
    }

    private function preparePayload(array $input): array
    {
        $allowed = [
            'customer_id',
            'room_type',
            'guest_count',
            'check_in',
            'check_out',
            'price_plan',
            'payment_method',
            'amount',
            'payment_status',
            'refund_amount',
        ];

        $filtered = array_intersect_key($input, array_flip($allowed));
        foreach ($filtered as $key => $value) {
            if (is_string($value)) {
                $filtered[$key] = trim($value);
            }
            if (in_array($key, ['customer_id', 'guest_count'], true) && $value !== null) {
                $filtered[$key] = (int)$value;
            }
            if ($key === 'amount' && $value !== null) {
                $filtered[$key] = (float)$value;
            }
        }

        return $filtered;
    }

    private function validStayPeriod(string $checkIn, string $checkOut): bool
    {
        $start = strtotime($checkIn);
        $end = strtotime($checkOut);
        return $start !== false && $end !== false && $end > $start;
    }
}
