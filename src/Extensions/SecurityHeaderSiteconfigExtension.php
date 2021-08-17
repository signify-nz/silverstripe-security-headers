<?php

namespace Signify\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Security\PermissionProvider;

class SecurityHeaderSiteconfigExtension extends DataExtension implements PermissionProvider
{

    private static $db = [
        "CSPReportingOnly" => "Enum('0,1,2,3')",
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'CSPReportingOnly' => '0',
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
                    '0' => 'Enable Content Security Policy with reporting (recommended)',
                    '1' => 'Set Content Security Policy to report-only mode',
                    '2' => 'Enable Content Security Policy without reporting',
                    '3' => 'Disable Content Security Policy (not recommended)',
                ],
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
