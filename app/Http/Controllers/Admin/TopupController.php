<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TopupTransaction;
use App\Models\Wallet;
use App\Models\AuditLog;
use App\Services\MongoEconomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use RuntimeException;
use App\Models\Settings;

class TopupController extends Controller
{
    private const COINS_PER_USD   = 14000; // 14,000 coins = $1
    private const USD_TO_PHP_RATE = 56;    // $1 = ₱56

    /**
     * Handle the completion of a top-up transaction and update the agent's wallet.
     */
public function completeTopUp(Request $request, $agentId)
{
    // Validate the incoming request
    $request->validate([
        'reference' => 'required|string',  // Reference to identify the top-up transaction
        'status' => 'required|string',     // Status passed from frontend (e.g., 'succeeded')
    ]);

    $reference = $request->input('reference');
    $status = $request->input('status');  // Payment status (succeeded or failed)

    // Fetch the top-up transaction by reference
    $topupTransaction = TopupTransaction::where('reference', $reference)->first();

    if (!$topupTransaction) {
        return response()->json(['error' => 'Top-up transaction not found.'], 404);
    }

    // Update the transaction based on the payment status
    $topupTransaction->status = ($status === 'succeeded') ? 'completed' : 'failed';
    $topupTransaction->save();

    // If the top-up is successful, update the agent's coins in MongoDB
    if ($status === 'succeeded') {
        try {
            // Fetch the agent's MongoDB user ID from the MySQL `user` table
            $user = \App\Models\User::find($agentId); // Assuming $agentId is from MySQL 'users' table

            if (!$user || !$user->mongo_user_id) {
                Log::error("Mongo user ID not found for agent", ['agentId' => $agentId]);
                return response()->json(['error' => 'Agent not found in MySQL.'], 404);
            }

            // Fetch agent's MongoDB data (from agencymembers collection)
            $economyService = new MongoEconomyService();
            $agent = $economyService->getLoggedInAgentWallet($user->mongo_user_id); // Using the mongo_user_id from MySQL

            if ($agent) {
                // Convert USD to PHP and coins (example: 1 USD = 14000 coins)
                $coins = (int) round(($topupTransaction->amount / 100) * 14000); // Example conversion from amount to coins

                // Credit coins to the agent's MongoDB account
                $economyService->creditCoins([
                    'mongo_user_id'   => $agent['mongo_user_id'],  // MongoDB user ID
                    'coins_amount'    => $coins,                     // Amount to credit
                    'idempotency_key' => uniqid(),                   // Ensure idempotency
                    'source'          => 'agent_top_up',             // Indicate source
                    'is_agent'        => true,                       // Specify this is for an agent
                ]);
            } else {
                Log::error("Agent not found in MongoDB", ['mongo_user_id' => $user->mongo_user_id]);
                return response()->json(['error' => 'Agent not found in MongoDB.'], 404);
            }
        } catch (\Exception $e) {
            Log::error("Error updating agent's coins", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error updating agent\'s coins.'], 500);
        }
    }

    return response()->json(['message' => 'Transaction status updated successfully.']);
}
    /**
     * Create a top-up payment intent.
     */
    public function adminTopUp(Request $request, $agentId)
    {
        // Validate the incoming request
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $amount = $request->input('amount');
        $currency = 'usd';

        try {
            // Retrieve Stripe keys
            $stripeKeys = $this->getStripeKeys();
            if (!$stripeKeys || !$stripeKeys['private'] || !$stripeKeys['public']) {
                Log::error("Stripe keys are missing");
                return response()->json(['error' => 'Stripe keys are missing or invalid.'], 500);
            }

            Stripe::setApiKey($stripeKeys['private']);

            // Create a Stripe PaymentIntent
            $paymentIntent = PaymentIntent::create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => $currency,
                'metadata' => ['agent_id' => $agentId],
            ]);

            // Create a top-up transaction record
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
        } catch (\Exception $e) {
            Log::error("Error during Stripe top-up process", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Stripe payment failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve Stripe keys from the database.
     */
    public function getStripeKeys()
    {
        // Fetch the Stripe keys from the Settings table
        $publicKey = Settings::where('name', 'stripe_public')->first();
        $privateKey = Settings::where('name', 'stripe_private')->first();

        if ($publicKey && $privateKey) {
            return [
                'public' => $publicKey->value,
                'private' => $privateKey->value,
            ];
        } else {
            return response()->json(['error' => 'Stripe keys are missing or invalid.'], 404);
        }
    }
}
