<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfflineWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $user = $this->user();
        $agentId = (int) ($user->agent_id ?? $user->id);

        return [
            'wallet_id' => [
                'required',
                'integer',
                Rule::exists('wallets', 'id')->where(function ($q) use ($agentId) {
                    $q->where('owner_type', 'agent')
                      ->where('owner_id', $agentId)
                      ->whereRaw('UPPER(asset) = ?', ['DIAMONDS']);
                }),
            ],

            'mongo_user_id' => [
                'required',
                'string',
                'size:24',
                'regex:/^[a-f0-9]{24}$/i',
            ],

            'diamonds_amount' => [
                'required',
                'integer',
                'min:112000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'wallet_id.required' => 'Wallet is required.',
            'wallet_id.exists' => 'Wallet not found or invalid.',
            'mongo_user_id.size' => 'Invalid user id.',
            'mongo_user_id.regex' => 'Invalid user id.',
            'diamonds_amount.min' => 'Minimum withdrawal is $10.',
        ];
    }
}
