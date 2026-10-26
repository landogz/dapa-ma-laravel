<?php

namespace App\Http\Requests\Post;

use App\Models\PostComment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePostCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body'  => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $body = trim((string) $this->input('body', ''));
            if ($body !== '' || $this->hasFile('image')) {
                return;
            }

            $commentId = (int) $this->route('commentId');
            $hasImage = PostComment::query()
                ->where('id', $commentId)
                ->whereNotNull('image_path')
                ->where('image_path', '!=', '')
                ->exists();

            if (! $hasImage) {
                $validator->errors()->add('body', 'Comment text or image is required.');
            }
        });
    }
}
