<?php

namespace Signify\Middleware;

use Signify\Extensions\SecurityHeaderSiteconfigExtension;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;

class SecurityHeaderMiddleware implements HTTPMiddleware
{
    use Configurable;
    use Extensible;

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

    /**
     * Can isCSPReportingOnly be used safely.
     *
     * This is not a config option.
     *
     * @var boolean
     */
    private static $is_csp_reporting_safe = false;


    public function process(HTTPRequest $request, callable $delegate)
    {
        $response = $delegate($request);

        $headersConfig = (array) $this->config()->get('headers');
        if (empty($headersConfig['global'])) {
            return $response;
        }

        $headersToSend = $headersConfig['global'];

        if ($this->isReporting() && $this->config()->get('use_report_to')) {
            $this->addReportToHeader($headersToSend);
        }

        // Update CSP header.
        if (array_key_exists('Content-Security-Policy', $headersToSend)) {
            $header = 'Content-Security-Policy';

            if ($this->hasCSP()) {
                $headerValue = $headersToSend['Content-Security-Policy'];

                // Set report only mode if appropriate.
                if ($this->isCSPReportingOnly()) {
                    unset($headersToSend['Content-Security-Policy']);
                    $header = 'Content-Security-Policy-Report-Only';
                }

                // Update CSP header value.
                $headersToSend[$header] = $this->updateCspHeader($headerValue);
            } else {
                unset($headersToSend['Content-Security-Policy']);
            }
        }
        $this->extend('updateHeaders', $headersToSend, $request);

        // Add headers to response.
        foreach ($headersToSend as $header => $value) {
            if (empty($value)) {
                continue;
            }
            $value = preg_replace('/\v/', '', $value);
            $this->extend('updateHeader', $header, $value, $request);
            if ($value) {
                $response->addHeader($header, $value);
            }
        }

        return $response;
    }

    /**
     * Return true if the Disable CSP is unchecked
     *
     * @return boolean
     */
    public function hasCSP()
    {
        return self::isCSPReportingAvailable() &&
            SiteConfig::current_site_config()->CSPReportingOnly != SecurityHeaderSiteconfigExtension::CSP_DISABLE;
    }

    /**
     * Return true if the Disable reporting is unchecked
     *
     * The CMS setting can disable reporting even if the 'enable_reporting' is true
     *
     * @return boolean
     */
    public function isReporting()
    {
        if ($this->hasCSP()) {
            return SiteConfig::current_site_config()->CSPReportingOnly
                != SecurityHeaderSiteconfigExtension::CSP_WITHOUT_REPORTING
                && $this->config()->get('enable_reporting');
        }

        return false;
    }

    /**
     * Returns true if the Content-Security-Policy-Report-Only header should be used.
     *
     * @return boolean
     */
    public function isCSPReportingOnly()
    {
        if (
            self::isCSPReportingAvailable() &&
            SiteConfig::current_site_config()->CSPReportingOnly == SecurityHeaderSiteconfigExtension::CSP_REPORTING_ONLY
        ) {
            return true;
        }

        return false;
    }

    protected function getReportURI()
    {
        return Director::absoluteURL($this->config()->get('report_uri'));
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
                'url' => $this->getReportURI(),
            ],],
            'include_subdomains' => $this->getIncludeSubdomains(),
        ];
        return json_encode($header);
    }

    protected function updateCspHeader($cspHeader)
    {
        if ($this->isReporting()) {
            // Add or update report-uri directive.
            if($cspHeader) {
                if (strpos($cspHeader, 'report-uri')) {
                    $cspHeader = str_replace('report-uri', $this->getReportURIDirective(), $cspHeader);
                } else {
                    $cspHeader = rtrim($cspHeader, ';') . "; {$this->getReportURIDirective()};";
                }
            }
            // Add report-to directive.
            // Note that unlike report-uri, only the first endpoint is used if multiple are declared.
            if ($this->config()->get('use_report_to')) {
                if (strpos($cspHeader, 'report-to') === false) {
                    $cspHeader = rtrim($cspHeader, ';') . "; {$this->getReportToDirective()};";
                }
            }
        }

        return $cspHeader;
    }

    /**
     * Is the CSPReportingOnly field safe to read.
     *
     * If the module is installed and the codebase is flushed before the database has been built,
     * accessing SiteConfig causes an error.
     *
     * @return boolean
     */
    private static function isCSPReportingAvailable()
    {
        // Cached true value.
        if (self::$is_csp_reporting_safe) {
            return self::$is_csp_reporting_safe;
        }

        // Check if all tables and fields required for the class exist in the database.
        $requiredClasses = ClassInfo::dataClassesFor(SiteConfig::class);
        $schema = DataObject::getSchema();
        foreach (array_unique($requiredClasses) as $required) {
            // Skip test classes, as not all test classes are scaffolded at once
            if (is_a($required, TestOnly::class, true)) {
                continue;
            }

            // if any of the tables aren't created in the database
            $table = $schema->tableName($required);
            if (!ClassInfo::hasTable($table)) {
                return false;
            }

            // if any of the tables don't have any fields mapped as table columns
            $dbFields = DB::field_list($table);
            if (!$dbFields) {
                return false;
            }

            // if any of the tables are missing fields mapped as table columns
            $objFields = $schema->databaseFields($required, false);
            $missingFields = array_diff_key($objFields, $dbFields);
            if ($missingFields) {
                return false;
            }
        }

        self::$is_csp_reporting_safe = true;

        return true;
    }
}
