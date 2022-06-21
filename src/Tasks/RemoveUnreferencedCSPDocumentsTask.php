<?php
namespace Signify\Tasks;

use Signify\Jobs\RemoveUnreferencedCSPDocumentJob;
use SilverStripe\Dev\BuildTask;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class RemoveUnreferencedCSPDocumentsTask extends BuildTask
{
    protected $title = 'Remove unreferenced CSP Document URIs';

    protected $description = 'CSP Document URIs that are not referenced by a CSP violation report can be safely removed.';

    /**
     * {@inheritDoc}
     * @see \SilverStripe\Dev\BuildTask::run()
     */
    public function run($request)
    {
        $deletionJob = new RemoveUnreferencedCSPDocumentJob();

        $jobId = singleton(QueuedJobService::class)->queueJob($deletionJob);

        print "Job queued with ID $jobId\n";
    }

    public function isEnabled()
    {
        return parent::isEnabled() && class_exists(QueuedJobService::class);
    }

}