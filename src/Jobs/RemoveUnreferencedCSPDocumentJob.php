<?php
namespace Signify\Jobs;

use Signify\Models\CSPDocument;
use Signify\Models\CSPViolation;
use Signify\Reports\CSPViolationsReport;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataList;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use SilverStripe\ORM\DB;

class RemoveUnreferencedCSPDocumentJob extends AbstractQueuedJob
{

    public function setup()
    {
        $this->jobData->lastSeenID = -1;
        $this->jobData->documentsDeleted = 0;
        $this->totalSteps = CSPDocument::get()->count();
    }

    public function process()
    {
        $batchSize = Config::inst()->get(CSPViolationsReport::class, 'deletion_batch_size');

        $deleted = 0;
        $delta = 0;

        // Wrapped in a transaction for performance only.
        try {
            DB::get_conn()->transactionStart();

            $documents = $this->getItemsList()->limit($batchSize);

            $lastDocument = $documents->last();
            if ($lastDocument) {
                $this->jobData->lastSeenID = $lastDocument->ID;
                unset($lastDocument);
            }

            /** @var CSPViolation $document */
            foreach ($documents as $document) {
                if (!$document->CSPViolations()->first()) {
                    # See https://github.com/silverstripe/silverstripe-framework/issues/1903
                    $document->CSPViolations()->removeAll();

                    $document->delete();
                    ++$deleted;
                }
                ++$delta;
            }
        }
        finally {
            DB::get_conn()->transactionEnd();
        }

        $this->jobData->documentsDeleted += $deleted;
        $this->currentStep += $delta;

        if ($delta < $batchSize) {
            $this->isComplete = true;
            print 'Removed ' . number_format($this->jobData->documentsDeleted) . ' unreferenced document URIs.';
        }
    }

    public function getTitle()
    {
        return 'Remove unreferenced CSP Document URIs';
    }

    private function getItemsList(): DataList
    {
        return CSPDocument::get()
            ->filter(['ID:GreaterThan' => $this->jobData->lastSeenID])
            ->sort('ID');
    }
}

