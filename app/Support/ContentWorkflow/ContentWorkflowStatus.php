<?php

namespace App\Support\ContentWorkflow;

final class ContentWorkflowStatus
{
    public const DRAFT = 'draft';

    public const PENDING = 'pending_review';

    public const PUBLISHED = 'published';

    public const ALL = [
        self::DRAFT,
        self::PENDING,
        self::PUBLISHED,
    ];
}
