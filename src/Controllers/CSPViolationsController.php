<?php
namespace Signify\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Director;
use Signify\Models\CSPViolation;
use Signify\Models\CSPDocument;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Controllers\RootURLController;

class CSPViolationsController extends Controller
{

    public function index(HTTPRequest $request)
    {
        if (!$request->isPOST() || !$this->isSameOrigin($request) || !$this->isReport($request)) {
            return $this->httpError(400);
        }

        $this->processReport(json_decode($request->getBody(), true)['csp-report']);
    }

    public function processReport($cspReport)
    {
        $violation = $this->getOrCreateViolation($cspReport);
        $this->setDocument($cspReport, $violation);
    }

    public function getOrCreateViolation($cspReport)
    {
        $violationData = [
            'Disposition' => $cspReport['disposition'],
            'BlockedURI' => $cspReport['blocked-uri'],
            'ViolatedDirective' => $cspReport['violated-directive'],
            'EffectiveDirective' => $cspReport['effective-directive'],
        ];

        $violation = CSPViolation::get()->filter($violationData)->first();
        if (!$violation) {
            $violationData['Violations'] = 0;
            $violation = CSPViolation::create($violationData);
        }
        $violation->Violations++;
        $violation->write();

        return $violation;
    }

    public function setDocument($cspReport, $violation)
    {
        // If the document is already added to this violation, no need to re-add it.
        if ($violation->Documents()->find('URI', $cspReport['document-uri'])) {
            return;
        }

        $documentData = [
            'URI' => $cspReport['document-uri'],
        ];
        $document = CSPDocument::get()->filter($documentData)->first();
        if (!$document) {
            $document = CSPDocument::create($documentData);
            $sitetreeLink = $cspReport['document-uri'];
            if (!Director::makeRelative($sitetreeLink)) {
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

    protected function isSameOrigin(HTTPRequest $request)
    {
        return $request->getHeader('origin') == rtrim(Director::absoluteBaseURL(), '/');
    }

    protected function isReport(HTTPRequest $request)
    {
        return in_array($request->getHeader('content-type'), ['application/csp-report', 'application/json']);
    }

}
