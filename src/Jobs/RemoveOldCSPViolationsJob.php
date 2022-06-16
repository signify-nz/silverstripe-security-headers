<?php
namespace Signify\Jobs;

use DateInterval;
use DateTime;
use Signify\Models\CSPViolation;
use Signify\Reports\CSPViolationsReport;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataList;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class RemoveOldCSPViolationsJob extends AbstractQueuedJob
{
    /**
     * Reports that were last changed longer than this period ago can be deleted.
     *
     * The string must be in DateInterval duration format.
     * @see https://www.php.net/manual/en/dateinterval.construct.php
     *
     * @var string
     */
    private static $retention_period = 'P1M';

    public function setup()
    {
        $retention = Config::inst()->get(self::class, 'retention_period');
        $retention = new DateInterval($retention);

        $date = new DateTime();
        $date->sub($retention);
        $this->jobData->retentionDate = $date->format(DateTime::ATOM);

        $this->jobData->reportsDeleted = 0;

        $this->totalSteps = $this->getItemsList()->count();
    }

    public function process()
    {
        $batchSize = Config::inst()->get(CSPViolationsReport::class, 'deletion_batch_size');

        $oldReports = $this->getItemsList()->limit($batchSize);

        $delta = 0;
        /** @var CSPViolation $report */
        foreach ($oldReports as $report) {
            $report->delete();
            $delta++;
        }

        $this->jobData->reportsDeleted += $delta;
        $this->currentStep += $delta;

        if ($delta < $batchSize) {
            $this->isComplete = true;
            print 'Removed ' . number_format($this->jobData->reportsDeleted) . ' reports.';
        }
    }

    public function getTitle()
    {
        return 'Remove old CSP Violation reports';
    }

    private function getItemsList(): DataList
    {
        return CSPViolation::get()->filter(['ReportedTime:LessThan' => $this->jobData->retentionDate]);
    }
}

