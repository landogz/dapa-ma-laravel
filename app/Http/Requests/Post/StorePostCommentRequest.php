<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePostCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body'      => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:post_comments,id'],
            'image'     => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $body = trim((string) $this->input('body', ''));
            if ($body === '' && !$this->hasFile('image')) {
                $validator->errors()->add('body', 'Comment text or image is required.');
            }
        });
    }
}
