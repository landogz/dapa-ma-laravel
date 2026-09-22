<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKidListoQuoteRequest;
use App\Http\Requests\Admin\UpdateKidListoQuoteRequest;
use App\Models\KidListoQuote;
use App\Services\KidListoQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KidListoQuoteAdminController extends Controller
{
    public function __construct(
        private readonly KidListoQuoteService $kidListoQuoteService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Kid Listo quotes fetched.',
            'data' => $this->kidListoQuoteService->list(
                (int) $request->integer('per_page', 200),
                $request->string('search')->toString() ?: null,
            ),
        ]);
    }

    public function show(KidListoQuote $kidListoQuote): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $kidListoQuote,
        ]);
    }

    public function store(StoreKidListoQuoteRequest $request): JsonResponse
    {
        $quote = $this->kidListoQuoteService->create($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Kid Listo quote created.',
            'data' => $quote,
        ], 201);
    }

    public function update(UpdateKidListoQuoteRequest $request, KidListoQuote $kidListoQuote): JsonResponse
    {
        $quote = $this->kidListoQuoteService->update($kidListoQuote, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Kid Listo quote updated.',
            'data' => $quote,
        ]);
    }

    public function destroy(KidListoQuote $kidListoQuote): JsonResponse
    {
        $this->kidListoQuoteService->delete($kidListoQuote);

        return response()->json([
            'status' => true,
            'message' => 'Kid Listo quote deleted.',
        ]);
    }
}
