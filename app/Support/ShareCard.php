<?php

namespace App\Support;

final class ShareCard
{
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $typeLabel,
        public readonly string $title,
        public readonly string $description,
        public readonly string $imageUrl,
        public readonly string $imageSrc,
        public readonly ?string $meta,
        public readonly string $canonicalUrl,
        public readonly string $appPath,
        public readonly bool $imageIsLogo = false,
    ) {
    }
}
