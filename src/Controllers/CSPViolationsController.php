<?php

namespace Signify\SecurityHeaders\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Director;
use Signify\SecurityHeaders\Models\CSPViolation;
use Signify\SecurityHeaders\Models\CSPDocument;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBDatetime;
use Signify\SecurityHeaders\Middleware\SecurityHeaderMiddleware;

class CSPViolationsController extends Controller
{
    public const REPORT_TIME = 'ReportedTime';

    private static $allowed_actions = [
        'index',
    ];

    public function index(HTTPRequest $request)
    {
        if (!$request->isPOST()) {
            return $this->httpError(405);
        }

        $contentType = $request->getHeader('Content-Type');
        if ($contentType !== 'application/csp-report' && $contentType !== 'application/json') {
            return $this->httpError(400);
        }

        $json = json_decode($request->getBody(), true);
        if (!$json || !isset($json['csp-report'])) {
            return $this->httpError(400);
        }

        $report = $json['csp-report'];
        $this->processReport($report);

        return $this->getResponse();
    }

    protected function processReport(array $report)
    {
        // Values are truncated before lookup as well as before write so that repeated reports
        // with over-long values still match the record created by the first report.
        $documentURI = $this->truncateToFieldSize(
            CSPDocument::class,
            'URI',
            $this->getRelativeURI($report['document-uri'] ?? '')
        );
        $fields = [
            'Disposition' => $report['disposition'] ?? '',
            'BlockedURI' => $this->getRelativeURI($report['blocked-uri'] ?? ''),
            'EffectiveDirective' => $report['effective-directive'] ?? '',
        ];
        foreach ($fields as $field => $value) {
            $fields[$field] = $this->truncateToFieldSize(CSPViolation::class, $field, $value);
        }

        $document = CSPDocument::get_or_create($documentURI);

        $violation = CSPViolation::get()->filter(array_merge(
            ['Documents.ID' => [$document->ID]],
            $fields
        ))->first();

        if (!$violation) {
            $violation = CSPViolation::create($fields);
            $violation->write();
            $violation->Documents()->add($document);
        }

        $violation->Violations++;
        $violation->ReportedTime = DBDatetime::now()->getValue();
        $violation->write();
    }

    /**
     * Truncate a value to fit the size of a Varchar field, since writing a longer value
     * fails DataObject validation.
     */
    protected function truncateToFieldSize(string $class, string $field, string $value): string
    {
        $dbField = singleton($class)->dbObject($field);
        if ($dbField && method_exists($dbField, 'getSize')) {
            return mb_substr($value, 0, $dbField->getSize());
        }
        return $value;
    }

    protected function getRelativeURI($uri)
    {
        if (strpos($uri, Director::absoluteBaseURL()) === 0) {
            return substr($uri, strlen(Director::absoluteBaseURL()));
        }
        return $uri;
    }
}
