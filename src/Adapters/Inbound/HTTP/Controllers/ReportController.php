<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\HTTP\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Library\Domain\Exceptions\InvalidDateRangeException;
use Library\Domain\Exceptions\ValidationException;

final class ReportController
{
    public function mostBorrowed(Request $request): JsonResponse
    {
        $from = $request->input('from');
        $to = $request->input('to');

        if (!$from || !$to) {
            throw new ValidationException('Los parámetros from y to son obligatorios.');
        }

        try {
            $fromDate = Carbon::createFromFormat('Y-m-d', $from, 'UTC')->startOfDay();
            $toDate = Carbon::createFromFormat('Y-m-d', $to, 'UTC')->endOfDay();
        } catch (\Throwable) {
            throw new ValidationException('El formato de las fechas debe ser YYYY-MM-DD.');
        }

        if ($toDate < $fromDate) {
            throw new InvalidDateRangeException('La fecha de fin no puede ser anterior a la fecha de inicio.');
        }

        $results = DB::table('library.loan_item')
            ->join('library.loan', 'library.loan_item.loan_id', '=', 'library.loan.id')
            ->whereBetween('library.loan.loaned_at', [$fromDate, $toDate])
            ->groupBy('library.loan_item.book_id', 'library.loan_item.book_title', 'library.loan_item.category_name')
            ->select([
                'library.loan_item.book_id',
                'library.loan_item.book_title',
                'library.loan_item.category_name',
                DB::raw('count(*) as times_borrowed'),
            ])
            ->orderByDesc('times_borrowed')
            ->orderBy('library.loan_item.book_title')
            ->get();

        $rows = $results->map(fn ($r) => [
            'book_id' => $r->book_id,
            'book_title' => $r->book_title,
            'category_name' => $r->category_name,
            'times_borrowed' => (int) $r->times_borrowed,
        ]);

        return response()->json([
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
        ]);
    }
}
