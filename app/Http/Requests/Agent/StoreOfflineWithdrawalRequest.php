<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfflineWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth middleware already enforced
        return true;
    }

    public function rules(): array
    {
        return [
            // Mongo player ID (ObjectId)
            'mongo_user_id' => [
                'required',
                'string',
                'size:24',
                'regex:/^[a-f0-9]{24}$/i',
            ],

            // Agent wallet to debit
            'wallet_id' => [
                'nullable',
                'integer',
                'exists:wallets,id',
            ],

            // Diamonds to withdraw (agent commission)
            'diamonds_amount' => [
                'required',
                'integer',
                'min:1',
                'max:1000000000',
            ],

            // ❌ REMOVED: payout_cents
            // payout is computed securely in the controller

            // Optional metadata
            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],

            'payout_method' => [
                'nullable',
                'string',
                'in:cash,gcash,bank,other',
            ],


            'reference' => [
                'nullable',
                'string',
                'max:64',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],

            // Optional client-provided idempotency key
            'idempotency_key' => [
                'nullable',
                'uuid',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mongo_user_id.size' =>
                'mongo_user_id must be a 24-character hex string.',

            'mongo_user_id.regex' =>
                'mongo_user_id must be a valid Mongo ObjectId (hex).',
        ];
    }
}
