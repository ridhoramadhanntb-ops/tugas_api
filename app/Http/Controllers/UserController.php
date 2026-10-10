<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Semua route di sini dilindungi middleware owner:user,
 * jadi {user} selalu sama dengan user yang login.
 */
class UserController extends Controller
{
    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
        ]);

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete(); // buku ikut terhapus (cascade)

        return response()->json(['message' => 'Akun dihapus.']);
    }

    public function books(User $user): JsonResponse
    {
        return response()->json($user->books()->latest()->get());
    }
}
