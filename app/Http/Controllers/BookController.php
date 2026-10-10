<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /** Daftar buku milik user yang login saja. */
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->books()->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $book = $request->user()->books()->create($this->validated($request));

        return response()->json($book, 201);
    }

    // show/update/destroy dilindungi middleware owner:book

    public function show(Book $book): JsonResponse
    {
        return response()->json($book);
    }

    public function update(Request $request, Book $book): JsonResponse
    {
        $book->update($this->validated($request, partial: true));

        return response()->json($book);
    }

    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        return response()->json(['message' => 'Buku dihapus.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'author' => [$required, 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'year' => ['nullable', 'integer', 'between:1000,2100'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
