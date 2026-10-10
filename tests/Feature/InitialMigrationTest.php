<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InitialMigrationTest extends TestCase
{
    /**
     * Verifica explícitamente que la extensión pdo_pgsql esté cargada en el runtime PHP del contenedor.
     */
    public function test_pdo_pgsql_extension_is_loaded(): void
    {
        $this->assertTrue(
            extension_loaded('pdo_pgsql'),
            'La extensión pdo_pgsql debe estar instalada y cargada en el contenedor PHP.'
        );
    }

    /**
     * Verifica que la tabla de control de migraciones esté en 'public' y no en 'library'.
     */
    public function test_migrations_table_is_in_public_schema(): void
    {
        $publicMigrations = DB::select("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public' AND table_name = 'migrations';
        ");
        $this->assertCount(1, $publicMigrations, 'La tabla migrations debe existir en el esquema public.');

        $libraryMigrations = DB::select("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'library' AND table_name = 'migrations';
        ");
        $this->assertCount(0, $libraryMigrations, 'La tabla migrations NO debe estar en el esquema library.');
    }

    /**
     * Comprueba que existen exactamente las seis tablas de negocio dentro del esquema 'library'.
     */
    public function test_all_six_business_tables_exist_exclusively_in_library_schema(): void
    {
        $tables = DB::select("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'library' AND table_type = 'BASE TABLE'
            ORDER BY table_name;
        ");

        $tableNames = array_map(fn($t) => $t->table_name, $tables);
        $expectedTables = ['book', 'category', 'loan', 'loan_item', 'member', 'user'];
        sort($expectedTables);

        $this->assertEquals($expectedTables, $tableNames);
    }

    /**
     * Comprueba detalladamente las columnas, tipos de datos, nulabilidad y ausencia total de DEFAULT en 'library'.
     */
    public function test_all_columns_types_nullability_and_no_defaults(): void
    {
        $columns = DB::select("
            SELECT table_name, column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_schema = 'library'
            ORDER BY table_name, ordinal_position;
        ");

        $this->assertNotEmpty($columns);

        // Regla innegociable: CERO defaults
        foreach ($columns as $col) {
            $this->assertNull(
                $col->column_default,
                "La columna '{$col->table_name}.{$col->column_name}' no debe tener valor DEFAULT definido."
            );
        }

        // Mapeo esperado de columnas según data-model.md §3
        $expectedColumns = [
            'category' => [
                'id' => ['type' => 'uuid', 'nullable' => 'NO'],
                'name' => ['type' => 'character varying', 'nullable' => 'NO'],
            ],
            'book' => [
                'id' => ['type' => 'uuid', 'nullable' => 'NO'],
                'title' => ['type' => 'character varying', 'nullable' => 'NO'],
                'author' => ['type' => 'character varying', 'nullable' => 'NO'],
                'isbn' => ['type' => 'character', 'nullable' => 'NO'],
                'category_id' => ['type' => 'uuid', 'nullable' => 'NO'],
                'total_copies' => ['type' => 'integer', 'nullable' => 'NO'],
                'available_copies' => ['type' => 'integer', 'nullable' => 'NO'],
                'cover_key' => ['type' => 'character varying', 'nullable' => 'YES'],
                'deleted_at' => ['type' => 'timestamp with time zone', 'nullable' => 'YES'],
            ],
            'member' => [
                'id' => ['type' => 'uuid', 'nullable' => 'NO'],
                'full_name' => ['type' => 'character varying', 'nullable' => 'NO'],
                'document_number' => ['type' => 'character varying', 'nullable' => 'NO'],
            ],
            'user' => [
                'id' => ['type' => 'uuid', 'nullable' => 'NO'],
                'username' => ['type' => 'character varying', 'nullable' => 'NO'],
                'password_hash' => ['type' => 'character varying', 'nullable' => 'NO'],
                'role' => ['type' => 'character varying', 'nullable' => 'NO'],
            ],
            'loan' => [
                'id' => ['type' => 'uuid', 'nullable' => 'NO'],
                'member_id' => ['type' => 'uuid', 'nullable' => 'NO'],
                'loaned_by_user_id' => ['type' => 'uuid', 'nullable' => 'NO'],
                'loaned_at' => ['type' => 'timestamp with time zone', 'nullable' => 'NO'],
            ],
            'loan_item' => [
                'id' => ['type' => 'uuid', 'nullable' => 'NO'],
                'loan_id' => ['type' => 'uuid', 'nullable' => 'NO'],
                'book_id' => ['type' => 'uuid', 'nullable' => 'NO'],
                'book_title' => ['type' => 'character varying', 'nullable' => 'NO'],
                'isbn' => ['type' => 'character', 'nullable' => 'NO'],
                'category_name' => ['type' => 'character varying', 'nullable' => 'NO'],
                'due_at' => ['type' => 'timestamp with time zone', 'nullable' => 'NO'],
                'returned_at' => ['type' => 'timestamp with time zone', 'nullable' => 'YES'],
            ],
        ];

        $actualColumns = [];
        foreach ($columns as $col) {
            $actualColumns[$col->table_name][$col->column_name] = [
                'type' => $col->data_type,
                'nullable' => $col->is_nullable,
            ];
        }

        $this->assertEquals($expectedColumns, $actualColumns);
    }

    /**
     * Comprueba las claves primarias, claves foráneas y la restricción CHECK.
     */
    public function test_constraints_pk_fk_and_check(): void
    {
        $constraints = DB::select("
            SELECT c.conrelid::regclass::text as table_name,
                   c.conname,
                   c.contype,
                   pg_get_constraintdef(c.oid) as def
            FROM pg_constraint c
            JOIN pg_namespace n ON n.oid = c.connamespace
            WHERE n.nspname = 'library'
            ORDER BY c.conname;
        ");

        $byName = [];
        foreach ($constraints as $con) {
            $byName[$con->conname] = $con;
        }

        // Restricción CHECK
        $this->assertArrayHasKey('ck_book_available_range', $byName);
        $this->assertEquals('c', $byName['ck_book_available_range']->contype);
        $this->assertStringContainsString('available_copies <= total_copies', $byName['ck_book_available_range']->def);

        // Claves foráneas (FK-1 a FK-5)
        $this->assertArrayHasKey('fk_book_category', $byName);
        $this->assertEquals('f', $byName['fk_book_category']->contype);
        $this->assertStringContainsString('REFERENCES category(id)', $byName['fk_book_category']->def);
        $this->assertStringContainsString('ON DELETE RESTRICT', $byName['fk_book_category']->def);

        $this->assertArrayHasKey('fk_loan_member', $byName);
        $this->assertStringContainsString('REFERENCES member(id)', $byName['fk_loan_member']->def);
        $this->assertStringContainsString('ON DELETE RESTRICT', $byName['fk_loan_member']->def);

        $this->assertArrayHasKey('fk_loan_user', $byName);
        $this->assertStringContainsString('REFERENCES "user"(id)', $byName['fk_loan_user']->def);
        $this->assertStringContainsString('ON DELETE RESTRICT', $byName['fk_loan_user']->def);

        $this->assertArrayHasKey('fk_loan_item_loan', $byName);
        $this->assertStringContainsString('REFERENCES loan(id)', $byName['fk_loan_item_loan']->def);
        $this->assertStringContainsString('ON DELETE CASCADE', $byName['fk_loan_item_loan']->def);

        $this->assertArrayHasKey('fk_loan_item_book', $byName);
        $this->assertStringContainsString('REFERENCES book(id)', $byName['fk_loan_item_book']->def);
        $this->assertStringContainsString('ON DELETE RESTRICT', $byName['fk_loan_item_book']->def);

        // Índices únicos a nivel tabla
        $this->assertArrayHasKey('uq_category_name', $byName);
        $this->assertArrayHasKey('uq_member_document_number', $byName);
        $this->assertArrayHasKey('uq_user_username', $byName);
        $this->assertArrayHasKey('uq_loan_book', $byName);
    }

    /**
     * Comprueba los índices únicos, el índice parcial para ISBN y los índices de acceso.
     */
    public function test_indexes_including_partial_and_access_indexes(): void
    {
        $indexes = DB::select("
            SELECT tablename, indexname, indexdef
            FROM pg_indexes
            WHERE schemaname = 'library'
            ORDER BY indexname;
        ");

        $byName = [];
        foreach ($indexes as $idx) {
            $byName[$idx->indexname] = $idx;
        }

        // Índice único parcial para ISBN activo
        $this->assertArrayHasKey('uq_book_isbn_active', $byName);
        $this->assertStringContainsString('UNIQUE INDEX', $byName['uq_book_isbn_active']->indexdef);
        $this->assertStringContainsString('WHERE (deleted_at IS NULL)', $byName['uq_book_isbn_active']->indexdef);

        // Índices de acceso definidos en data-model.md §4
        $this->assertArrayHasKey('idx_book_category_title', $byName);
        $this->assertStringContainsString('WHERE (deleted_at IS NULL)', $byName['idx_book_category_title']->indexdef);

        $this->assertArrayHasKey('idx_loan_loaned_at', $byName);
        $this->assertArrayHasKey('idx_loan_item_book', $byName);

        $this->assertArrayHasKey('idx_loan_item_due_unreturned', $byName);
        $this->assertStringContainsString('WHERE (returned_at IS NULL)', $byName['idx_loan_item_due_unreturned']->indexdef);
    }

    /**
     * Comprueba las cinco categorías sembradas con sus identificadores exactos (data-model.md §9).
     */
    public function test_five_categories_are_seeded_with_exact_uuids(): void
    {
        $categories = DB::table('library.category')->orderBy('name')->get();
        $this->assertCount(5, $categories);

        $expected = [
            'Ciencia' => '22222222-2222-4222-8222-222222222222',
            'Historia' => '44444444-4444-4444-8444-444444444444',
            'Infantil' => '55555555-5555-4555-8555-555555555555',
            'Novela' => '11111111-1111-4111-8111-111111111111',
            'Tecnología' => '33333333-3333-4333-8333-333333333333',
        ];

        foreach ($categories as $cat) {
            $this->assertArrayHasKey($cat->name, $expected);
            $this->assertEquals($expected[$cat->name], $cat->id);
        }
    }
}
