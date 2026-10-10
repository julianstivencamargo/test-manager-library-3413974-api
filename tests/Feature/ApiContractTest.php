<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Library\Adapters\Outbound\Security\JwtService;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    private string $adminToken;
    private string $librarianToken;
    private User $adminUser;
    private User $librarianUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure database seeder ran and admin exists
        $jwtService = new JwtService();

        $this->adminUser = User::firstOrCreate(
            ['username' => 'admin_test'],
            [
                'id' => Str::uuid()->toString(),
                'password_hash' => Hash::make('Secret123*'),
                'role' => 'admin',
            ]
        );

        $this->librarianUser = User::firstOrCreate(
            ['username' => 'librarian_test'],
            [
                'id' => Str::uuid()->toString(),
                'password_hash' => Hash::make('Secret123*'),
                'role' => 'librarian',
            ]
        );

        $this->adminToken = $jwtService->generateToken($this->adminUser->id, 'admin');
        $this->librarianToken = $jwtService->generateToken($this->librarianUser->id, 'librarian');
    }

    public function test_auth_login_with_valid_and_invalid_credentials(): void
    {
        // 1. Invalid credentials
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin_test',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Credenciales inválidas.',
                'details' => [],
            ]);

        // 2. Valid credentials
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin_test',
            'password' => 'Secret123*',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'role',
            ])
            ->assertJson([
                'token_type' => 'Bearer',
                'expires_in' => 28800,
                'role' => 'admin',
            ]);
    }

    public function test_create_librarian_user(): void
    {
        $uniqueUser = 'lib_' . Str::random(6);

        // 1. Without token -> 401
        $this->postJson('/api/v1/users', [
            'username' => $uniqueUser,
            'password' => 'Pass1234*',
            'role' => 'librarian',
        ])->assertStatus(401)
            ->assertJson(['code' => 'INVALID_CREDENTIALS']);

        // 2. With librarian token -> 403
        $this->withToken($this->librarianToken)
            ->postJson('/api/v1/users', [
                'username' => $uniqueUser,
                'password' => 'Pass1234*',
                'role' => 'librarian',
            ])->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN_ROLE']);

        // 3. Requesting admin role -> 422
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/users', [
                'username' => $uniqueUser,
                'password' => 'Pass1234*',
                'role' => 'admin',
            ])->assertStatus(422)
            ->assertJson(['code' => 'VALIDATION_ERROR']);

        // 4. Valid librarian creation -> 201
        $res = $this->withToken($this->adminToken)
            ->postJson('/api/v1/users', [
                'username' => '  ' . strtoupper($uniqueUser) . '  ',
                'password' => 'Pass1234*',
                'role' => 'librarian',
            ]);

        $res->assertStatus(201)
            ->assertJson([
                'username' => strtolower($uniqueUser),
                'role' => 'librarian',
            ]);

        $this->assertArrayNotHasKey('password', $res->json());
        $this->assertArrayNotHasKey('password_hash', $res->json());

        // 5. Duplicate username -> 409
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/users', [
                'username' => $uniqueUser,
                'password' => 'Pass1234*',
                'role' => 'librarian',
            ])->assertStatus(409)
            ->assertJson(['code' => 'USERNAME_TAKEN']);
    }

    public function test_categories_endpoint(): void
    {
        $res = $this->withToken($this->librarianToken)->getJson('/api/v1/categories');

        $res->assertStatus(200);
        $this->assertCount(5, $res->json());
    }

    public function test_book_crud_and_invariants(): void
    {
        $category = Category::first();
        $isbn = '978' . str_pad((string) rand(1000000000, 9999999999), 10, '0');

        // 1. Create book
        $res = $this->withToken($this->adminToken)->postJson('/api/v1/books', [
            'title' => 'El Quijote',
            'author' => 'Miguel de Cervantes',
            'isbn' => $isbn,
            'category_id' => $category->id,
            'total_copies' => 3,
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'title' => 'El Quijote',
                'total_copies' => 3,
                'available_copies' => 3,
                'cover_key' => null,
            ]);

        $bookId = $res->json('id');

        // 2. Duplicate ISBN -> 409
        $this->withToken($this->adminToken)->postJson('/api/v1/books', [
            'title' => 'Otro Libro',
            'author' => 'Otro Autor',
            'isbn' => $isbn,
            'category_id' => $category->id,
            'total_copies' => 1,
        ])->assertStatus(409)
            ->assertJson(['code' => 'ISBN_TAKEN']);

        // 3. Update book
        $updateRes = $this->withToken($this->adminToken)->putJson("/api/v1/books/{$bookId}", [
            'title' => 'Don Quijote de la Mancha',
            'author' => 'Miguel de Cervantes',
            'category_id' => $category->id,
            'total_copies' => 4,
        ]);

        $updateRes->assertStatus(200)
            ->assertJson([
                'title' => 'Don Quijote de la Mancha',
                'total_copies' => 4,
                'available_copies' => 4,
            ]);

        // 4. Search books
        $searchRes = $this->withToken($this->librarianToken)->getJson('/api/v1/books?q=Quijote');
        $searchRes->assertStatus(200)
            ->assertJsonStructure(['items', 'page', 'size', 'total']);
        $this->assertGreaterThanOrEqual(1, $searchRes->json('total'));
    }

    public function test_member_crud_and_duplicate(): void
    {
        $doc = 'DOC_' . Str::random(8);

        $res = $this->withToken($this->librarianToken)->postJson('/api/v1/members', [
            'full_name' => 'Carlos Pérez',
            'document_number' => $doc,
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'full_name' => 'Carlos Pérez',
                'document_number' => $doc,
            ]);

        // Duplicate document -> 409
        $this->withToken($this->librarianToken)->postJson('/api/v1/members', [
            'full_name' => 'Carlos Pérez Duplicado',
            'document_number' => $doc,
        ])->assertStatus(409)
            ->assertJson(['code' => 'DOCUMENT_TAKEN']);
    }

    public function test_complete_loan_and_return_cycle(): void
    {
        $category = Category::first();
        $isbn1 = '978' . str_pad((string) rand(1000000000, 9999999999), 10, '0');
        $isbn2 = '978' . str_pad((string) rand(1000000000, 9999999999), 10, '0');

        $book1 = $this->withToken($this->adminToken)->postJson('/api/v1/books', [
            'title' => 'Book 1',
            'author' => 'Author 1',
            'isbn' => $isbn1,
            'category_id' => $category->id,
            'total_copies' => 2,
        ])->json();

        $book2 = $this->withToken($this->adminToken)->postJson('/api/v1/books', [
            'title' => 'Book 2',
            'author' => 'Author 2',
            'isbn' => $isbn2,
            'category_id' => $category->id,
            'total_copies' => 1,
        ])->json();

        $member = $this->withToken($this->librarianToken)->postJson('/api/v1/members', [
            'full_name' => 'María Gomez',
            'document_number' => 'DOC_' . Str::random(8),
        ])->json();

        // 1. Duplicate book in same loan -> 422
        $this->withToken($this->librarianToken)->postJson('/api/v1/loans', [
            'member_id' => $member['id'],
            'items' => [
                ['book_id' => $book1['id']],
                ['book_id' => $book1['id']],
            ],
        ])->assertStatus(422)
            ->assertJson(['code' => 'DUPLICATE_BOOK_IN_LOAN']);

        // 2. Successful loan
        $loanRes = $this->withToken($this->librarianToken)->postJson('/api/v1/loans', [
            'member_id' => $member['id'],
            'items' => [
                ['book_id' => $book1['id']],
                ['book_id' => $book2['id']],
            ],
        ]);

        $loanRes->assertStatus(201)
            ->assertJson([
                'member_id' => $member['id'],
                'status' => 'open',
            ]);

        $this->assertCount(2, $loanRes->json('items'));

        $loanId = $loanRes->json('id');
        $itemId1 = $loanRes->json('items.0.id');

        // Check book2 now has 0 available copies
        $book2Check = $this->withToken($this->librarianToken)->getJson("/api/v1/books/{$book2['id']}")->json();
        $this->assertSame(0, $book2Check['available_copies']);

        // 3. Loaning book2 again -> 409 BOOK_NOT_AVAILABLE
        $otherMember = $this->withToken($this->librarianToken)->postJson('/api/v1/members', [
            'full_name' => 'Pedro Lopez',
            'document_number' => 'DOC_' . Str::random(8),
        ])->json();

        $this->withToken($this->librarianToken)->postJson('/api/v1/loans', [
            'member_id' => $otherMember['id'],
            'items' => [
                ['book_id' => $book2['id']],
            ],
        ])->assertStatus(409)
            ->assertJson(['code' => 'BOOK_NOT_AVAILABLE']);

        // 4. Return item 1
        $returnRes = $this->withToken($this->librarianToken)->postJson("/api/v1/loans/{$loanId}/items/{$itemId1}/return");
        $returnRes->assertStatus(200);
        $this->assertNotNull($returnRes->json('returned_at'));

        // 5. Returning already returned item -> 409 ITEM_ALREADY_RETURNED
        $this->withToken($this->librarianToken)
            ->postJson("/api/v1/loans/{$loanId}/items/{$itemId1}/return")
            ->assertStatus(409)
            ->assertJson(['code' => 'ITEM_ALREADY_RETURNED']);
    }

    public function test_reports_most_borrowed(): void
    {
        // 1. Invalid date range (to < from) -> 422
        $this->withToken($this->adminToken)->getJson('/api/v1/reports/most-borrowed?from=2026-10-31&to=2026-10-01')
            ->assertStatus(422)
            ->assertJson(['code' => 'INVALID_DATE_RANGE']);

        // 2. Valid report -> 200
        $res = $this->withToken($this->adminToken)->getJson('/api/v1/reports/most-borrowed?from=2026-01-01&to=2026-12-31');
        $res->assertStatus(200)
            ->assertJsonStructure([
                'from',
                'to',
                'rows',
            ]);
    }
}
