<?php

namespace App\Http\Requests\Post;

use App\Models\PostLike;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetPostReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reaction' => [
                'nullable',
                'string',
                Rule::in(PostLike::REACTION_TYPES),
            ],
        ];
    }
}
