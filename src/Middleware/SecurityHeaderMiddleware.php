<?php

namespace Signify\SecurityHeaders\Middleware;

use Signify\SecurityHeaders\Extensions\SecurityHeaderSiteconfigExtension;
use Signify\SecurityHeaders\Models\CSPDirective;
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

    /**
     * Whether the custom CSP (built from CSPDirective/CSPPolicy records) can be used.
     *
     * @config
     * @var bool
     */
    private static $enable_custom_csp = true;

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
        if ($this->config()->get('enable_custom_csp') && $this->hasCustomCSP()) {
            $this->applyCSPHeader($headersToSend, $this->getCustomCSP());
        } elseif (array_key_exists('Content-Security-Policy', $headersToSend)) {
            if ($this->hasCSP()) {
                $this->applyCSPHeader($headersToSend, $headersToSend['Content-Security-Policy']);
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
     * Returns true if custom Content-Security-Policy should be built from
     * CSPDirective/CSPPolicy records.
     *
     * @return bool
     */
    private function hasCustomCSP(): bool
    {
        $allDirectives = CSPDirective::get();

        foreach ($allDirectives as $directive) {
            $hasCustomDirective = $directive->AllowNone ||
                $directive->AllowSelf ||
                $directive->AllowUnsafeInline ||
                $directive->AllowUnsafeEval ||
                $directive->AllowDataUri ||
                $directive->Policies()->exists();

            if ($hasCustomDirective) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds a Content-Security-Policy header from CSPDirective/CSPPolicy records,
     * falling back to the configured CSP value when a directive has no policy.
     *
     * @return string
     */
    private function getCustomCSP(): string
    {
        $directives = CSPDirective::get();
        $baseDirectives = $this->parseBaseCSPDirectives();
        $allDirectives = '';
        $handledDirectives = [];

        foreach ($directives as $directive) {
            $sources = [];

            if ($directive->AllowNone) {
                $sources[] = "'none'";
            } else {
                $policyValues = $directive->Policies()->column('Value');

                if (empty($policyValues) && isset($baseDirectives[$directive->Name])) {
                    $policyValues = preg_split(
                        '/\s+/',
                        trim($baseDirectives[$directive->Name])
                    );
                }

                $hasAnyCheckbox = $directive->AllowSelf
                    || $directive->AllowUnsafeInline
                    || $directive->AllowUnsafeEval
                    || $directive->AllowDataUri;

                if (empty($policyValues) && !$hasAnyCheckbox) {
                    continue;
                }

                if ($directive->AllowSelf) {
                    $sources[] = "'self'";
                }

                if ($directive->AllowUnsafeInline) {
                    $sources[] = "'unsafe-inline'";
                }

                if ($directive->AllowUnsafeEval) {
                    $sources[] = "'unsafe-eval'";
                }

                if ($directive->AllowDataUri) {
                    $sources[] = 'data:';
                }

                $sources = array_merge($sources, $policyValues);
            }

            $handledDirectives[] = $directive->Name;
            $sourcesString = implode(' ', array_unique($sources));
            $directiveString = trim($directive->Name . ' ' . $sourcesString) . '; ';
            $allDirectives .= $directiveString;
        }

        foreach ($baseDirectives as $name => $sourcesString) {
            if (in_array($name, $handledDirectives) || $name === 'block-all-mixed-content') {
                continue;
            }

            $allDirectives .= $name . ' ' . $sourcesString . '; ';
        }

        $allDirectives .= 'block-all-mixed-content;';

        return $allDirectives;
    }

    /**
     * Parses the base Content-Security-Policy header into a source map, used to
     * fill any gaps left by CSPDirective records.
     *
     * @return array
     */
    private function parseBaseCSPDirectives(): array
    {
        $headersConfig = (array) $this->config()->get('headers');
        $baseCsp = $headersConfig['global']['Content-Security-Policy'] ?? '';

        if (!$baseCsp) {
            return [];
        }

        $directives = [];

        foreach (explode(';', $baseCsp) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $segments = preg_split('/\s+/', $part, 2);

            $name = $segments[0];
            $sources = $segments[1] ?? '';

            $directives[$name] = $sources;
        }

        return $directives;
    }

    /**
     * Sets the Content-Security-Policy or Content-Security-Policy-Report-Only
     * header depending on the current reporting mode.
     *
     * @param array $headersToSend
     * @param string $headerValue
     * @return void
     */
    private function applyCSPHeader(array &$headersToSend, string $headerValue): void
    {
        $header = 'Content-Security-Policy';

        if ($this->isCSPReportingOnly()) {
            unset($headersToSend['Content-Security-Policy']);
            $header = 'Content-Security-Policy-Report-Only';
        }

        $headersToSend[$header] = $this->updateCspHeader($headerValue);
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
        $isCSPReportingAvailable = self::isCSPReportingAvailable();
        $configReportingOnly = SiteConfig::current_site_config()->CSPReportingOnly;
        $isCSPReporting = $configReportingOnly == SecurityHeaderSiteconfigExtension::CSP_REPORTING_ONLY;

        if ($isCSPReportingAvailable && $isCSPReporting) {
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
            if ($cspHeader) {
                if (strpos($cspHeader, 'report-uri')) {
                    $cspHeader = str_replace('report-uri', $this->getReportURIDirective(), $cspHeader);
                } else {
                    $cspHeader = rtrim($cspHeader, ';') . "; {$this->getReportURIDirective()};";
                }
            } else {
                $cspHeader = $this->getReportURIDirective() . ';';
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
