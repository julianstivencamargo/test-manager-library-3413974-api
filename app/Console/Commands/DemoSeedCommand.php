<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeedCommand extends Command
{
    protected $signature = 'library:demo-seed';
    protected $description = 'Seed comprehensive demonstration data (>= 40 books, 10 members, 15 loans with 3 overdue)';

    public function handle(): int
    {
        $this->info('Starting demonstration seeding...');

        // 1. Ensure Admin and Librarian users exist
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'id' => Str::uuid()->toString(),
                'password_hash' => Hash::make('Admin1234*'),
                'role' => 'admin',
            ]
        );

        $librarian = User::firstOrCreate(
            ['username' => 'ana'],
            [
                'id' => Str::uuid()->toString(),
                'password_hash' => Hash::make('Librarian123*'),
                'role' => 'librarian',
            ]
        );

        $categories = Category::all()->keyBy('name');
        if ($categories->isEmpty()) {
            $this->error('Categories must be seeded first.');
            return Command::FAILURE;
        }

        // 2. Seed 42 Books
        $rawBooks = [
            // Novela
            ['Cien años de soledad', 'Gabriel García Márquez', '9780307474728', 'Novela', 5],
            ['El amor en los tiempos del cólera', 'Gabriel García Márquez', '9780307387264', 'Novela', 4],
            ['Don Quijote de la Mancha', 'Miguel de Cervantes', '9788424116286', 'Novela', 6],
            ['Rayuela', 'Julio Cortázar', '9788466331906', 'Novela', 3],
            ['Ficciones', 'Jorge Luis Borges', '9788420633121', 'Novela', 4],
            ['La ciudad y los perros', 'Mario Vargas Llosa', '9788420471839', 'Novela', 4],
            ['Pedro Páramo', 'Juan Rulfo', '9788437604183', 'Novela', 5],
            ['Crimen y castigo', 'Fiódor Dostoyevski', '9788420651330', 'Novela', 4],
            ['Orgullo y prejuicio', 'Jane Austen', '9788497940863', 'Novela', 3],

            // Ciencia
            ['Breve historia del tiempo', 'Stephen Hawking', '9788498927948', 'Ciencia', 4],
            ['Cosmos', 'Carl Sagan', '9788408053910', 'Ciencia', 5],
            ['El origen de las especies', 'Charles Darwin', '9788497940252', 'Ciencia', 3],
            ['El gen egoísta', 'Richard Dawkins', '9788434501782', 'Ciencia', 4],
            ['Sapiens: De animales a dioses', 'Yuval Noah Harari', '9788499926223', 'Ciencia', 6],
            ['Una breve historia de casi todo', 'Bill Bryson', '9788490628393', 'Ciencia', 3],
            ['La estructura de las revoluciones científicas', 'Thomas Kuhn', '9788437505718', 'Ciencia', 3],
            ['Armas, gérmenes y acero', 'Jared Diamond', '9788499926315', 'Ciencia', 4],

            // Tecnología
            ['Clean Code', 'Robert C. Martin', '9780132350884', 'Tecnología', 5],
            ['The Pragmatic Programmer', 'David Thomas & Andrew Hunt', '9780135957059', 'Tecnología', 4],
            ['Design Patterns', 'Erich Gamma et al.', '9780201633610', 'Tecnología', 4],
            ['Refactoring', 'Martin Fowler', '9780134757599', 'Tecnología', 3],
            ['Domain-Driven Design', 'Eric Evans', '9780321125217', 'Tecnología', 4],
            ['Building Microservices', 'Sam Newman', '9781492034025', 'Tecnología', 4],
            ['Designing Data-Intensive Applications', 'Martin Kleppmann', '9781449373320', 'Tecnología', 5],
            ['Clean Architecture', 'Robert C. Martin', '9780134494166', 'Tecnología', 4],
            ['Introduction to Algorithms', 'Thomas H. Cormen', '9780262033848', 'Tecnología', 3],

            // Historia
            ['Historia de Colombia y sus oligarquías', 'Antonio Caballero', '9789584268686', 'Historia', 4],
            ['Los cañones de agosto', 'Barbara W. Tuchman', '9788499421889', 'Historia', 3],
            ['El siglo de las luces', 'Alejo Carpentier', '9788420655383', 'Historia', 3],
            ['La Segunda Guerra Mundial', 'Antony Beevor', '9788498925104', 'Historia', 4],
            ['Homo Deus', 'Yuval Noah Harari', '9788499926711', 'Historia', 5],
            ['21 lecciones para el siglo XXI', 'Yuval Noah Harari', '9788499928739', 'Historia', 4],
            ['El colapso de las sociedades complejas', 'Joseph Tainter', '9788494798603', 'Historia', 3],
            ['Memorias de Adriano', 'Marguerite Yourcenar', '9788435009584', 'Historia', 4],

            // Infantil
            ['El Principito', 'Antoine de Saint-Exupéry', '9788498381498', 'Infantil', 6],
            ['Donde viven los monstruos', 'Maurice Sendak', '9788484645641', 'Infantil', 4],
            ['Matilda', 'Roald Dahl', '9788491221371', 'Infantil', 5],
            ['Charlie y la fábrica de chocolate', 'Roald Dahl', '9788491221364', 'Infantil', 4],
            ['Cuentos de buenas noches para niñas rebeldes', 'Elena Favilli', '9786070739989', 'Infantil', 4],
            ['El monstruo de colores', 'Anna Llenas', '9788494053641', 'Infantil', 5],
            ['Adivina cuánto te quiero', 'Sam McBratney', '9788498380309', 'Infantil', 4],
            ['La oruga muy hambrienta', 'Eric Carle', '9788496629738', 'Infantil', 5],
        ];

        $createdBooks = [];
        foreach ($rawBooks as [$title, $author, $isbn, $catName, $copies]) {
            $cat = $categories->get($catName);
            $book = Book::firstOrCreate(
                ['isbn' => $isbn],
                [
                    'id' => Str::uuid()->toString(),
                    'title' => $title,
                    'author' => $author,
                    'category_id' => $cat->id,
                    'total_copies' => $copies,
                    'available_copies' => $copies,
                    'cover_key' => null,
                    'deleted_at' => null,
                ]
            );
            $createdBooks[] = $book;
        }

        $this->info("Seeded " . count($createdBooks) . " books.");

        // 3. Seed 10 Members
        $rawMembers = [
            ['Carlos Eduardo Restrepo', '1017123456'],
            ['Valentina Morales Ríos', '1020654321'],
            ['Andrés Felipe Gómez', '1035987654'],
            ['Camila Andrea Duque', '1040112233'],
            ['Santiago Castro Pérez', '1052334455'],
            ['Isabella Muñoz Ortiz', '1063556677'],
            ['Mateo Herrera Londoño', '1074778899'],
            ['Sofía Ramírez Valencia', '1085990011'],
            ['Nicolás Jaramillo Botero', '1096123789'],
            ['Mariana Mejía Giraldo', '1107456123'],
        ];

        $createdMembers = [];
        foreach ($rawMembers as [$fullName, $doc]) {
            $member = Member::firstOrCreate(
                ['document_number' => $doc],
                [
                    'id' => Str::uuid()->toString(),
                    'full_name' => $fullName,
                ]
            );
            $createdMembers[] = $member;
        }

        $this->info("Seeded " . count($createdMembers) . " members.");

        // 4. Seed 15 Loans (with 3 overdue)
        // Clean existing demo loans to be idempotent
        if (Loan::count() < 15) {
            $now = Carbon::now('UTC');

            for ($i = 0; $i < 15; $i++) {
                $member = $createdMembers[$i % count($createdMembers)];
                $bookA = $createdBooks[($i * 2) % count($createdBooks)];
                $bookB = $createdBooks[($i * 2 + 1) % count($createdBooks)];

                $isOverdue = ($i < 3); // 3 overdue loans
                $isReturned = ($i >= 7 && $i < 11); // 4 closed/returned loans

                $loanDate = $isOverdue
                    ? $now->copy()->subDays(20 + $i)
                    : $now->copy()->subDays(2 + $i);

                $dueDate = $loanDate->copy()->addDays(14);
                $returnedDate = $isReturned ? $loanDate->copy()->addDays(5) : null;

                $loan = Loan::create([
                    'id' => Str::uuid()->toString(),
                    'member_id' => $member->id,
                    'loaned_by_user_id' => $librarian->id,
                    'loaned_at' => $loanDate,
                ]);

                // Item 1
                LoanItem::create([
                    'id' => Str::uuid()->toString(),
                    'loan_id' => $loan->id,
                    'book_id' => $bookA->id,
                    'book_title' => $bookA->title,
                    'isbn' => trim($bookA->isbn),
                    'category_name' => $bookA->category->name,
                    'due_at' => $dueDate,
                    'returned_at' => $returnedDate,
                ]);

                if (!$isReturned && $bookA->available_copies > 0) {
                    $bookA->decrement('available_copies');
                }

                // Item 2
                LoanItem::create([
                    'id' => Str::uuid()->toString(),
                    'loan_id' => $loan->id,
                    'book_id' => $bookB->id,
                    'book_title' => $bookB->title,
                    'isbn' => trim($bookB->isbn),
                    'category_name' => $bookB->category->name,
                    'due_at' => $dueDate,
                    'returned_at' => $returnedDate,
                ]);

                if (!$isReturned && $bookB->available_copies > 0) {
                    $bookB->decrement('available_copies');
                }
            }
        }

        $this->info("Seeded loans successfully.");
        $this->info("Overdue items count: " . LoanItem::whereNull('returned_at')->where('due_at', '<', now())->count());

        return Command::SUCCESS;
    }
}
