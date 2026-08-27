<?php

namespace Signify\SecurityHeaders\Tasks;

use DateInterval;
use Signify\SecurityHeaders\Jobs\RemoveOldCSPViolationsJob;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class RemoveOldCSPViolationsTask extends BuildTask
{
    protected string $title = 'Remove old CSP violation reports';

    protected static string $commandName = 'remove-old-csp-violations';

    protected static string $description = 'CSP reports that have not been recently reported will be removed.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $deletionJob = new RemoveOldCSPViolationsJob();

        $jobId = singleton(QueuedJobService::class)->queueJob($deletionJob);

        $output->writeln("Job queued with ID $jobId");
        return Command::SUCCESS;
    }

    public static function getDescription(): string
    {
        // Map DateInterval fields to text names. Order is significant.
        static $parts = [
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
            'f' => 'microsecond',
        ];

        $retention = Config::inst()->get(RemoveOldCSPViolationsJob::class, 'retention_period');
        if (!$retention) {
            return parent::getDescription();
        }
        $retention = new DateInterval($retention);

        $duration_parts = [];
        foreach ($parts as $field => $label) {
            if ($retention->$field != 0) {
                // Microseconds are a fraction of a second. Everything else is defined in terms of itself.
                $value = $field === 'f'
                    ? round($retention->$field * 1000000.0, 0, PHP_ROUND_HALF_UP)
                    : $retention->$field;

                // Cheap and nasty pluralisation.
                $duration_parts[] = $value . ' ' . $label . ($value === 1 ? '' : 's');
            }
        }

        // Convert to string e.g. "12 hours, 30 minutes and 10 seconds"
        if (count($duration_parts) > 1) {
            $last = array_pop($duration_parts);
            $duration_string = implode(', ', $duration_parts) . ' and ' . $last;
        } else {
            $duration_string = reset($duration_parts);
        }

        return 'CSP reports that have not been created or modified within the last ' .
            $duration_string . ' will be removed.';
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled() && class_exists(QueuedJobService::class);
    }
}
