<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * メール/パスワードを検証し、Sanctum の Bearer トークンを発行する。
     */
    public function store(TokenRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            // 資格情報不正は 422（ValidationException）。401 は保護リソース用に温存
            throw ValidationException::withMessages([
                'email' => ['認証情報が正しくありません。'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('api-token')->plainTextToken,
        ]);
    }
}
