<?php

namespace App\Repositories\AdminSearch;

use App\Models\AppOption;
use App\Models\AppTranslation;
use App\Models\BlockedWord;
use App\Models\CareSupportResource;
use App\Models\Contest;
use App\Models\HopeDirectoryOrganization;
use App\Models\HopeEvent;
use App\Models\IecMaterial;
use App\Models\KidListoQuote;
use App\Models\Notification;
use App\Models\Post;
use App\Models\RehabCenter;
use App\Models\Training;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminSearchRepository
{
    private const LIMIT = 5;

    public function posts(string $query): Collection
    {
        return Post::query()
            ->select(['id', 'title', 'status'])
            ->where('title', 'like', $this->like($query))
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Post $post) => [
                'id'       => $post->id,
                'title'    => $post->title,
                'subtitle' => ucfirst((string) $post->status),
                'href'     => $this->focusUrl('/admin/posts', (string) $post->title, ['id' => $post->id]),
                'icon'     => 'fas fa-file-pen',
                'group'    => 'Posts',
            ]);
    }

    public function users(string $query): Collection
    {
        $like = $this->like($query);

        return User::query()
            ->select(['id', 'name', 'first_name', 'last_name', 'email', 'role'])
            ->where(function ($inner) use ($like) {
                $inner->where('name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(function (User $user) {
                $name = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->name ?? 'User');

                return [
                    'id'       => $user->id,
                    'title'    => $name,
                    'subtitle' => $this->formatRole((string) $user->role),
                    'href'     => $this->focusUrl('/admin/users', $name, ['id' => $user->id]),
                    'icon'     => 'fas fa-user',
                    'group'    => 'Users',
                ];
            });
    }

    public function contests(string $query): Collection
    {
        return Contest::query()
            ->select(['id', 'title', 'status', 'category'])
            ->where('title', 'like', $this->like($query))
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Contest $contest) => [
                'id'       => $contest->id,
                'title'    => $contest->title,
                'subtitle' => trim(($contest->category ?? '').' · '.ucfirst((string) $contest->status), ' ·'),
                'href'     => $this->focusUrl('/admin/contests', (string) $contest->title, [
                    'id'    => $contest->id,
                    'group' => $contest->category,
                ]),
                'icon'     => 'fas fa-trophy',
                'group'    => 'Contests',
            ]);
    }

    public function rehabCenters(string $query): Collection
    {
        $like = $this->like($query);

        return RehabCenter::query()
            ->select(['id', 'name', 'region', 'province'])
            ->where(function ($inner) use ($like) {
                $inner->where('name', 'like', $like)
                    ->orWhere('region', 'like', $like)
                    ->orWhere('province', 'like', $like);
            })
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (RehabCenter $center) => [
                'id'       => $center->id,
                'title'    => $center->name,
                'subtitle' => trim(($center->province ?? '').', '.($center->region ?? ''), ', '),
                'href'     => $this->focusUrl('/admin/rehab-centers', (string) $center->name, ['id' => $center->id]),
                'icon'     => 'fas fa-hospital',
                'group'    => 'Rehab Centers',
            ]);
    }

    public function trainings(string $query): Collection
    {
        return Training::query()
            ->select(['id', 'title', 'region', 'venue'])
            ->where('title', 'like', $this->like($query))
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Training $training) => [
                'id'       => $training->id,
                'title'    => $training->title,
                'subtitle' => trim(($training->venue ?? '').' · '.($training->region ?? ''), ' ·'),
                'href'     => $this->focusUrl('/admin/trainings', (string) $training->title, ['id' => $training->id]),
                'icon'     => 'fas fa-chalkboard-user',
                'group'    => 'Trainings',
            ]);
    }

    public function iecMaterials(string $query): Collection
    {
        return IecMaterial::query()
            ->select(['id', 'title', 'topic', 'media_type'])
            ->where('title', 'like', $this->like($query))
            ->orderBy('sort_order')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (IecMaterial $material) => [
                'id'       => $material->id,
                'title'    => $material->title,
                'subtitle' => trim(($material->topic ?? '').' · '.($material->media_type ?? ''), ' ·'),
                'href'     => $this->focusUrl('/admin/iec-materials', (string) $material->title, ['id' => $material->id]),
                'icon'     => 'fas fa-photo-film',
                'group'    => 'IEC Materials',
            ]);
    }

    public function translations(string $query): Collection
    {
        $like = $this->like($query);

        return AppTranslation::query()
            ->select(['id', 'key', 'group', 'value_en'])
            ->where(function ($inner) use ($like) {
                $inner->where('key', 'like', $like)
                    ->orWhere('value_en', 'like', $like)
                    ->orWhere('value_tl', 'like', $like);
            })
            ->orderBy('key')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (AppTranslation $row) => [
                'id'       => $row->id,
                'title'    => $row->key,
                'subtitle' => $row->value_en ?: ($row->group ?? 'Translation'),
                'href'     => $this->focusUrl('/admin/app-translations', (string) $row->key, [
                    'id'    => $row->id,
                    'group' => $row->group,
                ]),
                'icon'     => 'fas fa-language',
                'group'    => 'App Translations',
            ]);
    }

    public function lookups(string $query): Collection
    {
        $like = $this->like($query);

        return AppOption::query()
            ->select(['id', 'group', 'key', 'label_en'])
            ->where(function ($inner) use ($like) {
                $inner->where('key', 'like', $like)
                    ->orWhere('label_en', 'like', $like)
                    ->orWhere('label_tl', 'like', $like);
            })
            ->orderBy('group')
            ->orderBy('sort_order')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (AppOption $option) => [
                'id'       => $option->id,
                'title'    => $option->label_en ?: $option->key,
                'subtitle' => $option->group,
                'href'     => $this->focusUrl('/admin/lookups', (string) ($option->label_en ?: $option->key), [
                    'id'    => $option->id,
                    'group' => $option->group,
                ]),
                'icon'     => 'fas fa-sliders',
                'group'    => 'Lookups',
            ]);
    }

    public function hopeDirectory(string $query): Collection
    {
        return HopeDirectoryOrganization::query()
            ->select(['id', 'name', 'category'])
            ->where('name', 'like', $this->like($query))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (HopeDirectoryOrganization $org) => [
                'id'       => $org->id,
                'title'    => $org->name,
                'subtitle' => $org->category,
                'href'     => $this->focusUrl('/admin/hope-directory', (string) $org->name, ['id' => $org->id]),
                'icon'     => 'fas fa-address-book',
                'group'    => 'Hope Directory',
            ]);
    }

    public function hopeEvents(string $query): Collection
    {
        return HopeEvent::query()
            ->select(['id', 'title', 'status', 'audience'])
            ->where('title', 'like', $this->like($query))
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (HopeEvent $event) => [
                'id'       => $event->id,
                'title'    => $event->title,
                'subtitle' => trim(($event->audience ?? '').' · '.($event->status ?? ''), ' ·'),
                'href'     => $this->focusUrl('/admin/hope-events', (string) $event->title, ['id' => $event->id]),
                'icon'     => 'fas fa-calendar-days',
                'group'    => 'Hope Events',
            ]);
    }

    public function careSupport(string $query): Collection
    {
        $like = $this->like($query);

        return CareSupportResource::query()
            ->select(['id', 'title_en', 'category'])
            ->where(function ($inner) use ($like) {
                $inner->where('title_en', 'like', $like)
                    ->orWhere('title_tl', 'like', $like);
            })
            ->orderBy('title_en')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (CareSupportResource $resource) => [
                'id'       => $resource->id,
                'title'    => $resource->title_en,
                'subtitle' => str_replace('_', ' ', (string) $resource->category),
                'href'     => $this->focusUrl('/admin/care-support', (string) $resource->title_en, ['id' => $resource->id]),
                'icon'     => 'fas fa-handshake-angle',
                'group'    => 'Care Support',
            ]);
    }

    public function blockedWords(string $query): Collection
    {
        return BlockedWord::query()
            ->select(['id', 'word', 'is_active'])
            ->where('word', 'like', $this->like($query))
            ->orderBy('word')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (BlockedWord $word) => [
                'id'       => $word->id,
                'title'    => $word->word,
                'subtitle' => $word->is_active ? 'Active' : 'Inactive',
                'href'     => $this->focusUrl('/admin/word-blocking', (string) $word->word, ['id' => $word->id]),
                'icon'     => 'fas fa-ban',
                'group'    => 'Word Blocking',
            ]);
    }

    public function notifications(string $query): Collection
    {
        return Notification::query()
            ->select(['id', 'title', 'topic'])
            ->where('title', 'like', $this->like($query))
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Notification $notification) => [
                'id'       => $notification->id,
                'title'    => $notification->title,
                'subtitle' => $notification->topic ?: 'Notification',
                'href'     => $this->focusUrl('/admin/notifications', (string) $notification->title, ['id' => $notification->id]),
                'icon'     => 'fas fa-bell',
                'group'    => 'Notifications',
            ]);
    }

    public function quotes(string $query): Collection
    {
        $like = $this->like($query);

        return KidListoQuote::query()
            ->select(['id', 'message_en', 'attribution'])
            ->where(function ($inner) use ($like) {
                $inner->where('message_en', 'like', $like)
                    ->orWhere('message_tl', 'like', $like)
                    ->orWhere('attribution', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (KidListoQuote $quote) => [
                'id'       => $quote->id,
                'title'    => Str::limit((string) $quote->message_en, 72),
                'subtitle' => $quote->attribution ?: 'Kid Listo',
                'href'     => $this->focusUrl('/admin/settings', (string) $quote->message_en, ['id' => $quote->id]),
                'icon'     => 'fas fa-quote-left',
                'group'    => 'Kid Listo Quotes',
            ]);
    }

    private function focusUrl(string $path, string $query, array $extra = []): string
    {
        $params = array_filter([
            'q'     => Str::limit(trim($query), 80, ''),
            'id'    => $extra['id'] ?? null,
            'group' => $extra['group'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return $path.(empty($params) ? '' : '?'.http_build_query($params));
    }

    private function like(string $query): string
    {
        $safe = str_replace(['%', '_'], '', trim($query));

        return '%'.$safe.'%';
    }

    private function formatRole(string $role): string
    {
        return collect(explode('_', $role))
            ->filter()
            ->map(fn (string $part) => ucfirst($part))
            ->implode(' ');
    }
}
