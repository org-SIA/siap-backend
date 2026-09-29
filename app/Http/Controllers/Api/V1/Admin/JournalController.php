<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalActivite;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consultation du journal d'activité (permission journal.voir : admin). Lecture seule. */
class JournalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = JournalActivite::query()->orderByDesc('id');

        foreach (['action', 'niveau', 'user_id', 'sujet_type', 'sujet_id'] as $filtre) {
            if ($request->filled($filtre)) {
                $query->where($filtre, $request->query($filtre));
            }
        }
        if ($request->filled('du')) {
            $query->where('created_at', '>=', $request->query('du'));
        }
        if ($request->filled('au')) {
            $query->where('created_at', '<=', $request->query('au'));
        }

        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);

        return ApiResponse::paginated($query->paginate($perPage), fn (JournalActivite $j) => $j->toApi());
    }
}
