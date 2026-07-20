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
        $documentURI = $this->getRelativeURI($report['document-uri']);
        $blockedURI = $this->getRelativeURI($report['blocked-uri']);

        $document = CSPDocument::get_or_create($documentURI);
        
        $violation = CSPViolation::get()->filter([
            'DocumentID' => $document->ID,
            'Disposition' => $report['disposition'],
            'BlockedURI' => $blockedURI,
            'EffectiveDirective' => $report['effective-directive'],
            'ViolationCode' => isset($report['status-code']) ? $report['status-code'] : null,
        ])->first();

        if (!$violation) {
            $violation = CSPViolation::create([
                'DocumentID' => $document->ID,
                'Disposition' => $report['disposition'],
                'BlockedURI' => $blockedURI,
                'EffectiveDirective' => $report['effective-directive'],
                'ViolationCode' => isset($report['status-code']) ? $report['status-code'] : null,
            ]);
        }

        $violation->Violations++;
        $violation->ReportedTime = DBDatetime::now()->getValue();
        $violation->write();
    }

    protected function getRelativeURI($uri)
    {
        if (strpos($uri, Director::absoluteBaseURL()) === 0) {
            return substr($uri, strlen(Director::absoluteBaseURL()));
        }
        return $uri;
    }
}
