<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfflineRechargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already behind auth middleware
        return true;
    }

    public function rules(): array
    {
        return [
            'mongo_user_id' => ['required', 'string', 'size:24', 'regex:/^[a-fA-F0-9]{24}$/'],
            'coins_amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:120'],
            'proof_url' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'mongo_user_id.size' => 'Mongo User ID must be 24 characters.',
            'mongo_user_id.regex' => 'Mongo User ID must be a valid 24-char hex ObjectId.',
        ];
    }
}
