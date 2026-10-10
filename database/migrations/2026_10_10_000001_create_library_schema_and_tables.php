<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Crear el esquema 'library' si no existe
        DB::statement('CREATE SCHEMA IF NOT EXISTS library;');

        // 2. Tabla category
        DB::statement('
            CREATE TABLE library.category (
                id UUID PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                CONSTRAINT uq_category_name UNIQUE (name)
            );
        ');

        // 3. Tabla book
        DB::statement('
            CREATE TABLE library.book (
                id UUID PRIMARY KEY,
                title VARCHAR(250) NOT NULL,
                author VARCHAR(200) NOT NULL,
                isbn CHAR(13) NOT NULL,
                category_id UUID NOT NULL,
                total_copies INTEGER NOT NULL,
                available_copies INTEGER NOT NULL,
                cover_key VARCHAR(512) NULL,
                deleted_at TIMESTAMPTZ NULL,
                CONSTRAINT fk_book_category FOREIGN KEY (category_id) REFERENCES library.category (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
                CONSTRAINT ck_book_available_range CHECK (0 <= available_copies AND available_copies <= total_copies)
            );
        ');

        // Índice único parcial para ISBN entre libros activos
        DB::statement('
            CREATE UNIQUE INDEX uq_book_isbn_active ON library.book (isbn) WHERE deleted_at IS NULL;
        ');

        // Índice de acceso por categoría y título
        DB::statement('
            CREATE INDEX idx_book_category_title ON library.book (category_id, title) WHERE deleted_at IS NULL;
        ');

        // 4. Tabla member
        DB::statement('
            CREATE TABLE library.member (
                id UUID PRIMARY KEY,
                full_name VARCHAR(200) NOT NULL,
                document_number VARCHAR(30) NOT NULL,
                CONSTRAINT uq_member_document_number UNIQUE (document_number)
            );
        ');

        // 5. Tabla user
        DB::statement('
            CREATE TABLE library."user" (
                id UUID PRIMARY KEY,
                username VARCHAR(120) NOT NULL,
                password_hash VARCHAR(512) NOT NULL,
                role VARCHAR(40) NOT NULL,
                CONSTRAINT uq_user_username UNIQUE (username)
            );
        ');

        // 6. Tabla loan
        DB::statement('
            CREATE TABLE library.loan (
                id UUID PRIMARY KEY,
                member_id UUID NOT NULL,
                loaned_by_user_id UUID NOT NULL,
                loaned_at TIMESTAMPTZ NOT NULL,
                CONSTRAINT fk_loan_member FOREIGN KEY (member_id) REFERENCES library.member (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
                CONSTRAINT fk_loan_user FOREIGN KEY (loaned_by_user_id) REFERENCES library."user" (id) ON DELETE RESTRICT ON UPDATE NO ACTION
            );
        ');

        DB::statement('
            CREATE INDEX idx_loan_loaned_at ON library.loan (loaned_at);
        ');

        // 7. Tabla loan_item
        DB::statement('
            CREATE TABLE library.loan_item (
                id UUID PRIMARY KEY,
                loan_id UUID NOT NULL,
                book_id UUID NOT NULL,
                book_title VARCHAR(250) NOT NULL,
                isbn CHAR(13) NOT NULL,
                category_name VARCHAR(120) NOT NULL,
                due_at TIMESTAMPTZ NOT NULL,
                returned_at TIMESTAMPTZ NULL,
                CONSTRAINT fk_loan_item_loan FOREIGN KEY (loan_id) REFERENCES library.loan (id) ON DELETE CASCADE ON UPDATE NO ACTION,
                CONSTRAINT fk_loan_item_book FOREIGN KEY (book_id) REFERENCES library.book (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
                CONSTRAINT uq_loan_book UNIQUE (loan_id, book_id)
            );
        ');

        DB::statement('
            CREATE INDEX idx_loan_item_book ON library.loan_item (book_id);
        ');

        DB::statement('
            CREATE INDEX idx_loan_item_due_unreturned ON library.loan_item (due_at) WHERE returned_at IS NULL;
        ');

        // 8. Sembrar las cinco categorías fijas con identificadores inmutables (data-model.md §9)
        DB::table('library.category')->insert([
            ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novela'],
            ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Ciencia'],
            ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Tecnología'],
            ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'Historia'],
            ['id' => '55555555-5555-4555-8555-555555555555', 'name' => 'Infantil'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS library.loan_item RESTRICT;');
        DB::statement('DROP TABLE IF EXISTS library.loan RESTRICT;');
        DB::statement('DROP TABLE IF EXISTS library."user" RESTRICT;');
        DB::statement('DROP TABLE IF EXISTS library.member RESTRICT;');
        DB::statement('DROP TABLE IF EXISTS library.book RESTRICT;');
        DB::statement('DROP TABLE IF EXISTS library.category RESTRICT;');
    }
};
