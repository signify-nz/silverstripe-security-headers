<?php

namespace Signify\SecurityHeaders\Tests;

use SilverStripe\Dev\FunctionalTest;
use Signify\SecurityHeaders\Models\CSPPolicy;

class CSPPolicyTest extends FunctionalTest
{
    public function testEmptyValueFailsValidation()
    {
        $policy = CSPPolicy::create(['Value' => '']);
        $result = $policy->validate();

        $this->assertFalse($result->isValid(), 'Test empty Value fails validation.');
    }

    public function testWhitespaceInValueFailsValidation()
    {
        $policy = CSPPolicy::create(['Value' => 'https://example.com /path']);
        $result = $policy->validate();

        $this->assertFalse($result->isValid(), 'Test Value containing whitespace fails validation.');
    }

    public function testSemicolonInValueFailsValidation()
    {
        $policy = CSPPolicy::create(['Value' => 'https://example.com;']);
        $result = $policy->validate();

        $this->assertFalse($result->isValid(), 'Test Value containing a semicolon fails validation.');
    }

    public function testCommaInValueFailsValidation()
    {
        $policy = CSPPolicy::create(['Value' => 'https://example.com,https://other.com']);
        $result = $policy->validate();

        $this->assertFalse($result->isValid(), 'Test Value containing a comma fails validation.');
    }

    public function testQuoteInValueFailsValidation()
    {
        $policy = CSPPolicy::create(['Value' => "'self'"]);
        $result = $policy->validate();

        $this->assertFalse($result->isValid(), 'Test Value containing a quote fails validation.');
    }

    public function testValidUrlPassesValidation()
    {
        $policy = CSPPolicy::create(['Value' => 'https://www.youtube.com']);
        $result = $policy->validate();

        $this->assertTrue($result->isValid(), 'Test valid URL passes validation.');
    }

    public function testGetTitleReturnsValueWhenSet()
    {
        $policy = CSPPolicy::create(['Value' => 'https://www.youtube.com']);

        $this->assertEquals(
            'https://www.youtube.com',
            $policy->getTitle(),
            'Test getTitle() returns the Value when set.'
        );
    }

    public function testGetTitleFallsBackToIdWhenValueEmpty()
    {
        $policy = CSPPolicy::create(['Value' => '']);
        $policy->ID = 42;

        $this->assertEquals(
            'Policy #42',
            $policy->getTitle(),
            'Test getTitle() falls back to the record ID when Value is empty.'
        );
    }
}
