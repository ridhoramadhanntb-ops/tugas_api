<?php

namespace App\Http\Middleware;

use App\Models\Book;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware kepemilikan: pastikan resource di URL milik user yang sedang login.
 *
 * Pemakaian di route:
 *   ->middleware('owner:book')  // {book} harus punya user_id = user login
 *   ->middleware('owner:user')  // {user} harus user login itu sendiri
 *
 * Jika milik user lain -> 403. Jika id tidak ada -> 404.
 */
class EnsureOwner
{
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $model = $request->route($resource);

        // Route model binding mungkin belum berjalan; resolve manual jika masih id mentah.
        if (! is_object($model)) {
            $class = $resource === 'book' ? Book::class : User::class;
            $model = $class::findOrFail($model);
        }

        $ownerId = $model instanceof User ? $model->getKey() : $model->user_id;

        if ($ownerId !== $request->user()->getKey()) {
            return response()->json(['message' => 'Forbidden. Kamu tidak punya akses ke data user lain.'], 403);
        }

        return $next($request);
    }
}
