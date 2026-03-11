<?php

namespace App\Console\Commands;

use App\Services\PostService;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature   = 'dape-ma:publish-scheduled-posts';

    protected $description = 'Auto-publish any posts whose publish_date has been reached and status is scheduled.';

    public function __construct(
        private readonly PostService $postService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->postService->publishScheduled();

        if ($count > 0) {
            $this->info("[DAPE-MA] Published {$count} scheduled post(s).");
        } else {
            $this->line('[DAPE-MA] No posts due for publishing.');
        }

        return self::SUCCESS;
    }
}
