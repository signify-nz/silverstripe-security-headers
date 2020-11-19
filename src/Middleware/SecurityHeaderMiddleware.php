<?php

namespace Signify\Middleware;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\SiteConfig\SiteConfig;

class SecurityHeaderMiddleware implements HTTPMiddleware
{
    use Configurable, Extensible;

    /**
     * An array of HTTP headers.
     * @config
     * @var array
     */
    private static $headers = [
        'global' => array(),
    ];

    /**
     * Whether to automatically add the CMS report endpoint to the CSP config.
     * @config
     * @var string
     */
    private static $enable_reporting = true;

    /**
     * The URI to report CSP violations to.
     * See routes.yml
     * @config
     * @var string
     */
    private static $report_uri = 'cspviolations/report';

    /**
     * Whether to use the report-to header and CSP directive.
     * @config
     * @var string
     */
    private static $use_report_to = false;

    /**
     * Whether subdomains should report to the same endpoint.
     * @config
     * @var string
     */
    private static $report_to_subdomains = false;

    /**
     * The group name for the report-to CSP directive.
     * @config
     * @var string
     */
    private static $report_to_group = 'signify-csp-violation';

    public function process(HTTPRequest $request, callable $delegate)
    {
        $response = $delegate($request);

        $headersConfig = (array) $this->config()->get('headers');
        if (empty($headersConfig['global'])) {
            return $response;
        }

        $headersToSend = $headersConfig['global'];
        if ($this->config()->get('enable_reporting') && $this->config()->get('use_report_to')) {
            $this->addReportToHeader($headersToSend);
        }

        foreach ($headersToSend as $header => $value) {
            if (empty($value)) {
                continue;
            }
            $value = preg_replace('/\v/', '', $value);

            if ($header === 'Content-Security-Policy') {
                if ($this->isCSPReportingOnly($request)) {
                    $header = 'Content-Security-Policy-Report-Only';
                }

                if ($this->config()->get('enable_reporting')) {
                    // Add or update report-uri directive.
                    if (strpos($value, 'report-uri')) {
                        $value = str_replace('report-uri', $this->getReportURIDirective(), $value);
                    } else {
                        $value = rtrim($value, ';') . "; {$this->getReportURIDirective()};";
                    }

                    // Add report-to directive.
                    // Note that unlike report-uri, only the first endpoint is used if multiple are declared.
                    if ($this->config()->get('use_report_to')) {
                        if (strpos($value, 'report-to') === false) {
                            $value = rtrim($value, ';') . "; {$this->getReportToDirective()};";
                        }
                    }
                }
            }
            $this->extend('updateHeader', $header, $value, $request);
            if ($value) {
                $response->addHeader($header, $value);
            }
        }

        return $response;
    }

    /**
     * Returns true if the Content-Security-Policy-Report-Only header should be used.
     * @return boolean
     */
    public function isCSPReportingOnly($request)
    {
        return SiteConfig::current_site_config()->CSPReportingOnly;
    }

    protected function getReportURI()
    {
        return $this->config()->get('report_uri');
    }

    protected function getIncludeSubdomains()
    {
        return $this->config()->get('report_to_subdomains');
    }

    protected function getReportToGroup()
    {
        return $this->config()->get('report_to_group');
    }

    protected function getReportURIDirective()
    {
        return "report-uri {$this->getReportURI()}";
    }

    protected function getReportToDirective()
    {
        return "report-to {$this->getReportToGroup()}";
    }

    protected function addReportToHeader(&$headers)
    {
        if (array_key_exists('Report-To', $headers)) {
            $headers['Report-To'] = $headers['Report-To'] . ',' . $this->getReportToHeader();
        } else {
            $headers['Report-To'] = $this->getReportToHeader();
        }
    }

    protected function getReportToHeader()
    {
        $header = [
            'group' => $this->getReportToGroup(),
            'max_age' => 1800,
            'endpoints' => [[
                'url' => Director::absoluteURL($this->getReportURI()),
            ],],
            'include_subdomains' => $this->getIncludeSubdomains(),
        ];
        return json_encode($header);
    }

}
