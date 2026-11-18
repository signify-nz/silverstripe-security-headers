<?php

namespace Signify\SecurityHeaders\Tests;

use SilverStripe\Dev\FunctionalTest;
use Signify\SecurityHeaders\Models\CSPDirective;

class CSPDirectiveTest extends FunctionalTest
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (CSPDirective::get() as $directive) {
            $directive->delete();
        }
    }

    public function testEmptyNameFailsValidation()
    {
        $directive = CSPDirective::create(['Name' => '']);
        $result = $directive->validate();

        $this->assertFalse($result->isValid(), 'Test empty Name fails validation.');
    }

    public function testDuplicateNameFailsValidation()
    {
        $existing = CSPDirective::create(['Name' => 'script-src']);
        $existing->write();

        $duplicate = CSPDirective::create(['Name' => 'script-src']);

        $result = $duplicate->validate();

        $this->assertFalse($result->isValid(), 'Test a duplicate Name fails validation.');
    }

    public function testUnchangedNameIsNotADuplicate()
    {
        $directive = CSPDirective::create(['Name' => 'script-src', 'AllowSelf' => true]);
        $directive->write();
        $directive->AllowSelf = false;
        $result = $directive->validate();

        $this->assertTrue(
            $result->isValid(),
            'Test re-saving directive without changing its Name does not trigger false duplicate error.'
        );
    }

    public function testUniqueNamePassesValidation()
    {
        $existing = CSPDirective::create(['Name' => 'script-src']);
        $existing->write();
        $newDirective = CSPDirective::create(['Name' => 'style-src']);
        $result = $newDirective->validate();

        $this->assertTrue($result->isValid(), 'Test unique Name passes validation.');
    }
}
