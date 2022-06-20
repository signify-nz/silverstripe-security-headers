<?php
namespace Signify\Tasks;

use DateInterval;
use Signify\Jobs\RemoveOldCSPViolationsJob;
use Signify\Reports\CSPViolationsReport;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class RemoveOldCSPViolationsTask extends BuildTask
{
    protected $title = 'Remove old CSP violation reports';

    /**
     * {@inheritDoc}
     * @see \SilverStripe\Dev\BuildTask::run()
     */
    public function run($request)
    {
        $deletionJob = new RemoveOldCSPViolationsJob();

        $jobId = singleton(QueuedJobService::class)->queueJob($deletionJob);

        print "Job queued with ID $jobId\n";
    }

    /**
     * {@inheritDoc}
     * @see \SilverStripe\Dev\BuildTask::getDescription()
     */
    public function getDescription()
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
        $retention = new DateInterval($retention);

        $duration_parts = [];
        foreach ($parts as $field => $label) {
            if ($retention->$field != 0) {
                // Microseconds are a fraction of a second. Everything else is defined in terms of itself.
                $value = $field === 'f' ? round($retention->$field * 1000000.0, 0, PHP_ROUND_HALF_UP) : $retention->$field;

                // Cheap and nasty pluralisation.
                $duration_parts[] = $value . ' ' . $label . ($value === 1 ? '' : 's');
            }
        }

        // Convert to string e.g. "12 hours, 30 minutes and 10 seconds"
        if (count($duration_parts) > 1) {
            $last = array_pop($duration_parts);
            $duration_string = implode(', ', $duration_parts) . ' and ' . $last;
        }
        else {
            $duration_string = reset($duration_parts);
        }

        return 'CSP reports that have not been created or modified within the last ' . $duration_string . ' will be removed.';
    }

    public function isEnabled()
    {
        return parent::isEnabled() && class_exists(QueuedJobService::class);
    }

}