<?php
namespace Signify\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Director;
use Signify\Models\CSPViolation;
use Signify\Models\CSPDocument;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBDatetime;
use Signify\Extensions\SecurityHeaderControllerExtension;

class CSPViolationsController extends Controller
{
    const REPORT_TIME = 'ReportedTime';

    const DISPOSITION = 'Disposition';

    const BLOCKED_URI = 'BlockedURI';

    const EFFECTIVE_DIRECTIVE = 'EffectiveDirective';

    const DOCUMENT_URI = 'URI';

    const REPORT_DIRECTIVE = 'ReportDirective';


    public function index(HTTPRequest $request)
    {
        if (!$request->isPOST() || !$this->isSameOrigin($request) || !$this->isReport($request)) {
            return $this->httpError(400);
        }

        // Depending on which directive was used to generate the report, the format will be slightly different.
        // We must do some pre-processing on the report to normalise the data.
        $json = json_decode($request->getBody(), true);
        if (isset($json['csp-report'])) {
            // This report was sent as a result of the "report-uri" directive.
            $report = $json['csp-report'];
            $report[self::REPORT_TIME] = DBDatetime::now()->getValue();
            $report[self::REPORT_DIRECTIVE] = 'report-uri';
            $this->processReport($report);
        } else {
            // This report was sent as a result of the "report-to" directive.
            // There may be multiple reports in the one request.
            foreach ($json as $reportWrapper) {
                if ($reportWrapper['type'] == 'csp-violation') {
                    $report = $reportWrapper['body'];
                    // 'age' is the number of milliseconds since the report was generated.
                    $report[self::REPORT_TIME] = DBField::create_field('Datetime', time() - ($reportWrapper['age'] / 1000))->getValue();
                    $report[self::REPORT_DIRECTIVE] = 'report-to';
                    $this->processReport($report);
                }
            }
        }
    }

    /**
     * Process a Content Security Policy violation report.
     * Creates or updates the relevant CSPViolation object.
     * @param array $cspReport
     */
    public function processReport($cspReport)
    {
        $violation = $this->getOrCreateViolation($cspReport);
        $this->setDocument($cspReport, $violation);
        $violation->Violations++;
        $reportTime = $this->getDataForAttribute($cspReport, self::REPORT_TIME);
        if ($violation->{self::REPORT_TIME} === null || $violation->{self::REPORT_TIME} < $reportTime) {
            $violation->{self::REPORT_TIME} = $reportTime;
        }
        $violation->write();
    }

    /**
     * If this violation has been previously reported, get that violation object.  Otherwise, create a new one.
     * @param array $cspReport
     * @return CSPViolation
     */
    protected function getOrCreateViolation($cspReport)
    {
        $violationData = [
            self::DISPOSITION => $this->getDataForAttribute($cspReport, self::DISPOSITION),
            self::BLOCKED_URI => $this->getDataForAttribute($cspReport, self::BLOCKED_URI),
            self::EFFECTIVE_DIRECTIVE => $this->getDataForAttribute($cspReport, self::EFFECTIVE_DIRECTIVE),
        ];

        $violation = CSPViolation::get()->filter($violationData)->first();
        if (!$violation) {
            $violationData['Violations'] = 0;
            $violation = CSPViolation::create($violationData);
        }

        return $violation;
    }

    /**
     * Set the document-uri for a given violation based on the report.
     * @param array $cspReport
     * @param CSPViolation $violation
     */
    protected function setDocument($cspReport, $violation)
    {
        $documentURI = $this->getDataForAttribute($cspReport, self::DOCUMENT_URI);
        // If the document is already added to this violation, no need to re-add it.
        if ($violation->Documents()->find('URI', $documentURI)) {
            return;
        }

        $documentData = [
            self::DOCUMENT_URI => $documentURI,
        ];
        $document = CSPDocument::get()->filter($documentData)->first();

        if (!$document) {
            $document = CSPDocument::create($documentData);
            $siteTreeLink = $documentURI;
            if (!Director::makeRelative($siteTreeLink)) {
                // SiteTree::get_by_link returns null, see https://github.com/silverstripe/silverstripe-cms/issues/2580
                $siteTreeLink = RootURLController::get_homepage_link();
            }
            if ($siteTree = SiteTree::get_by_link($siteTreeLink)) {
                $document->SiteTreeID = $siteTree->ID;
            }
            $document->write();
        }

        $violation->Documents()->add($document);
    }

    /**
     * Get the data from the report for a given attribute.
     * The reports generated by the report-to and report-uri directives have different keys.
     * @param array $cspReport
     * @param string $attribute
     * @return mixed
     */
    protected function getDataForAttribute($cspReport, $attribute)
    {
        if ($cspReport[self::REPORT_DIRECTIVE] == 'report-uri') {
            switch ($attribute) {
                case self::REPORT_TIME:
                    return $cspReport[self::REPORT_TIME];
                case self::DISPOSITION:
                    if (!empty($cspReport['disposition'])) {
                        return $cspReport['disposition'];
                    } else {
                        // Firefox doesn't report the disposition.
                        return 'unknown';
                    }
                    return ;
                case self::BLOCKED_URI:
                    return $cspReport['blocked-uri'];
                case self::EFFECTIVE_DIRECTIVE:
                    if (!empty($cspReport['effective-directive'])) {
                        return $cspReport['effective-directive'];
                    } else {
                        // Firefox doesn't report the effective directive.
                        return $cspReport['violated-directive'];
                    }
                case self::DOCUMENT_URI:
                    return $cspReport['document-uri'];
            }
        } elseif ($cspReport[self::REPORT_DIRECTIVE] == 'report-to') {
            switch ($attribute) {
                case self::REPORT_TIME:
                    return $cspReport[self::REPORT_TIME];
                case self::DISPOSITION:
                    return $cspReport['disposition'];
                case self::BLOCKED_URI:
                    return $cspReport['blockedURL'];
                case self::EFFECTIVE_DIRECTIVE:
                    return $cspReport['effectiveDirective'];
                case self::DOCUMENT_URI:
                    return $cspReport['documentURL'];
            }
        }

        // This should never be hit...
        return null;
    }

    /**
     * If the origin header is set, return true if it is the same as the current absolute base URL.
     *
     * @param HTTPRequest $request
     * @return boolean
     */
    protected function isSameOrigin(HTTPRequest $request)
    {
        $origin = $request->getHeader('origin');

        // The origin header may not be set for report-to requests, so null must be considered sameorigin.
        if (SecurityHeaderControllerExtension::config()->get('use_report_to') && $origin === null) {
            return true;
        }

        // If not using report-to, or the origin header is set, only allow same origin requests.
        return $origin == rtrim(Director::absoluteBaseURL(), '/');
    }

    /**
     * Returns true if the content-type of the request is a valid CSP report value.
     * @param HTTPRequest $request
     * @return boolean
     */
    protected function isReport(HTTPRequest $request)
    {
        return in_array($request->getHeader('content-type'), [
            'application/csp-report', // from report-uri directive
            'application/reports+json', // from report-to directive
            'application/json', // fallback
        ]);
    }

}
