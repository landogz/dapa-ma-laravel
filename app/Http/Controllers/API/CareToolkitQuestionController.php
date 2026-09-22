<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CareToolkitQuestion;
use App\Services\CareToolkitQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CareToolkitQuestionController extends Controller
{
    public function __construct(
        private readonly CareToolkitQuestionService $careToolkitQuestionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'   => ['required', 'string', Rule::in(CareToolkitQuestion::TYPES)],
            'locale' => ['nullable', 'string', 'max:8'],
        ]);

        $questions = $this->careToolkitQuestionService->publicByType(
            $validated['type'],
            $validated['locale'] ?? $request->header('X-Locale', 'en'),
        );

        return response()->json([
            'status'  => true,
            'message' => 'Care toolkit questions fetched.',
            'data'    => [
                'toolkit_type' => $validated['type'],
                'questions'    => $questions,
            ],
        ]);
    }
}
