<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentBlockController extends Controller
{
    /**
     * Public list of active content blocks for portals (notices, help text, etc.).
     *
     * @queryParam keys Optional comma-separated list of block keys to filter.
     */
    public function index(Request $request): JsonResponse
    {
        $keysFilter = $request->query('keys');
        $keys = is_string($keysFilter) && $keysFilter !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $keysFilter))))
            : [];

        $query = ContentBlock::query()->where('active', true)->orderBy('key');

        if ($keys !== []) {
            $query->whereIn('key', $keys);
        }

        return response()->json([
            'data' => $query->get(['id', 'key', 'title', 'body', 'updated_at']),
        ]);
    }
}
