<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\HTTP\Controllers;

use App\Models\LoanItem;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Library\Domain\Exceptions\DocumentTakenException;
use Library\Domain\Exceptions\NotFoundException;
use Library\Domain\Exceptions\ValidationException;

final class MemberController
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $size = min(50, max(1, (int) $request->input('size', 20)));

        $query = Member::query();

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('full_name', 'ilike', "%{$q}%")
                    ->orWhere('document_number', 'ilike', "%{$q}%");
            });
        }

        $total = $query->count();
        $members = $query->orderBy('full_name')
            ->skip(($page - 1) * $size)
            ->take($size)
            ->get();

        $items = $members->map(fn (Member $m) => [
            'id' => $m->id,
            'full_name' => $m->full_name,
            'document_number' => $m->document_number,
        ]);

        return response()->json([
            'items' => $items,
            'page' => $page,
            'size' => $size,
            'total' => $total,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $member = Member::find($id);
        if (!$member) {
            throw new NotFoundException('Socio no encontrado.');
        }

        $activeLoans = LoanItem::whereHas('loan', fn ($query) => $query->where('member_id', $member->id))
            ->whereNull('returned_at')
            ->count();

        return response()->json([
            'id' => $member->id,
            'full_name' => $member->full_name,
            'document_number' => $member->document_number,
            'active_loans' => $activeLoans,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $fullName = trim((string) $request->input('full_name', ''));
        $documentNumber = trim((string) $request->input('document_number', ''));

        if ($fullName === '' || $documentNumber === '') {
            throw new ValidationException('El nombre completo y el documento son obligatorios.');
        }

        if (Member::where('document_number', $documentNumber)->exists()) {
            throw new DocumentTakenException("El documento '{$documentNumber}' ya se encuentra registrado.");
        }

        $member = Member::create([
            'id' => Str::uuid()->toString(),
            'full_name' => $fullName,
            'document_number' => $documentNumber,
        ]);

        return response()->json([
            'id' => $member->id,
            'full_name' => $member->full_name,
            'document_number' => $member->document_number,
        ], 201);
    }
}
