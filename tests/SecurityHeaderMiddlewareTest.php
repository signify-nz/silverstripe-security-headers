<?php
namespace Signify\Tests;

use Signify\Extensions\SecurityHeaderSiteconfigExtension;
use SilverStripe\Dev\FunctionalTest;
use Signify\Middleware\SecurityHeaderMiddleware;
use SilverStripe\Config\MergeStrategy\Priority;
use SilverStripe\Control\Director;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;

class SecurityHeaderMiddlewareExtensionTest extends FunctionalTest
{
    protected static $fixture_file = 'fixtures.yml';

    private static $originalHeaderValues = null;

    private static $testHeaders = [
        'global' => [
            'Content-Security-Policy' => 'test-value1',
            'Strict-Transport-Security' => 'test-value2',
            'X-Frame-Options' => 'test-value3',
            'X-XSS-Protection' => 'test-value4',
            'X-Content-Type-Options' => 'test-value5'
        ]
    ];

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        // Set test header values.
        static::$originalHeaderValues = SecurityHeaderMiddleware::config()->get('headers');
        SecurityHeaderMiddleware::config()->merge('headers', self::$testHeaders);
        // Add extension. Note this is needed to ensure the test database is constructed correctly when running both
        // test classes together. It's not strictly needed for this test class alone.
        SiteConfig::add_extension(SecurityHeaderSiteconfigExtension::class);
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        // Reset headers to defaults.
        SecurityHeaderMiddleware::config()->merge('headers', static::$originalHeaderValues);
        // Remove extension.
        SiteConfig::remove_extension(SecurityHeaderSiteconfigExtension::class);
    }

    public function testResponseHeaders()
    {
        $response = $this->getResponse();

        // Test all headers, not just the default ones or just the ones in self::$testHeaders.
        $headersSent = TestUtils::array_change_key_case_deep(
            Priority::mergeArray(self::$testHeaders, SecurityHeaderMiddleware::config()->get('headers')),
            CASE_LOWER
        );
        $headersReceived = array_change_key_case($response->getHeaders(), CASE_LOWER);

        foreach ($headersReceived as $header => $value) {
            if (in_array($header, $headersSent['global'])) {
                $this->assertEquals(
                    $value,
                    $headersSent['global'][$header],
                    "Test response value for header '$header' is equal to configured value."
                );
            }
        }

        $missedHeaders = array_diff_key($headersSent['global'], $headersReceived);
        $this->assertEmpty($missedHeaders, 'Test all headers are sent in the response.');
    }

    public function testReportURIAdded()
    {
        $defaultUri = SecurityHeaderMiddleware::config()->get('report_uri');
        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');

        $this->assertTrue($this->directiveExists($csp, 'report-uri'), 'Test CSP includes a report-uri directive.');
        $this->assertTrue(
            $this->endpointExists($csp, 'report-uri', $defaultUri, true),
            'Test report-uri is the default endpoint.'
        );
    }

    public function testReportURIAppended()
    {
        $testURI = 'https://example.test/endpoint.aspx';
        TestUtils::testWithConfig(
            [
                SecurityHeaderMiddleware::class => [
                    'headers' => [
                        'global' => [
                            'Content-Security-Policy' => "default-src 'self'; report-uri $testURI;",
                        ],
                    ],
                ],
            ],
            function () use ($testURI) {
                $defaultUri = SecurityHeaderMiddleware::config()->get('report_uri');
                $response = $this->getResponse();
                $csp = $response->getHeader('Content-Security-Policy');

                $this->assertTrue(
                    $this->directiveExists($csp, 'report-uri'),
                    'Test CSP includes a report-uri directive.'
                );
                $this->assertTrue(
                    $this->endpointExists($csp, 'report-uri', $testURI),
                    'Test report-uri includes the configured endpoint.'
                );
                $this->assertTrue(
                    $this->endpointExists($csp, 'report-uri', $defaultUri),
                    'Test report-uri includes the default endpoint.'
                );
            }
        );
    }

    public function testReportDisabled()
    {
        TestUtils::testWithConfig(
            [
                SecurityHeaderMiddleware::class => [
                    'enable_reporting' => false,
                    'use_report_to' => true,
                ],
            ],
            function () {
                $response = $this->getResponse();
                $csp = $response->getHeader('Content-Security-Policy');
                $reportHeaderExists = $response->getHeader('Report-To') !== null;

                $this->assertFalse(
                    $this->directiveExists($csp, 'report-uri'),
                    'Test CSP does not include a report-uri directive.'
                );
                $this->assertFalse(
                    $this->directiveExists($csp, 'report-to'),
                    'Test CSP does not include a report-to directive.'
                );
                $this->assertFalse(
                    $reportHeaderExists,
                    'Test CSP does not include a Report-To header.'
                );
            }
        );
    }

    public function testReportToNotAdded()
    {
        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');
        $reportHeaderExists = $response->getHeader('Report-To') !== null;

        $this->assertFalse(
            $this->directiveExists($csp, 'report-to'),
            'Test CSP does not include a report-to directive.'
        );
        $this->assertFalse(
            $reportHeaderExists,
            'Test CSP does not include a Report-To header.'
        );
    }

    public function testReportToAdded()
    {
        TestUtils::testWithConfig(
            [
                SecurityHeaderMiddleware::class => [
                    'use_report_to' => true,
                ],
            ],
            function () {
                $defaultEndpoint = SecurityHeaderMiddleware::config()->get('report_to_group');
                $defaultUri = Director::absoluteURL(SecurityHeaderMiddleware::config()->get('report_uri'));
                $response = $this->getResponse();
                $csp = $response->getHeader('Content-Security-Policy');
                $reportHeader = json_decode($response->getHeader('Report-To'), true);

                $this->assertTrue(
                    $this->directiveExists($csp, 'report-to'),
                    'Test CSP includes a report-to directive.'
                );
                $this->assertTrue(
                    $this->endpointExists($csp, 'report-to', $defaultEndpoint, true),
                    'Test report-to directive is the default endpoint group.'
                );
                $this->assertTrue(
                    $reportHeader !== null,
                    'Test CSP includes a Report-To header.'
                );
                if ($reportHeader !== null) {
                    $this->assertEquals(
                        $defaultEndpoint,
                        $reportHeader['group'],
                        'Test Report-To header has correct group name.'
                    );
                    $this->assertEquals(
                        $defaultUri,
                        $reportHeader['endpoints'][0]['url'],
                        'Test Report-To header has correct endpoint URI'
                    );
                }
            }
        );
    }

    protected function getResponse()
    {
        $page = $this->objFromFixture('Page', 'page');
        $page->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
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
