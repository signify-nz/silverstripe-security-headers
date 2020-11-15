<?php

class SecurityHeaderRequestFilter extends SS_Object implements RequestFilter
{

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

    public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model)
    {
        // Return true to continue processing.
        return true;
    }

    public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model)
    {
        $config = Config::inst()->forClass(__CLASS__);

        $headersConfig = (array) $config->get('headers');
        if (empty($headersConfig['global'])) {
            // Return true to send the response.
            return true;
        }

        $headersToSend = $headersConfig['global'];
        if ($config->get('enable_reporting') && $config->get('use_report_to')) {
            $this->addReportToHeader($headersToSend);
        }

        foreach ($headersToSend as $header => $value) {
            if (empty($value)) {
                continue;
            }
            $value = preg_replace('/\v/', '', $value);

            if ($header === 'Content-Security-Policy') {
                if ($this->isCSPReportingOnly()) {
                    $header = 'Content-Security-Policy-Report-Only';
                }

                if ($config->get('enable_reporting')) {
                    // Add or update report-uri directive.
                    if (strpos($value, 'report-uri')) {
                        $value = str_replace('report-uri', $this->getReportURIDirective(), $value);
                    } else {
                        $value = rtrim($value, ';') . "; {$this->getReportURIDirective()};";
                    }

                    // Add report-to directive.
                    // Note that unlike report-uri, only the first endpoint is used if multiple are declared.
                    if ($config->get('use_report_to')) {
                        if (strpos($value, 'report-to') === false) {
                            $value = rtrim($value, ';') . "; {$this->getReportToDirective()};";
                        }
                    }
                }
            }
            $this->extend('updateHeader', $header, $value, $request);
            $response->addHeader($header, $value);
        }

        // Return true to send the response.
        return true;
    }

    /**
     * Returns true if the Content-Security-Policy-Report-Only header should be used.
     * @return boolean
     */
    public function isCSPReportingOnly()
    {
        return SiteConfig::current_site_config()->CSPReportingOnly;
    }

    protected function getReportURI()
    {
        return Config::inst()->get(__CLASS__, 'report_uri');
    }

    protected function getIncludeSubdomains()
    {
        return Config::inst()->get(__CLASS__, 'report_to_subdomains');
    }

    protected function getReportToGroup()
    {
        return Config::inst()->get(__CLASS__, 'report_to_group');
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
