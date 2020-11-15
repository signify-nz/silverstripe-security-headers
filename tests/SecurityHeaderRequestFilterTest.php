<?php

class SecurityHeaderRequestFilterTest extends FunctionalTest
{
    protected static $fixture_file = 'fixtures.yml';

    private static $originalHeaderValues = null;

    private static $testHeaders = [
        'Content-Security-Policy' => 'test-value1',
        'Strict-Transport-Security' => 'test-value2',
        'X-Frame-Options' => 'test-value3',
        'X-XSS-Protection' => 'test-value4',
        'X-Content-Type-Options' => 'test-value5'
    ];

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        // Add extension.
        Controller::add_extension(SecurityHeaderRequestFilter::class);

        // Set test header values.
        $config = Config::inst()->forClass(SecurityHeaderRequestFilter::class);
        static::$originalHeaderValues = $config->get('headers');
        Config::inst()->update(SecurityHeaderRequestFilter::class, 'headers', self::$testHeaders);
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        // Remove extension and reset headers to defaults.
        Controller::remove_extension(SecurityHeaderRequestFilter::class);
        Config::inst()->update(SecurityHeaderRequestFilter::class, 'headers', static::$originalHeaderValues);
    }

    public function testResponseHeaders()
    {
        $response = $this->getResponse();

        // Test all headers, not just the default ones or just the ones in self::$testHeaders.
        $config = Config::inst()->forClass(SecurityHeaderRequestFilter::class);
        $headersSent = array_change_key_case(
            array_merge($config->get('headers'), self::$testHeaders),
            CASE_LOWER
        );
        $headersReceived = array_change_key_case($response->getHeaders(), CASE_LOWER);

        foreach ($headersReceived as $header => $value) {
            if (in_array($header, $headersSent)) {
                $this->assertEquals(
                    $value,
                    $headersSent[$header],
                    "Test response value for header '$header' is equal to configured value."
                );
            }
        }

        $missedHeaders = array_diff_key($headersSent, $headersReceived);
        $this->assertEmpty($missedHeaders, 'Test all headers are sent in the response.');
    }

    public function testReportURIAdded()
    {
        $config = Config::inst()->forClass(SecurityHeaderRequestFilter::class);
        $defaultUri = $config->get('report_uri');
        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');

        $this->assertTrue($this->directiveExists($csp, 'report-uri'), 'Test CSP includes a report-uri directive.');
        $this->assertTrue($this->endpointExists($csp, 'report-uri', $defaultUri, true), 'Test report-uri is the default endpoint.');
    }

    public function testReportURIAppended()
    {
        $testURI = 'https://example.test/endpoint.aspx';
        $config = Config::inst()->forClass(SecurityHeaderRequestFilter::class);
        SigSecurityTestUtils::testWithConfig(
            [
                SecurityHeaderRequestFilter::class => [
                    'headers' => [
                        'Content-Security-Policy' => "default-src 'self'; report-uri $testURI;",
                    ],
                ],
            ],
            function () use ($testURI, $config) {
                $defaultUri = $config->get('report_uri');
                $response = $this->getResponse();
                $csp = $response->getHeader('Content-Security-Policy');

                $this->assertTrue($this->directiveExists($csp, 'report-uri'), 'Test CSP includes a report-uri directive.');
                $this->assertTrue($this->endpointExists($csp, 'report-uri', $testURI), 'Test report-uri includes the configured endpoint.');
                $this->assertTrue($this->endpointExists($csp, 'report-uri', $defaultUri), 'Test report-uri includes the default endpoint.');
            }
        );
    }

    public function testReportDisabled()
    {
        SigSecurityTestUtils::testWithConfig(
            [
                SecurityHeaderRequestFilter::class => [
                    'enable_reporting' => false,
                    'use_report_to' => true,
                ],
            ],
            function () {
                $response = $this->getResponse();
                $csp = $response->getHeader('Content-Security-Policy');
                $reportHeaderExists = $response->getHeader('Report-To') !== null;

                $this->assertFalse($this->directiveExists($csp, 'report-uri'), 'Test CSP does not include a report-uri directive.');
                $this->assertFalse($this->directiveExists($csp, 'report-to'), 'Test CSP does not include a report-to directive.');
                $this->assertFalse($reportHeaderExists, 'Test CSP does not include a Report-To header.');
            }
        );
    }

    public function testReportToNotAdded()
    {
        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');
        $reportHeaderExists = $response->getHeader('Report-To') !== null;

        $this->assertFalse($this->directiveExists($csp, 'report-to'), 'Test CSP does not include a report-to directive.');
        $this->assertFalse($reportHeaderExists, 'Test CSP does not include a Report-To header.');
    }

    public function testReportToAdded()
    {
        $config = Config::inst()->forClass(SecurityHeaderRequestFilter::class);
        SigSecurityTestUtils::testWithConfig(
            [
                SecurityHeaderRequestFilter::class => [
                    'use_report_to' => true,
                ],
            ],
            function () use ($config) {
                $defaultEndpoint = $config->get('report_to_group');
                $defaultUri = Director::absoluteURL($config->get('report_uri'));
                $response = $this->getResponse();
                $csp = $response->getHeader('Content-Security-Policy');
                $reportHeader = json_decode($response->getHeader('Report-To'), true);

                $this->assertTrue($this->directiveExists($csp, 'report-to'), 'Test CSP includes a report-to directive.');
                $this->assertTrue($this->endpointExists($csp, 'report-to', $defaultEndpoint, true), 'Test report-to directive is the default endpoint group.');
                $this->assertTrue($reportHeader !== null, 'Test CSP includes a Report-To header.');
                if ($reportHeader !== null) {
                    $this->assertEquals($defaultEndpoint, $reportHeader['group'], 'Test Report-To header has correct group name.');
                    $this->assertEquals($defaultUri, $reportHeader['endpoints'][0]['url'], 'Test Report-To header has correct endpoint URI');
                }
            }
        );
    }

    protected function getResponse()
    {
        $page = $this->objFromFixture('Page', 'page');
        $page->publish(Versioned::DRAFT, Versioned::LIVE);
        return $this->get($page->Link());
    }

    protected function directiveExists($csp, $directive)
    {
        return strpos($csp, $directive) !== false;
    }

    protected function endpointExists($csp, $directive, $endpoint, $exactMatch = false)
    {
        $matches = array();
        preg_match('/' . $directive . '\s+(?<endpoints>[^;]+?);/', $csp, $matches);
        if ($exactMatch) {
            return $matches['endpoints'] === $endpoint;
        } else {
            return strpos($matches['endpoints'], $endpoint) !== false;
        }
    }
}
