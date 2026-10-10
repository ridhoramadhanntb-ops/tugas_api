<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    /** Buat user + token siap pakai. */
    private function userWithToken(string $name = 'Budi'): array
    {
        $user = User::factory()->create(['name' => $name]);

        return [$user, $user->issueApiToken()];
    }

    private function bookFor(User $user, array $attrs = []): Book
    {
        $book = new Book(array_merge(['title' => 'Laskar Pelangi', 'author' => 'Andrea Hirata'], $attrs));
        $book->user_id = $user->id;
        $book->save();

        return $book;
    }

    public function test_register_dan_login(): void
    {
        $res = $this->postJson('/api/register', [
            'name' => 'Ridho', 'email' => 'r@example.com', 'password' => 'rahasia123',
        ])->assertCreated()->assertJsonStructure(['user', 'token']);

        $this->assertArrayNotHasKey('api_token', $res->json('user'));
        $this->assertArrayNotHasKey('password', $res->json('user'));

        $this->postJson('/api/login', ['email' => 'r@example.com', 'password' => 'rahasia123'])
            ->assertOk()->assertJsonStructure(['token']);

        $this->postJson('/api/login', ['email' => 'r@example.com', 'password' => 'salah'])
            ->assertStatus(422);
    }

    public function test_tanpa_token_ditolak_401(): void
    {
        $this->getJson('/api/books')->assertUnauthorized();
        $this->getJson('/api/books', ['Authorization' => 'Bearer ngawur'])->assertUnauthorized();
    }

    public function test_logout_mematikan_token(): void
    {
        [, $token] = $this->userWithToken();
        $h = ['Authorization' => "Bearer $token"];

        $this->getJson('/api/me', $h)->assertOk();
        $this->postJson('/api/logout', [], $h)->assertOk();
        $this->getJson('/api/me', $h)->assertUnauthorized();
    }

    public function test_crud_buku_milik_sendiri(): void
    {
        [, $token] = $this->userWithToken();
        $h = ['Authorization' => "Bearer $token"];

        $id = $this->postJson('/api/books', ['title' => 'Bumi', 'author' => 'Tere Liye', 'year' => 2014], $h)
            ->assertCreated()->json('id');

        $this->getJson('/api/books', $h)->assertOk()->assertJsonCount(1);
        $this->getJson("/api/books/$id", $h)->assertOk()->assertJsonPath('title', 'Bumi');

        $this->putJson("/api/books/$id", ['title' => 'Bulan'], $h)
            ->assertOk()->assertJsonPath('title', 'Bulan')->assertJsonPath('author', 'Tere Liye');

        $this->deleteJson("/api/books/$id", [], $h)->assertOk();
        $this->getJson("/api/books/$id", $h)->assertNotFound();
    }

    public function test_validasi_buku(): void
    {
        [, $token] = $this->userWithToken();
        $this->postJson('/api/books', ['title' => 'tanpa penulis'], ['Authorization' => "Bearer $token"])
            ->assertStatus(422)->assertJsonValidationErrors('author');
    }

    public function test_tidak_bisa_get_edit_delete_buku_user_lain(): void
    {
        [$a] = $this->userWithToken('A');
        [, $tokenB] = $this->userWithToken('B');
        $book = $this->bookFor($a);
        $h = ['Authorization' => "Bearer $tokenB"];

        $this->getJson("/api/books/{$book->id}", $h)->assertForbidden();
        $this->putJson("/api/books/{$book->id}", ['title' => 'Dibajak'], $h)->assertForbidden();
        $this->deleteJson("/api/books/{$book->id}", [], $h)->assertForbidden();

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => 'Laskar Pelangi']);
    }

    public function test_daftar_buku_hanya_milik_sendiri(): void
    {
        [$a, $tokenA] = $this->userWithToken('A');
        [$b] = $this->userWithToken('B');
        $this->bookFor($a, ['title' => 'Buku A']);
        $this->bookFor($b, ['title' => 'Buku B']);

        $this->getJson('/api/books', ['Authorization' => "Bearer $tokenA"])
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Buku A');
    }

    public function test_akses_buku_via_endpoint_user(): void
    {
        [$a, $tokenA] = $this->userWithToken('A');
        [$b] = $this->userWithToken('B');
        $this->bookFor($a);
        $h = ['Authorization' => "Bearer $tokenA"];

        $this->getJson("/api/users/{$a->id}/books", $h)->assertOk()->assertJsonCount(1);
        // Buku milik user lain tidak bisa dilihat lewat endpoint user
        $this->getJson("/api/users/{$b->id}/books", $h)->assertForbidden();
    }

    public function test_tidak_bisa_get_edit_delete_user_lain(): void
    {
        [, $tokenA] = $this->userWithToken('A');
        [$b] = $this->userWithToken('B');
        $h = ['Authorization' => "Bearer $tokenA"];

        $this->getJson("/api/users/{$b->id}", $h)->assertForbidden();
        $this->putJson("/api/users/{$b->id}", ['name' => 'Hacked'], $h)->assertForbidden();
        $this->deleteJson("/api/users/{$b->id}", [], $h)->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $b->id, 'name' => 'B']);
    }

    public function test_user_bisa_edit_dan_hapus_diri_sendiri(): void
    {
        [$a, $token] = $this->userWithToken('A');
        $this->bookFor($a);
        $h = ['Authorization' => "Bearer $token"];

        $this->putJson("/api/users/{$a->id}", ['name' => 'A Baru'], $h)
            ->assertOk()->assertJsonPath('name', 'A Baru');

        $this->deleteJson("/api/users/{$a->id}", [], $h)->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $a->id]);
        $this->assertDatabaseCount('books', 0); // cascade
    }

    public function test_id_tidak_ada_404(): void
    {
        [, $token] = $this->userWithToken();
        $this->getJson('/api/books/9999', ['Authorization' => "Bearer $token"])->assertNotFound();
    }
}
