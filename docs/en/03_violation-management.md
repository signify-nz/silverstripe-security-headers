# Content-Security-Policy violation report management

If reporting is enabled, there will inevitably be reports. The CSP violations report 
enables a user with suitable permissions to manage and remove reports.

However, if reviewing violation reports is intermittent, there is a dev/task to delete older reports, 
to keep the number of stored reports manageable.

The task is only available if [symbiote/silverstripe-queuedjobs][] is installed.

The retention period (visible in the task description) can be configured as follows:

```yaml
Signify\Jobs\RemoveOldCSPViolationsJob:
  retention_period: P1M
```

The `retention_period` is a duration formatted as described in the [PHP DateInterval constructor][].
Reports that have not been modified within the retention period will be deleted.

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
