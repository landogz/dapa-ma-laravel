<?php

namespace App\Http\Requests\Admin;

use App\Support\Notification\NotificationLinkType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:1024'],
            'topic' => ['nullable', 'string', 'max:255'],
            'link_type' => ['nullable', 'string', Rule::in(NotificationLinkType::all())],
            'link_id' => ['nullable', 'integer', 'required_with:link_type'],
            'post_id' => ['nullable', 'integer', 'exists:posts,id'],
            'data' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $linkType = $this->input('link_type');
            $linkId = $this->input('link_id');

            if (! filled($linkType) && filled($this->input('post_id'))) {
                return;
            }

            if (! filled($linkType)) {
                return;
            }

            if (! filled($linkId)) {
                $validator->errors()->add('link_id', 'Choose an item to link.');

                return;
            }

            if (! NotificationLinkType::exists((string) $linkType, (int) $linkId)) {
                $validator->errors()->add('link_id', 'The selected link target is invalid.');
            }
        });
    }
}
