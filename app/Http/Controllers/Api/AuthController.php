<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الاعتماد غير صحيحة'], 401);
        }

        if ($user->isStoreOwner() && ! $user->store?->isActive()) {
            return response()->json(['message' => 'الحساب غير نشط. يرجى الاتصال بالدعم.'], 403);
        }
        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,

            'user'         => [
                'name'     => $user->name,
                'email'    => $user->email,
                'role'     => $user->role,

            ],
            'store' => $user->isStoreOwner() ? [
                'id'       => $user->store?->id,
                'name'     => $user->store?->name,
                'logo_url' => $user->store?->logo_path
                    ? asset('storage/' . $user->store->logo_path)
                    : null,
                'slug'     => $user->store?->slug,
            ] : null,
        ]);
    }

    public function logout(): JsonResponse
    {
        $token = Auth::user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }
    public function me(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'id'    => $user->id,
            'store_id' => $user->store_id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ]);
    }
}
