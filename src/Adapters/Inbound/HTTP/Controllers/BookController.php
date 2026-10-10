<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\HTTP\Controllers;

use App\Models\Book;
use App\Models\Category;
use App\Models\LoanItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Library\Domain\Exceptions\BookHasOpenLoansException;
use Library\Domain\Exceptions\IsbnTakenException;
use Library\Domain\Exceptions\NotFoundException;
use Library\Domain\Exceptions\ValidationException;
use Library\Domain\ValueObjects\Isbn;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class BookController
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $categoryId = $request->input('category_id');
        $page = max(1, (int) $request->input('page', 1));
        $size = min(50, max(1, (int) $request->input('size', 20)));

        $query = Book::with('category')->whereNull('deleted_at');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'ilike', "%{$q}%")
                    ->orWhere('author', 'ilike', "%{$q}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $total = $query->count();
        $books = $query->orderBy('title')
            ->skip(($page - 1) * $size)
            ->take($size)
            ->get();

        $items = $books->map(fn (Book $b) => $this->formatBook($b));

        return response()->json([
            'items' => $items,
            'page' => $page,
            'size' => $size,
            'total' => $total,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $book = Book::with('category')->whereNull('deleted_at')->find($id);
        if (!$book) {
            throw new NotFoundException('Libro no encontrado.');
        }

        return response()->json($this->formatBook($book));
    }

    public function store(Request $request): JsonResponse
    {
        $title = trim((string) $request->input('title', ''));
        $author = trim((string) $request->input('author', ''));
        $rawIsbn = $request->input('isbn');
        $categoryId = (string) $request->input('category_id', '');
        $totalCopies = (int) $request->input('total_copies', 0);

        if ($title === '' || $author === '') {
            throw new ValidationException('El título y el autor son obligatorios.');
        }

        if ($totalCopies < 1) {
            throw new ValidationException('El total de ejemplares debe ser mayor o igual a 1.');
        }

        if (!Category::where('id', $categoryId)->exists()) {
            throw new ValidationException('La categoría especificada no existe.');
        }

        $isbnVo = new Isbn($rawIsbn);
        $normalizedIsbn = $isbnVo->value();

        if (Book::where('isbn', $normalizedIsbn)->whereNull('deleted_at')->exists()) {
            throw new IsbnTakenException("El ISBN '{$normalizedIsbn}' ya se encuentra registrado en un libro activo.");
        }

        $book = Book::create([
            'id' => Str::uuid()->toString(),
            'title' => $title,
            'author' => $author,
            'isbn' => $normalizedIsbn,
            'category_id' => $categoryId,
            'total_copies' => $totalCopies,
            'available_copies' => $totalCopies,
            'cover_key' => null,
            'deleted_at' => null,
        ]);

        $book->load('category');

        return response()->json($this->formatBook($book), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $book = Book::with('category')->whereNull('deleted_at')->find($id);
        if (!$book) {
            throw new NotFoundException('Libro no encontrado.');
        }

        $title = trim((string) $request->input('title', ''));
        $author = trim((string) $request->input('author', ''));
        $categoryId = (string) $request->input('category_id', '');
        $newTotal = (int) $request->input('total_copies', 0);

        if ($title === '' || $author === '') {
            throw new ValidationException('El título y el autor son obligatorios.');
        }

        if ($newTotal < 1) {
            throw new ValidationException('El total de ejemplares debe ser mayor o igual a 1.');
        }

        if (!Category::where('id', $categoryId)->exists()) {
            throw new ValidationException('La categoría especificada no existe.');
        }

        $diff = $newTotal - $book->total_copies;
        $newAvailable = $book->available_copies + $diff;

        if ($newAvailable < 0) {
            throw new ValidationException("Modificar el total dejaría los ejemplares disponibles en {$newAvailable} (< 0).");
        }

        $book->update([
            'title' => $title,
            'author' => $author,
            'category_id' => $categoryId,
            'total_copies' => $newTotal,
            'available_copies' => $newAvailable,
        ]);

        $book->load('category');

        return response()->json($this->formatBook($book));
    }

    public function destroy(string $id): JsonResponse
    {
        $book = Book::whereNull('deleted_at')->find($id);
        if (!$book) {
            throw new NotFoundException('Libro no encontrado.');
        }

        $hasOpenLoans = LoanItem::where('book_id', $book->id)
            ->whereNull('returned_at')
            ->exists();

        if ($hasOpenLoans) {
            throw new BookHasOpenLoansException('El libro tiene préstamos abiertos y no puede darse de baja.');
        }

        $book->delete(); // Soft delete sets deleted_at

        return response()->json(null, 204);
    }

    public function uploadCover(Request $request, string $id): JsonResponse
    {
        $book = Book::with('category')->whereNull('deleted_at')->find($id);
        if (!$book) {
            throw new NotFoundException('Libro no encontrado.');
        }

        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            throw new ValidationException('Debe proporcionar un archivo de imagen válido.');
        }

        $file = $request->file('file');
        $mime = $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new ValidationException('Solo se admiten portadas en formato JPG o PNG.');
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            throw new ValidationException('El archivo de portada no debe superar los 2 MB.');
        }

        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $coverKey = "covers/{$book->id}_" . Str::random(16) . ".{$extension}";

        $file->storeAs('public', $coverKey);

        $book->update(['cover_key' => $coverKey]);

        return response()->json($this->formatBook($book));
    }

    public function deleteCover(string $id): JsonResponse
    {
        $book = Book::whereNull('deleted_at')->find($id);
        if (!$book) {
            throw new NotFoundException('Libro no encontrado.');
        }

        if ($book->cover_key) {
            Storage::disk('public')->delete($book->cover_key);
            $book->update(['cover_key' => null]);
        }

        return response()->json(null, 204);
    }

    public function getCover(string $id): BinaryFileResponse
    {
        $book = Book::whereNull('deleted_at')->find($id);
        if (!$book || !$book->cover_key) {
            throw new NotFoundException('Portada no encontrada.');
        }

        $path = storage_path("app/public/{$book->cover_key}");
        if (!file_exists($path)) {
            throw new NotFoundException('Archivo de portada no encontrado.');
        }

        return response()->file($path);
    }

    private function formatBook(Book $book): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => trim($book->isbn),
            'category' => [
                'id' => $book->category->id,
                'name' => $book->category->name,
            ],
            'total_copies' => (int) $book->total_copies,
            'available_copies' => (int) $book->available_copies,
            'cover_key' => $book->cover_key,
        ];
    }
}
