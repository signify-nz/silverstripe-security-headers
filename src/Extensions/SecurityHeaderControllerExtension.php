<?php

namespace Signify\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class SecurityHeaderControllerExtension extends Extension
{
    use Configurable;

    /**
     * An array of HTTP headers.
     * @config
     * @var array
     */
    private static $headers;

    /**
     * The URI to report CSP violations to.
     * See routes.yml
     * @config
     * @var string
     */
    private static $report_uri;

    public function onAfterInit()
    {
        $response = $this->owner->getResponse();

        $headersToSend = (array) $this->config()->get('headers');

        foreach ($headersToSend as $header => $value) {
            if (empty($value)) {
                continue;
            }
            $value = preg_replace('/\v/', '', $value);

            // Add the report-uri directive.
            // TODO add or amend report-to directive and Report-To header.
            // SEE https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/report-to
            // SEE https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/report-uri for report-uri deprecation
            // SEE https://canhas.report/csp-report-to
            // SEE https://w3c.github.io/reporting/
            if ($header === 'Content-Security-Policy') {
                if ($this->isCSPReportingOnly()) {
                    $header = 'Content-Security-Policy-Report-Only';
                }

                if (strpos($value, 'report-uri')) {
                    $value = str_replace('report-uri', "report-uri {$this->getReportURI()}", $value);
                } else {
                    $value = rtrim($value, ';') . "; {$this->getReportURIDirective()}";
                }
            }

            $response->addHeader($header, $value);
        }
    }

    /**
     * Returns true if the Content-Security-Policy-Report-Only header should be used.
     * @return boolean
     */
    public function isCSPReportingOnly()
    {
        // If the CSPReportingOnly field doesn't exist on SiteConfig yet, we're not in report-only mode.
        // This is necessary to let dev/build run safely the first time SecurityHeaderSiteconfigExtension is applied.
        $table = DataObject::getSchema()->baseDataTable(SiteConfig::class);
        if (!in_array('CSPReportingOnly', array_keys(DB::field_list($table)))) {
            return false;
        }
        return SiteConfig::current_site_config()->CSPReportingOnly;
    }

    protected function getReportURI()
    {
        return $this->config()->get('report_uri');
    }

    protected function getReportURIDirective()
    {
        return "report-uri {$this->getReportURI()};";
    }

}
