<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class OfflinePosAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $login = trim((string) $validated['login']);
        $userTable = (new User())->getTable();
        $searchable = array_values(array_filter(
            ['email', 'phone', 'user_id'],
            fn (string $column) => Schema::hasColumn($userTable, $column)
        ));

        if (!$searchable) {
            return response()->json([
                'status' => false,
                'message' => 'No supported login field is available on the server.',
            ], 422);
        }

        $user = User::query()
            ->where(function ($query) use ($searchable, $login) {
                foreach ($searchable as $index => $column) {
                    $index === 0
                        ? $query->where($column, $login)
                        : $query->orWhere($column, $login);
                }
            })
            ->first();

        if (!$user || !Hash::check((string) $validated['password'], (string) $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid login credentials.',
            ], 401);
        }

        if (Schema::hasColumn($userTable, 'status')) {
            $status = strtolower(trim((string) $user->status));
            if (in_array($status, ['0', 'inactive', 'disabled', 'blocked'], true)) {
                return response()->json([
                    'status' => false,
                    'message' => 'This user account is not active.',
                ], 403);
            }
        }

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'user' => [
                'server_id' => $user->id,
                'user_id' => $user->user_id ?? null,
                'name' => $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'email' => $user->email ?? null,
                'phone' => $user->phone ?? null,
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [],
                'permissions' => method_exists($user, 'getAllPermissions')
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
            ],
        ]);
    }
}
