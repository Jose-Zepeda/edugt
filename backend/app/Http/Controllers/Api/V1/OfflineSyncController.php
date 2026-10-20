<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\OfflineSync\Actions\OfflineSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RF-25..RF-27: modo offline parcial.
 */
final class OfflineSyncController extends Controller
{
    public function __construct(private readonly OfflineSync $offline) {}

    /** GET /api/v1/offline/bundle */
    public function bundle(Request $request): JsonResponse
    {
        return response()->json(['data' => ($this->offline)->bundle($request->user())]);
    }

    /** POST /api/v1/offline/sync — {items: [...]} */
    public function sync(Request $request): JsonResponse
    {
        // Validación de ESTRUCTURA únicamente: el contenido lo inspecciona el
        // action (RB-13) y validate() filtraría llaves como quiz_id antes de poder rechazarlas.
        $data = $request->validate([
            'items' => ['required', 'array', 'max:200'],
        ]);

        $result = ($this->offline)->sync($request->user(), $request->input('items', []));

        return response()->json($result);
    }
}
