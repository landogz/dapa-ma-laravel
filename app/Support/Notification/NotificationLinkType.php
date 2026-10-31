<?php

namespace App\Support\Notification;

use App\Models\CareSupportResource;
use App\Models\Contest;
use App\Models\HopeDirectoryOrganization;
use App\Models\HopeEvent;
use App\Models\IecMaterial;
use App\Models\Post;
use App\Models\RehabCenter;
use App\Models\Training;

final class NotificationLinkType
{
    public const POST = 'post';

    public const HOPE_EVENT = 'hope_event';

    public const CONTEST = 'contest';

    public const IEC_MATERIAL = 'iec_material';

    public const REHAB_CENTER = 'rehab_center';

    public const TRAINING = 'training';

    public const CARE_SUPPORT = 'care_support';

    public const HOPE_DIRECTORY = 'hope_directory';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::POST,
            self::HOPE_EVENT,
            self::CONTEST,
            self::IEC_MATERIAL,
            self::REHAB_CENTER,
            self::TRAINING,
            self::CARE_SUPPORT,
            self::HOPE_DIRECTORY,
        ];
    }

    public static function exists(string $type, int $id): bool
    {
        return match ($type) {
            self::POST => Post::query()->whereKey($id)->exists(),
            self::HOPE_EVENT => HopeEvent::query()->whereKey($id)->exists(),
            self::CONTEST => Contest::query()->whereKey($id)->exists(),
            self::IEC_MATERIAL => IecMaterial::query()->whereKey($id)->exists(),
            self::REHAB_CENTER => RehabCenter::query()->whereKey($id)->exists(),
            self::TRAINING => Training::query()->whereKey($id)->exists(),
            self::CARE_SUPPORT => CareSupportResource::query()->whereKey($id)->exists(),
            self::HOPE_DIRECTORY => HopeDirectoryOrganization::query()->whereKey($id)->exists(),
            default => false,
        };
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function labels(): array
    {
        return [
            ['value' => self::POST, 'label' => 'Post'],
            ['value' => self::HOPE_EVENT, 'label' => 'Hope Event'],
            ['value' => self::CONTEST, 'label' => 'Contest'],
            ['value' => self::IEC_MATERIAL, 'label' => 'IEC Material'],
            ['value' => self::REHAB_CENTER, 'label' => 'Treatment Rehabilitation Center'],
            ['value' => self::TRAINING, 'label' => 'Training'],
            ['value' => self::CARE_SUPPORT, 'label' => 'Care Support'],
            ['value' => self::HOPE_DIRECTORY, 'label' => 'Hope Directory'],
        ];
    }

    public static function labelFor(string $type): string
    {
        foreach (self::labels() as $row) {
            if ($row['value'] === $type) {
                return $row['label'];
            }
        }

        return $type;
    }

    public static function adminPath(string $type): ?string
    {
        return match ($type) {
            self::POST => '/admin/posts',
            self::HOPE_EVENT => '/admin/hope-events',
            self::CONTEST => '/admin/contests',
            self::IEC_MATERIAL => '/admin/iec-materials',
            self::REHAB_CENTER => '/admin/rehab-centers',
            self::TRAINING => '/admin/trainings',
            self::CARE_SUPPORT => '/admin/care-support',
            self::HOPE_DIRECTORY => '/admin/hope-directory',
            default => null,
        };
    }

    public static function adminQueryKey(string $type): string
    {
        return match ($type) {
            self::POST => 'post',
            self::HOPE_EVENT => 'event',
            default => 'item',
        };
    }

    /**
     * @return array{id:int,title:string,admin_url:string}|null
     */
    public static function resolveTarget(string $type, int $id): ?array
    {
        $title = match ($type) {
            self::POST => Post::query()->whereKey($id)->value('title'),
            self::HOPE_EVENT => HopeEvent::query()->whereKey($id)->value('title'),
            self::CONTEST => Contest::query()->whereKey($id)->value('title'),
            self::IEC_MATERIAL => IecMaterial::query()->whereKey($id)->value('title'),
            self::REHAB_CENTER => RehabCenter::query()->whereKey($id)->value('name'),
            self::TRAINING => Training::query()->whereKey($id)->value('title'),
            self::CARE_SUPPORT => CareSupportResource::query()->whereKey($id)->value('title_en'),
            self::HOPE_DIRECTORY => HopeDirectoryOrganization::query()->whereKey($id)->value('name'),
            default => null,
        };

        if ($title === null || $title === '') {
            return null;
        }

        $path = self::adminPath($type);
        if ($path === null) {
            return null;
        }

        $query = self::adminQueryKey($type);

        return [
            'id' => $id,
            'title' => (string) $title,
            'admin_url' => $path.'?'.$query.'='.urlencode((string) $id),
        ];
    }
}
