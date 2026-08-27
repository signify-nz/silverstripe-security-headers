<?php
namespace Signify\SecurityHeaders\Jobs;

use Signify\SecurityHeaders\Models\CSPDocument;
use Signify\SecurityHeaders\Models\CSPViolation;
use Signify\SecurityHeaders\Reports\CSPViolationsReport;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataList;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use SilverStripe\ORM\DB;

/**
 * A queued job to remove CSPDocument objects that are no longer referenced by any CSPViolation.
 */
class RemoveUnreferencedCSPDocumentJob extends AbstractQueuedJob
{
    public function __construct()
    {
        $this->totalSteps = CSPDocument::get()->count();
    }

    public function getTitle()
    {
        return _t(
            __CLASS__ . '.TITLE',
            'Remove unreferenced CSP documents'
        );
    }

    public function getJobType()
    {
        return \Symbiote\QueuedJobs\Services\QueuedJob::QUEUED;
    }

    public function process()
    {
        $documents = CSPDocument::get();
        foreach ($documents as $document) {
            $this->currentStep++;
            if ($document->Violations()->count() == 0) {
                $document->delete();
            }
        }
        $this->isComplete = true;
    }
}
