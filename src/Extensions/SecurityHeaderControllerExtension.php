<?php

namespace Guttmann\SilverStripe\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Config\Configurable;

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
            // Add the report-uri directive.
            // TODO add or amend report-to directive and Report-To header.
            // SEE https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/report-to
            // SEE https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/report-uri for report-uri deprecation
            // SEE https://canhas.report/csp-report-to
            if ($header === 'Content-Security-Policy') {
                $value = preg_replace('/\v/', '', $value);
                if (strpos($value, 'report-uri')) {
                    $value = str_replace('report-uri', "report-uri {$this->getReportURI()}", $value);
                } else {
                    $value = rtrim($value, ';') . "; {$this->getReportURIDirective()}";
                }
            }

            $response->addHeader($header, $value);
        }
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
