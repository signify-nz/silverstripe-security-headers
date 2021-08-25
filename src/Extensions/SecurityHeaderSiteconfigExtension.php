<?php

namespace Signify\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Security\PermissionProvider;

class SecurityHeaderSiteconfigExtension extends DataExtension implements PermissionProvider
{
    // The values are in this order to ensure backwards compatability with the old binary options.
    public const CSP_WITH_REPORTING = 0;

    public const CSP_WITHOUT_REPORTING = 2;

    public const CSP_REPORTING_ONLY = 1;

    public const CSP_DISABLE = 3;

    private static $db = [
        "CSPReportingOnly" => "Enum('0,2,1,3')",
    ];

    public function updateCMSFields(FieldList $fields)
    {
        if (!Permission::check('ADMINISTER_CSP')) {
            return;
        }

        $fields->addFieldToTab(
            'Root.Main',
            OptionsetField::create(
                'CSPReportingOnly',
                'Content Security Policy',
                [
                    self::CSP_WITH_REPORTING => 'Enable Content Security Policy with reporting (recommended)',
                    self::CSP_WITHOUT_REPORTING => 'Enable Content Security Policy without reporting',
                    self::CSP_REPORTING_ONLY => 'Set Content Security Policy to report-only mode',
                    self::CSP_DISABLE => 'Disable Content Security Policy (not recommended)',
                ]
            )
        );
    }

    public function providePermissions()
    {
        $category = 'Content Security Policy';
        $permissions = [
            'ADMINISTER_CSP' => [
                'name' => 'Administer CSP',
                'category' => $category,
                'help' => 'Can administer settings related to the Content Security Policy'
            ],
        ];
        return $permissions;
    }
}
