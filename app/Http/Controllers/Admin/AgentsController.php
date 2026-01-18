<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MongoEconomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AgentsController extends Controller
{
    /* =====================================================
     | LIST AGENTS / ADMINS (ORC USERS)
     ===================================================== */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->whereIn('role', ['agent', 'admin'])
            ->latest();

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->paginate(20)
        );
    }

    /* =====================================================
     | CREATE AGENT / ADMIN
     ===================================================== */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'email'          => ['required', 'email', 'max:191', 'unique:users,email'],
            'password'       => ['required', 'string', 'min:8'],
            'role'           => ['required', Rule::in(['agent', 'admin'])],
            'mongo_user_id'  => ['nullable', 'string', 'size:24'],
        ]);

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'role'          => $data['role'],
            'mongo_user_id' => $data['mongo_user_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'User created.',
            'user'    => $user,
        ], 201);
    }

    /* =====================================================
     | UPDATE AGENT / ADMIN
     ===================================================== */
    public function update(Request $request, User $user): JsonResponse
    {
        if ($user->role !== 'agent' && $user->role !== 'admin') {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => [
                'sometimes',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string'],
            'role' => ['sometimes', Rule::in(['agent', 'admin'])],
            'mongo_user_id' => ['nullable', 'string', 'size:24'],
        ]);

        /* ===============================
        PASSWORD (ignore empty string)
        =============================== */
        if (array_key_exists('password', $data)) {
            if (trim($data['password']) === '') {
                unset($data['password']);
            } else {
                $data['password'] = Hash::make($data['password']);
            }
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated.',
            'user' => $user->fresh(),
        ]);
    }


    /* =====================================================
     | DELETE AGENT (NOT ADMIN)
     ===================================================== */
public function destroy(User $user): JsonResponse
{
    $auth = auth()->user();

    // Safety: prevent self-delete
    if ($auth->id === $user->id) {
        return response()->json([
            'message' => 'You cannot delete your own account.',
        ], 403);
    }

    // Allow deleting only admin or agent
    if (!in_array($user->role, ['admin', 'agent'], true)) {
        return response()->json([
            'message' => 'Only admin or agent accounts can be deleted.',
        ], 403);
    }

    $user->delete();

    return response()->json([
        'message' => 'User deleted successfully.',
    ]);
}

    /* =====================================================
     | DROPDOWN: AGENCY MEMBERS (MONGO)
     ===================================================== */
    public function agencyMembersDropdown(
        Request $request,
        MongoEconomyService $mongo
    ): JsonResponse {
        return response()->json(
            $mongo->listAgencyMembersForDropdown([
                'q'     => $request->query('q'),
                'limit' => 50,
            ])
        );
    }
}
