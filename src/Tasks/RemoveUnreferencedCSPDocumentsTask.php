<?php

namespace Signify\SecurityHeaders\Tasks;

use Signify\SecurityHeaders\Jobs\RemoveUnreferencedCSPDocumentJob;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class RemoveUnreferencedCSPDocumentsTask extends BuildTask
{
    protected string $title = 'Remove unreferenced CSP Document URIs';

    protected static string $commandName = 'remove-unreferenced-csp-documents';

    protected static string $description =
    'CSP Document URIs that are not referenced by a CSP violation report can be safely removed.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $deletionJob = new RemoveUnreferencedCSPDocumentJob();

        $jobId = singleton(QueuedJobService::class)->queueJob($deletionJob);

        $output->writeln("Job queued with ID $jobId");
        return Command::SUCCESS;
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled() && class_exists(QueuedJobService::class);
    }
}
