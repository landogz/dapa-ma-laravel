<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HopeEvent extends Model
{
    public const AUDIENCES = ['youth', 'parents', 'community'];

    public const STATUSES = ['upcoming', 'ongoing', 'ended'];

    protected $fillable = [
        'title',
        'audience',
        'cover_path',
        'status',
        'start_date',
        'end_date',
        'venue',
        'is_online',
        'online_label',
        'slots',
        'registration_url',
        'about_text',
        'for_you_items',
        'who_can_join',
        'highlights',
        'details_text',
        'speakers',
        'faqs',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date'    => 'date',
            'end_date'      => 'date',
            'is_online'     => 'boolean',
            'slots'         => 'integer',
            'for_you_items' => 'array',
            'highlights'    => 'array',
            'speakers'      => 'array',
            'faqs'          => 'array',
            'sort_order'    => 'integer',
            'is_active'     => 'boolean',
        ];
    }

    public function coverUrl(): ?string
    {
        if (!$this->cover_path) {
            return null;
        }

        return Storage::disk('public')->url($this->cover_path);
    }

    public static function speakerPhotoUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @return list<array{name: string, role: ?string, photo_url: ?string, photo_path?: ?string}>
     */
    public function speakersForApi(bool $includePaths = false): array
    {
        $out = [];
        foreach ($this->speakers ?? [] as $speaker) {
            if (!is_array($speaker)) {
                continue;
            }
            $name = trim((string) ($speaker['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $path = isset($speaker['photo_path']) ? (string) $speaker['photo_path'] : null;
            $item = [
                'name'      => $name,
                'role'      => isset($speaker['role']) && $speaker['role'] !== ''
                    ? (string) $speaker['role']
                    : null,
                'photo_url' => self::speakerPhotoUrl($path ?: null),
            ];
            if ($includePaths) {
                $item['photo_path'] = $path ?: null;
            }
            $out[] = $item;
        }

        return $out;
    }

    public function toPublicArray(bool $detailed = false): array
    {
        $base = [
            'id'               => $this->id,
            'title'            => $this->title,
            'audience'         => $this->audience,
            'cover_url'        => $this->coverUrl(),
            'status'           => $this->status,
            'start_date'       => $this->start_date?->toDateString(),
            'end_date'         => $this->end_date?->toDateString(),
            'venue'            => $this->venue,
            'is_online'        => $this->is_online,
            'online_label'     => $this->online_label,
            'slots'            => $this->slots,
            'registration_url' => $this->registration_url,
            'sort_order'       => $this->sort_order,
        ];

        if (!$detailed) {
            return $base;
        }

        return [
            ...$base,
            'about_text'    => $this->about_text,
            'for_you_items' => $this->for_you_items ?? [],
            'who_can_join'  => $this->who_can_join,
            'highlights'    => $this->highlights ?? [],
            'details_text'  => $this->details_text,
            'speakers'      => $this->speakersForApi(false),
            'faqs'          => $this->faqs ?? [],
        ];
    }

    public function toAdminArray(): array
    {
        return [
            ...$this->toPublicArray(true),
            'speakers'   => $this->speakersForApi(true),
            'cover_path' => $this->cover_path,
            'is_active'  => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
