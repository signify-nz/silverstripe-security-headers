<?php
namespace Signify\SecurityHeaders\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Control\Controller;
use Signify\SecurityHeaders\Extensions\SecurityHeaderControllerExtension;
use SilverStripe\Control\Director;

class SecurityHeaderControllerExtensionTest extends FunctionalTest
{
    const HEADER_TEST_ROUTE = 'security-header-test';

    private $originalHeaderValues = null;

    private static $testHeaders = [
        'Content-Security-Policy' => 'test-value1',
        'Strict-Transport-Security' => 'test-value2',
        'X-Frame-Options' => 'test-value3',
        'X-XSS-Protection' => 'test-value4',
        'X-Content-Type-Options' => 'test-value5'
    ];

    public function setUpOnce()
    {
        // Add extension and a new test route.
        Controller::add_extension(SecurityHeaderControllerExtension::class);
        Director::config()->update('rules', array(
            self::HEADER_TEST_ROUTE => 'Controller'
        ));

        // Set test header values.
        $this->originalHeaderValues = SecurityHeaderControllerExtension::config()->get('headers');
        SecurityHeaderControllerExtension::config()->update('headers', self::$testHeaders);
    }

    public function tearDownOnce()
    {
        // Remove extension and test route. Reset headers to defaults.
        Controller::remove_extension(SecurityHeaderControllerExtension::class);
        Director::config()->remove('rules', self::HEADER_TEST_ROUTE);
        SecurityHeaderControllerExtension::config()->update('headers', $this->originalHeaderValues);
    }

    public function testResponseHeaders()
    {
        $response = $this->get(self::HEADER_TEST_ROUTE);

        // Test all headers, not just the default ones or just the ones in self::$testHeaders.
        $headersSent = array_merge(SecurityHeaderControllerExtension::config()->get('headers'), self::$testHeaders);
        $headersReceived = $response->getHeaders();

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
        $defaultUri = SecurityHeaderControllerExtension::config()->get('report_uri');
        $response = $this->get(self::HEADER_TEST_ROUTE);
        $csp = $response->getHeader('Content-Security-Policy');

        $this->assertTrue($this->directiveExists($csp, 'report-uri'), 'Test CSP includes a report-uri directive.');
        $this->assertTrue($this->endpointExists($csp, 'report-uri', $defaultUri, true), 'Test report-uri is the default endpoint.');
    }

    public function testReportURIAppended()
    {
        $testURI = 'https://example.test/endpoint.aspx';
        TestUtils::testWithConfig(
            [
                SecurityHeaderControllerExtension::class => [
                    'headers' => [
                        'Content-Security-Policy' => "default-src 'self'; report-uri $testURI;",
                    ],
                ],
            ],
            function () use ($testURI) {
                $defaultUri = SecurityHeaderControllerExtension::config()->get('report_uri');
                $response = $this->get(self::HEADER_TEST_ROUTE);
                $csp = $response->getHeader('Content-Security-Policy');

                $this->assertTrue($this->directiveExists($csp, 'report-uri'), 'Test CSP includes a report-uri directive.');
                $this->assertTrue($this->endpointExists($csp, 'report-uri', $testURI), 'Test report-uri includes the configured endpoint.');
                $this->assertTrue($this->endpointExists($csp, 'report-uri', $defaultUri), 'Test report-uri includes the default endpoint.');
            }
        );
    }

    public function testReportDisabled()
    {
        TestUtils::testWithConfig(
            [
                SecurityHeaderControllerExtension::class => [
                    'enable_reporting' => false,
                    'use_report_to' => true,
                ],
            ],
            function () {
                $response = $this->get(self::HEADER_TEST_ROUTE);
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
        $response = $this->get(self::HEADER_TEST_ROUTE);
        $csp = $response->getHeader('Content-Security-Policy');
        $reportHeaderExists = $response->getHeader('Report-To') !== null;

        $this->assertFalse($this->directiveExists($csp, 'report-to'), 'Test CSP does not include a report-to directive.');
        $this->assertFalse($reportHeaderExists, 'Test CSP does not include a Report-To header.');
    }

    public function testReportToAdded()
    {
        TestUtils::testWithConfig(
            [
                SecurityHeaderControllerExtension::class => [
                    'use_report_to' => true,
                ],
            ],
            function () {
                $defaultEndpoint = SecurityHeaderControllerExtension::config()->get('report_to_group');
                $defaultUri = SecurityHeaderControllerExtension::config()->get('report_uri');
                $response = $this->get(self::HEADER_TEST_ROUTE);
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

    protected function directiveExists($csp, $directive)
    {
        return strpos($csp, $directive) !== false;
    }

    protected function endpointExists($csp, $directive, $endpoint, $exactMatch = false)
    {
        $matches = array();
        preg_match('/report-uri\s+(?<endpoints>[^;]+?);/', $csp, $matches);
        if ($exactMatch) {
            return $matches['endpoints'] === $endpoint;
        } else {
            return strpos($matches['endpoints'], $endpoint) !== false;
        }
    }
}
