<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TopupTransaction;
use App\Models\Wallet;
use App\Models\Settings;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TopupController extends Controller
{
    // Retrieve the Stripe keys from the database, if available
    public function getStripeKeys()
    {
        // Fetch the Stripe keys from the Settings table
        $publicKey = Settings::where('name', 'stripe_public')->first();
        $privateKey = Settings::where('name', 'stripe_private')->first();

        // If no keys are found in the database, fallback to .env
        if ($publicKey && $privateKey) {
            return [
                'public' => $publicKey->value,
                'private' => $privateKey->value,
            ];
        } else {
            return response()->json([
                'error' => 'Stripe keys not found in the database.',
            ], 404);
        }
    }

    /* =====================================================
     | Admin Top-Up an Agent's Wallet (Stripe Integration)
     | POST /admin/topups/agent/{agentId}/stripe
     ===================================================== */
public function adminTopUp(Request $request, $agentId)
{
    $request->validate([
        'amount' => 'required|numeric|min:0.01',
    ]);

    $amount = $request->input('amount');
    $currency = 'usd';

    // Log the incoming request
    Log::info("Received top-up request", ['amount' => $amount, 'agentId' => $agentId]);

    try {
        // Get Stripe keys from the database
        $stripeKeys = $this->getStripeKeys();

        if ($stripeKeys['private'] == null || $stripeKeys['public'] == null) {
            Log::error("Stripe keys are missing");
            return response()->json(['error' => 'Stripe keys are missing or invalid.'], 500);
        }

        Stripe::setApiKey($stripeKeys['private']);

        // Create the PaymentIntent
        $paymentIntent = PaymentIntent::create([
            'amount' => $amount * 100,
            'currency' => $currency,
            'metadata' => ['agent_id' => $agentId],
        ]);

        // Log PaymentIntent creation success
        Log::info("Created PaymentIntent", ['paymentIntentId' => $paymentIntent->id]);

        $topupTransaction = TopupTransaction::create([
            'agent_id' => $agentId,
            'reference' => 'TOPUP-' . uniqid(),
            'amount' => $amount,
            'status' => 'pending',
            'payment_method' => 'stripe',
        ]);

        return response()->json([
            'clientSecret' => $paymentIntent->client_secret,
            'reference' => $topupTransaction->reference, // Pass the reference for frontend
            'paymentIntentId' => $paymentIntent->id,
        ]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Log Stripe-specific error
        Log::error("Stripe API error", ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Stripe payment failed: ' . $e->getMessage()], 500);
    } catch (\Exception $e) {
        // Log general error
        Log::error("General error in processing top-up", ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Something went wrong.'], 500);
    }
}

    /* =====================================================
     | Handle successful payment and update the agent's wallet
     | POST /admin/topups/agent/{agentId}/stripe-complete
     ===================================================== */
public function completeTopUp(Request $request, $agentId)
{
    // Validate the reference and the status from the frontend
    $request->validate([
        'reference' => 'required|string',  // Reference to identify the top-up transaction
        'status' => 'required|string',     // The status passed (succeeded or failed)
    ]);

    $reference = $request->input('reference');
    $status = $request->input('status');  // Status passed from frontend (e.g., 'succeeded')

    // Fetch the top-up transaction by reference
    $topupTransaction = TopupTransaction::where('reference', $reference)->first();

    if (!$topupTransaction) {
        return response()->json(['error' => 'Top-up transaction not found.'], 404);
    }

    // Update the transaction based on the payment status
    if ($status === 'succeeded') {
        $topupTransaction->status = 'completed';
    } else {
        $topupTransaction->status = 'failed';
    }

    $topupTransaction->save();

    // Optionally, update the agent's wallet balance if needed
    if ($status === 'succeeded') {
        // Check if the wallet exists for the agent, create if not
        $wallet = Wallet::where('owner_type', 'agent')
            ->where('owner_id', $agentId)
            ->first();

        if (!$wallet) {
            // If the wallet does not exist, create a new one
            $wallet = Wallet::create([
                'owner_type' => 'agent',
                'owner_id' => $agentId,
                'asset' => 'PHP',  // or other asset type
                'available_cents' => 0,  // initial balance
                'reserved_cents' => 0,   // initial reserved amount
            ]);
        }

        // Update wallet balance
        $wallet->available_cents += $topupTransaction->amount * 100; // Update balance in cents
        $wallet->save();
    }

    return response()->json(['message' => 'Transaction status updated successfully.']);
}

}
