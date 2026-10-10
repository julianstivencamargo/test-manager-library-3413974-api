<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\HTTP\Controllers;

use App\Models\Book;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Library\Domain\Exceptions\BookNotAvailableException;
use Library\Domain\Exceptions\DuplicateBookInLoanException;
use Library\Domain\Exceptions\ItemAlreadyReturnedException;
use Library\Domain\Exceptions\MemberHasOverdueException;
use Library\Domain\Exceptions\MemberLimitExceededException;
use Library\Domain\Exceptions\NotFoundException;
use Library\Domain\Exceptions\ValidationException;

final class LoanController
{
    public function store(Request $request): JsonResponse
    {
        $memberId = (string) $request->input('member_id', '');
        $rawItems = $request->input('items');

        if (!is_array($rawItems) || count($rawItems) < 1 || count($rawItems) > 3) {
            throw new ValidationException('Un préstamo debe contener entre 1 y 3 libros.');
        }

        $member = Member::find($memberId);
        if (!$member) {
            throw new NotFoundException('Socio no encontrado.');
        }

        // Validate no duplicate books in the same request
        $bookIds = [];
        foreach ($rawItems as $idx => $it) {
            if (!is_array($it) || !isset($it['book_id'])) {
                throw new ValidationException("Cada elemento debe incluir un 'book_id'.");
            }
            $bId = (string) $it['book_id'];
            if (in_array($bId, $bookIds, true)) {
                throw new DuplicateBookInLoanException('No se puede incluir el mismo libro más de una vez en el préstamo.');
            }
            $bookIds[] = $bId;
        }

        $now = Carbon::now('UTC');

        // Check if member has any overdue unreturned items
        $hasOverdue = LoanItem::whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
            ->whereNull('returned_at')
            ->where('due_at', '<', $now)
            ->exists();

        if ($hasOverdue) {
            throw new MemberHasOverdueException('El socio tiene libros vencidos y no puede solicitar nuevos préstamos.');
        }

        // Check active loan limit for member (max 3 unreturned in total)
        $currentActiveCount = LoanItem::whereHas('loan', fn ($q) => $q->where('member_id', $member->id))
            ->whereNull('returned_at')
            ->count();

        if ($currentActiveCount + count($bookIds) > 3) {
            throw new MemberLimitExceededException("El socio ya tiene {$currentActiveCount} libros sin devolver y no puede exceder el límite de 3.");
        }

        $user = $request->attributes->get('auth_user') ?? $request->user();
        $userId = $user ? $user->id : (string) Str::uuid();

        $loan = DB::transaction(function () use ($member, $userId, $bookIds, $now) {
            // Check availability and lock rows
            $booksToLend = [];
            foreach ($bookIds as $idx => $bId) {
                $book = Book::with('category')->whereNull('deleted_at')->lockForUpdate()->find($bId);
                if (!$book) {
                    throw new NotFoundException("Libro '{$bId}' no encontrado.");
                }

                if ($book->available_copies < 1) {
                    throw new BookNotAvailableException(
                        "No quedan ejemplares disponibles de «{$book->title}».",
                        [['field' => "items[{$idx}].book_id", 'issue' => 'sin ejemplares']]
                    );
                }

                $book->decrement('available_copies');
                $booksToLend[] = $book;
            }

            $loanId = Str::uuid()->toString();
            $newLoan = Loan::create([
                'id' => $loanId,
                'member_id' => $member->id,
                'loaned_by_user_id' => $userId,
                'loaned_at' => $now,
            ]);

            $dueAt = $now->copy()->addDays(14);

            foreach ($booksToLend as $book) {
                LoanItem::create([
                    'id' => Str::uuid()->toString(),
                    'loan_id' => $loanId,
                    'book_id' => $book->id,
                    'book_title' => $book->title,
                    'isbn' => trim($book->isbn),
                    'category_name' => $book->category->name,
                    'due_at' => $dueAt,
                    'returned_at' => null,
                ]);
            }

            $newLoan->load('items');

            return $newLoan;
        });

        return response()->json($this->formatLoan($loan, $now), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $statusFilter = $request->input('status');
        $memberId = $request->input('member_id');
        $page = max(1, (int) $request->input('page', 1));
        $size = min(50, max(1, (int) $request->input('size', 20)));

        $query = Loan::with(['items', 'member'])->orderBy('loaned_at', 'desc');

        if ($from) {
            $query->where('loaned_at', '>=', Carbon::parse($from, 'UTC')->startOfDay());
        }

        if ($to) {
            $query->where('loaned_at', '<=', Carbon::parse($to, 'UTC')->endOfDay());
        }

        if ($memberId) {
            $query->where('member_id', $memberId);
        }

        $now = Carbon::now('UTC');
        $allLoans = $query->get();

        if ($statusFilter) {
            $allLoans = $allLoans->filter(fn (Loan $l) => $l->calculateStatus() === $statusFilter);
        }

        $total = $allLoans->count();
        $pagedLoans = $allLoans->slice(($page - 1) * $size, $size)->values();

        $items = $pagedLoans->map(fn (Loan $l) => $this->formatLoan($l, $now));

        return response()->json([
            'items' => $items,
            'page' => $page,
            'size' => $size,
            'total' => $total,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $loan = Loan::with('items')->find($id);
        if (!$loan) {
            throw new NotFoundException('Préstamo no encontrado.');
        }

        return response()->json($this->formatLoan($loan, Carbon::now('UTC')));
    }

    public function returnItem(string $loanId, string $itemId): JsonResponse
    {
        $now = Carbon::now('UTC');

        $item = DB::transaction(function () use ($loanId, $itemId, $now) {
            $loanItem = LoanItem::where('loan_id', $loanId)->where('id', $itemId)->lockForUpdate()->first();
            if (!$loanItem) {
                throw new NotFoundException('Línea de préstamo no encontrada.');
            }

            if ($loanItem->returned_at !== null) {
                throw new ItemAlreadyReturnedException('La línea de préstamo ya fue devuelta previamente.');
            }

            $loanItem->update(['returned_at' => $now]);

            $book = Book::lockForUpdate()->find($loanItem->book_id);
            if ($book) {
                $book->increment('available_copies');
            }

            return $loanItem;
        });

        return response()->json([
            'id' => $item->id,
            'loan_id' => $item->loan_id,
            'book_id' => $item->book_id,
            'book_title' => $item->book_title,
            'isbn' => trim($item->isbn),
            'category_name' => $item->category_name,
            'due_at' => $item->due_at->toIso8601ZuluString(),
            'returned_at' => $item->returned_at->toIso8601ZuluString(),
        ]);
    }

    public function overdue(): JsonResponse
    {
        $now = Carbon::now('UTC');

        $overdueItems = LoanItem::with(['loan.member'])
            ->whereNull('returned_at')
            ->where('due_at', '<', $now)
            ->get();

        $formatted = $overdueItems->map(function (LoanItem $item) use ($now) {
            $diffSeconds = $now->diffInSeconds($item->due_at, false);
            $daysLate = (int) ceil(abs($diffSeconds) / 86400);

            return [
                'loan_id' => $item->loan_id,
                'item_id' => $item->id,
                'book_title' => $item->book_title,
                'member_name' => $item->loan?->member?->full_name ?? '',
                'due_at' => $item->due_at->toIso8601ZuluString(),
                'days_late' => $daysLate,
            ];
        })->sortByDesc('days_late')->values();

        return response()->json([
            'items' => $formatted,
        ]);
    }

    private function formatLoan(Loan $loan, Carbon $now): array
    {
        return [
            'id' => $loan->id,
            'member_id' => $loan->member_id,
            'loaned_at' => $loan->loaned_at->toIso8601ZuluString(),
            'status' => $loan->calculateStatus(),
            'items' => $loan->items->map(fn (LoanItem $item) => [
                'id' => $item->id,
                'book_id' => $item->book_id,
                'book_title' => $item->book_title,
                'isbn' => trim($item->isbn),
                'category_name' => $item->category_name,
                'due_at' => $item->due_at->toIso8601ZuluString(),
                'returned_at' => $item->returned_at ? $item->returned_at->toIso8601ZuluString() : null,
            ])->values()->all(),
        ];
    }
}
