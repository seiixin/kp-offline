<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\MongoEconomyService;
use Illuminate\Http\Request;

class UserDropdownController extends Controller
{
    public function index(Request $request, MongoEconomyService $mongoEconomy)
    {
        $validated = $request->validate([
            'q'     => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return response()->json([
            'data' => $mongoEconomy->listUsersForDropdown([
                'q'     => $validated['q'] ?? null,
                'limit' => $validated['limit'] ?? 20,
            ]),
        ]);
    }
}
