<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfflineWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth middleware already enforced. Add role checks here if needed.
        return true;
    }

    public function rules(): array
    {
        return [
            'mongo_user_id' => ['required', 'string', 'size:24', 'regex:/^[a-f0-9]{24}$/i'],
            'diamonds_amount' => ['required', 'integer', 'min:1', 'max:1000000000'],

            // payout for the agent to give to the player (cash-out) in cents
            'payout_cents' => ['required', 'integer', 'min:0', 'max:100000000000'],

            'currency' => ['nullable', 'string', 'size:3'],
            'payout_method' => ['required', 'string', 'in:cash,gcash,bank,other'],

            'reference' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],

            // optional client-provided idempotency key for retries
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'mongo_user_id.size' => 'mongo_user_id must be a 24-character hex string.',
            'mongo_user_id.regex' => 'mongo_user_id must be a valid Mongo ObjectId (hex).',
        ];
    }
}
