<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\HTTP\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;

final class CategoryController
{
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
            ]);

        return response()->json($categories);
    }
}
