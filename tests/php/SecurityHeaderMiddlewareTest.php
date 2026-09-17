<?php

namespace Signify\SecurityHeaders\Tests;

use Signify\SecurityHeaders\Extensions\SecurityHeaderSiteconfigExtension;
use SilverStripe\Dev\FunctionalTest;
use Signify\SecurityHeaders\Middleware\SecurityHeaderMiddleware;
use SilverStripe\Config\MergeStrategy\Priority;
use SilverStripe\Control\Director;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
use Signify\SecurityHeaders\Models\CSPDirective;
use Signify\SecurityHeaders\Models\CSPPolicy;

class SecurityHeaderMiddlewareTest extends FunctionalTest
{
    protected static $fixture_file = 'fixtures.yml';

    private static $originalHeaderValues = null;

    private static $testHeaders = [
        'global' => [
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; frame-ancestors 'self';",
            'Strict-Transport-Security' => 'test-value2',
            'X-Frame-Options' => 'test-value3',
            'X-XSS-Protection' => 'test-value4',
            'X-Content-Type-Options' => 'test-value5'
        ]
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Set test header values.
        static::$originalHeaderValues = SecurityHeaderMiddleware::config()->get('headers');
        SecurityHeaderMiddleware::config()->merge('headers', self::$testHeaders);
        // Add extension. Note this is needed to ensure the test database is constructed correctly when running both
        // test classes together. It's not strictly needed for this test class alone.
        SiteConfig::add_extension(SecurityHeaderSiteconfigExtension::class);
    }

    public static function tearDownAfterClass(): void
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
        $headersSent = TestUtils::arrayChangeKeyCaseDeep(
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
        $defaultUri = Director::absoluteURL(SecurityHeaderMiddleware::config()->get('report_uri'));
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
                $defaultUri = Director::absoluteURL(SecurityHeaderMiddleware::config()->get('report_uri'));
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

    public function testCustomCSPOverridesConfigHeader()
    {
        $this->createCustomCSPFixtures();

        $testHeaders = self::$testHeaders['global']['Content-Security-Policy'];
        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');

        $this->assertNotEquals(
            $testHeaders,
            $csp,
            'Test configured header value is overridden when custom CSP exists.'
        );
        $this->assertTrue(
            strpos($csp, 'script-src') !== false,
            'Test custom CSP includes the script-src directive.'
        );
    }

    public function testDirectiveWithoutPolicyUsesBaseFallback()
    {
        $this->createCustomCSPFixtures();

        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');

        $hasBaseImgSrc = (bool) preg_match("/img-src[^;]*'self'[^;]*data:/", $csp);

        $this->assertTrue(
            $hasBaseImgSrc,
            'Test a directive with no linked policy falls back to the base config value.'
        );
    }

    public function testDirectiveWithNoRecordUsesBaseFallback()
    {
        $this->createCustomCSPFixtures();

        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');

        $hasFrameAncestors = (bool) preg_match("/frame-ancestors[^;]*'self'/", $csp);

        $this->assertTrue(
            $hasFrameAncestors,
            'Test a directive with no CSPDirective record at all falls back to the base config.'
        );
    }

    public function testCheckboxOnlyDirectiveIsIncludedWithoutPolicy()
    {
        $directive = CSPDirective::create(['Name' => 'connect-src', 'AllowSelf' => true]);
        $directive->write();

        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');

        $hasConnectSrcSelf = (bool) preg_match("/connect-src\s+'self';/", $csp);

        $this->assertTrue(
            $hasConnectSrcSelf,
            'Test a directive with only a checkbox checked, and no policy, is still included.'
        );
    }

    public function testAllowNoneIgnoresPolicyRequirement()
    {
        $this->createCustomCSPFixtures();

        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');
        $hasObjectSrcNone = (bool) preg_match("/object-src\s+'none'/", $csp);

        $this->assertTrue(
            $hasObjectSrcNone,
            "Test AllowNone directive is included even without a linked policy."
        );
    }

    public function testAllowSelfCombinesWithPolicyValue()
    {
        $this->createCustomCSPFixtures();

        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');
        $hasSelfAndYoutubeInScriptSrc = (bool) preg_match(
            "/script-src[^;]*'self'[^;]*https:\/\/www\.youtube\.com/",
            $csp
        );

        $this->assertTrue(
            $hasSelfAndYoutubeInScriptSrc,
            "Test AllowSelf and linked policy value are combined in one directive."
        );
    }

    public function testAlwaysAppendsBlockAllMixedContent()
    {
        $this->createCustomCSPFixtures();

        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');

        $this->assertTrue(
            strpos($csp, 'block-all-mixed-content') !== false,
            'Test block-all-mixed-content is always appended.'
        );
    }

    public function testReportOnlyUsesReportOnlyHeader()
    {
        TestUtils::testWithConfig(
            [
                SecurityHeaderMiddleware::class => [
                    'headers' => [
                        'global' => [
                            'Content-Security-Policy' => "default-src 'self';",
                        ],
                    ],
                ],
            ],
            function () {
                $this->assertReportOnlyHeaderIsUsed();
            }
        );
    }

    private function assertReportOnlyHeaderIsUsed()
    {
        $siteConfig = SiteConfig::current_site_config();
        $reportOnlyConfig = (string) SecurityHeaderSiteconfigExtension::CSP_REPORTING_ONLY;
        $siteConfig->CSPReportingOnly = $reportOnlyConfig;
        $siteConfig->write();

        $response = $this->getResponse();

        $this->assertNull(
            $response->getHeader('Content-Security-Policy'),
            'Test enforced CSP header is removed in report-only mode.'
        );
        $this->assertNotNull(
            $response->getHeader('Content-Security-Policy-Report-Only'),
            'Test Report-Only header is set instead.'
        );
    }

    public function testDefaultUsesEnforcedHeader()
    {
        $response = $this->getResponse();

        $this->assertNotNull(
            $response->getHeader('Content-Security-Policy'),
            'Test enforced CSP header is set when not in report-only mode.'
        );
        $this->assertNull(
            $response->getHeader('Content-Security-Policy-Report-Only'),
            'Test Report-Only header is not set when not in report-only mode.'
        );
    }

    public function testAllowUnsafeInlineIncludedInScriptSrc()
    {
        $this->createCustomCSPFixtures();

        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');

        $hasUnsafeInline = (bool) preg_match("/script-src[^;]*'unsafe-inline'/", $csp);

        $this->assertTrue(
            $hasUnsafeInline,
            'Test AllowUnsafeInline adds unsafe-inline to script-src.'
        );
    }

    public function testAllowUnsafeEvalIncludedInScriptSrc()
    {
        $this->createCustomCSPFixtures();

        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');

        $hasUnsafeEval = (bool) preg_match("/script-src[^;]*'unsafe-eval'/", $csp);

        $this->assertTrue(
            $hasUnsafeEval,
            'Test AllowUnsafeEval adds unsafe-eval to script-src.'
        );
    }

    public function testAllowDataUriIncludedInFontSrc()
    {
        $this->createCustomCSPFixtures();

        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');

        $hasDataUri = (bool) preg_match("/font-src[^;]*data:/", $csp);

        $this->assertTrue(
            $hasDataUri,
            'Test AllowDataUri adds data: to font-src.'
        );
    }

    public function testUnsafeEvalNotIncludedWhenUnchecked()
    {
        $this->createCustomCSPFixtures();

        // font-src has a policy and AllowDataUri, but not AllowUnsafeEval/AllowUnsafeInline.
        $response = $this->getResponse();
        $csp = $response->getHeader('Content-Security-Policy');

        $fontSrcSegment = [];
        preg_match('/font-src[^;]*;/', $csp, $fontSrcSegment);

        $this->assertFalse(
            strpos($fontSrcSegment[0] ?? '', "'unsafe-eval'") !== false,
            'Test font-src does not include unsafe-eval, which was never checked.'
        );
    }

    private function createCustomCSPFixtures()
    {
        $scriptSrc = CSPDirective::create([
            'Name' => 'script-src',
            'AllowSelf' => true,
            'AllowUnsafeInline' => true,
            'AllowUnsafeEval' => true,
        ]);
        $scriptSrc->write();

        $imgSrc = CSPDirective::create(['Name' => 'img-src', 'AllowSelf' => true]);
        $imgSrc->write();

        $fontSrc = CSPDirective::create([
            'Name' => 'font-src',
            'AllowSelf' => true,
            'AllowDataUri' => true,
        ]);
        $fontSrc->write();

        $objectSrc = CSPDirective::create(['Name' => 'object-src', 'AllowNone' => true]);
        $objectSrc->write();

        $youtubePolicy = CSPPolicy::create(['Value' => 'https://www.youtube.com']);
        $youtubePolicy->write();
        $youtubePolicy->Directive()->add($scriptSrc);

        $fontPolicy = CSPPolicy::create(['Value' => 'https://fonts.gstatic.com']);
        $fontPolicy->write();
        $fontPolicy->Directive()->add($fontSrc);
    }
}
