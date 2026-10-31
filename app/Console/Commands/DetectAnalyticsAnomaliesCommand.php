<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsAnomalyDetector;
use Illuminate\Console\Command;

class DetectAnalyticsAnomaliesCommand extends Command
{
    protected $signature = 'analytics:detect-anomalies';

    protected $description = 'Detect analytics anomalies from configured alert rules';

    public function handle(AnalyticsAnomalyDetector $detector): int
    {
        $created = $detector->run();
        $this->info("Created {$created} analytics anomaly alert(s).");

        return self::SUCCESS;
    }
}
