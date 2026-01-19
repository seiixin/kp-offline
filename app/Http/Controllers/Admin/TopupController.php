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
        // Validation: Make sure the amount is valid
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $amount = $request->input('amount');
        $currency = 'usd'; // You can change this to the desired currency (e.g., PHP)

        // Get Stripe keys from the database
        $stripeKeys = $this->getStripeKeys();
        
        if ($stripeKeys['private'] == null || $stripeKeys['public'] == null) {
            return response()->json([
                'error' => 'Stripe keys are missing or invalid.',
            ], 500);
        }

        // Set Stripe's secret key for backend interaction
        Stripe::setApiKey($stripeKeys['private']);

        try {
            // Create a PaymentIntent (Stripe's way to handle payments)
            $paymentIntent = PaymentIntent::create([
                'amount'   => $amount * 100, // Convert to cents (Stripe requires amount in cents)
                'currency' => $currency,
                'metadata' => ['agent_id' => $agentId], // Metadata for the agent
            ]);

            // Save the top-up transaction as 'pending' initially
            $topupTransaction = TopupTransaction::create([
                'agent_id'      => $agentId,
                'reference'     => 'TOPUP-' . uniqid(),
                'amount'        => $amount,
                'status'        => 'pending', // Transaction status is 'pending' until payment is confirmed
                'payment_method' => 'stripe', // Payment method used
            ]);

            // Send the public key, clientSecret, and other relevant information to the frontend
            return response()->json([
                'clientSecret' => $paymentIntent->client_secret, // Pass the client secret to frontend to complete payment
                'paymentIntentId' => $paymentIntent->id, // Send the PaymentIntent ID as well
                'publicKey' => $stripeKeys['public'], // Include the public key in the response
                'topupTransaction' => $topupTransaction, // Return the transaction object for tracking
            ]);
        } catch (\Exception $e) {
            // Log Stripe API errors
            Log::error('Stripe PaymentIntent creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Payment creation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* =====================================================
     | Handle successful payment and update the agent's wallet
     | POST /admin/topups/agent/{agentId}/stripe-complete
     ===================================================== */
public function completeTopUp(Request $request, $agentId)
{
    // Validate the incoming request for paymentIntentId
    $request->validate([
        'paymentIntentId' => 'required|string', // Ensure paymentIntentId is provided
    ]);

    $paymentIntentId = $request->input('paymentIntentId');

    // Retrieve Stripe keys from the database or fallback to .env
    $stripeKeys = $this->getStripeKeys();

    if (!$stripeKeys['private'] || !$stripeKeys['public']) {
        return response()->json([
            'error' => 'Stripe private/public keys are missing or invalid.',
        ], 500);
    }

    // Set the Stripe API key
    Stripe::setApiKey($stripeKeys['private']); // Use secret key for backend operations

    try {
        // Retrieve the PaymentIntent object from Stripe using the payment intent ID
        $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

        // Log payment intent status for debugging
        Log::info("PaymentIntent Status: {$paymentIntent->status}");
        Log::info("PaymentIntent ID: {$paymentIntent->id}");

        // Proceed if the payment was successful
        if ($paymentIntent->status === 'succeeded') {
            // Fetch the agent's PHP wallet
            $wallet = Wallet::where('owner_type', 'agent')
                ->where('owner_id', $agentId)
                ->whereRaw('UPPER(asset) = ?', ['PHP'])
                ->firstOrFail();

            // Calculate the amount in dollars (Stripe returns amount in cents)
            $amount = $paymentIntent->amount_received / 100; // Convert from cents to dollars

            // Add the top-up amount to the wallet
            $wallet->available_cents += $amount * 100; // Convert to cents
            $wallet->reserved_cents += $amount * 100; // Add to reserved portion if needed
            $wallet->save(); // Save the updated wallet balance

            // Retrieve the top-up transaction to update its status
            $topupTransaction = TopupTransaction::where('reference', 'TOPUP-' . $paymentIntent->metadata['agent_id'])
                ->first();

            if (!$topupTransaction) {
                return response()->json([
                    'error' => 'Top-up transaction not found.',
                ], 404);
            }

            // Update the top-up transaction status to 'completed'
            $topupTransaction->status = 'completed';
            $topupTransaction->save();

            // Return success response with updated balance information
            return response()->json([
                'message' => 'Top-up successful.',
                'data' => [
                    'agent_id' => $agentId,
                    'topup_amount' => $amount,
                    'new_balance' => $wallet->available_cents / 100, // Convert back to dollars for display
                ],
            ], 200);
        } else {
            // If payment failed, return an error response
            return response()->json(['error' => 'Payment failed. Please try again.'], 400);
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Stripe API specific error handling
        Log::error('Stripe API Error: ' . $e->getMessage());
        return response()->json([
            'error' => 'Stripe API Error: ' . $e->getMessage(),
        ], 500);
    } catch (\Exception $e) {
        // General error handling for any other issues
        Log::error('General Error: ' . $e->getMessage());
        return response()->json([
            'error' => 'An unexpected error occurred: ' . $e->getMessage(),
        ], 500);
    }
}
}
