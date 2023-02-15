# Content-Security-Policy violation report management

If reporting is enabled, there will inevitably be reports. The CSP violations report 
enables a user with suitable permissions to manage and remove reports.

However, if reviewing violation reports is intermittent, there are two `dev/task`s
that can be run to remove older reports and remove unreferenced document URIs.

Additionally, the system can be configured to automatically run jobs to remove older reports and
remove unreferenced document URIs.

## Available tasks

The tasks are only available if [symbiote/silverstripe-queuedjobs][] is installed.
Both tasks simply queue up an appropriate job to run immediately.

### Remove old CSP violation reports

`dev/tasks/Signify-Tasks-RemoveOldCSPViolationsTask`

Delete older reports, to keep the number of stored reports manageable. Any report that
has not been modified within the retention period will be deleted.

The retention period (visible in the task description) can be configured as follows:

```yaml
Signify\Jobs\RemoveOldCSPViolationsJob:
  retention_period: P1M
```

The `retention_period` is a duration, formatted as described in the [PHP DateInterval constructor][]
documentation. When the report deletion job has completed, a job to remove any unreferenced
CSP Document  URIs will be queued.

### Remove unreferenced CSP Document URIs

`dev/tasks/Signify-Tasks-RemoveUnreferencedCSPDocumentsTask`

Remove all CSP Document URIs (`CSPDocument`) that does not have any linked violation
report (`CSPViolation`).

[symbiote/silverstripe-queuedjobs]: https://github.com/symbiote/silverstripe-queuedjobs
[PHP DateInterval constructor]: https://www.php.net/manual/en/dateinterval.construct.php

## Automatically delete older reports

To regularly delete older violations reports automatically, configure a default job,
with something like the following:

```yaml
---
After: '#queuedjobsettings'
---
SilverStripe\Core\Injector\Injector:
  Symbiote\QueuedJobs\Services\QueuedJobService:
    properties:
      defaultJobs:
        RemoveOldCSPViolationsJob:
          type: 'Signify\Jobs\RemoveOldCSPViolationsJob'
          filter:
            JobTitle: 'Remove old CSP Violation reports'
          construct:
            key: value
          startDateFormat: 'Y-m-d H:i:s'
          startTimeString: 'tomorrow 03:00'
          recreate: true
          jobType: 3
```

Removing unreferenced CSP Document URIs will be queued automatically when the violations
report deletions have completed.
