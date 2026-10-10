<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InitialMigrationTest extends TestCase
{
    /**
     * Comprueba las columnas y que ninguna columna tenga DEFAULT (data-model.md §10 y Artículo V).
     */
    public function test_no_columns_have_default_values_in_library_schema(): void
    {
        $columns = DB::select("
            SELECT table_name, column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_schema = 'library'
            ORDER BY table_name, ordinal_position;
        ");

        $this->assertNotEmpty($columns, 'El esquema library debe contener columnas.');

        foreach ($columns as $column) {
            $this->assertNull(
                $column->column_default,
                "La columna '{$column->table_name}.{$column->column_name}' no debe tener valor DEFAULT definido."
            );
        }
    }

    /**
     * Comprueba que existen exactamente las seis tablas del modelo físico.
     */
    public function test_all_six_tables_exist_in_library_schema(): void
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
     * Comprueba las cinco categorías fijas sembradas con sus identificadores exactos (data-model.md §9).
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

    /**
     * Comprueba la restricción de verificación ck_book_available_range en el motor.
     */
    public function test_check_constraint_book_available_range_exists(): void
    {
        $constraints = DB::select("
            SELECT conname, contype, pg_get_constraintdef(c.oid) as def
            FROM pg_constraint c
            JOIN pg_namespace n ON n.oid = c.connamespace
            WHERE n.nspname = 'library' AND conname = 'ck_book_available_range';
        ");

        $this->assertCount(1, $constraints);
        $this->assertEquals('c', $constraints[0]->contype);
        $this->assertStringContainsString('available_copies <= total_copies', $constraints[0]->def);
    }

    /**
     * Comprueba el índice único parcial para ISBN entre libros activos.
     */
    public function test_partial_unique_index_for_active_isbn_exists(): void
    {
        $indexes = DB::select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE schemaname = 'library' AND indexname = 'uq_book_isbn_active';
        ");

        $this->assertCount(1, $indexes);
        $this->assertStringContainsString('WHERE (deleted_at IS NULL)', $indexes[0]->indexdef);
    }
}
