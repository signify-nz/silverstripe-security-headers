<?php

class SecurityHeaderSiteconfigExtension extends DataExtension implements PermissionProvider
{

    private static $db = [
        'CSPReportingOnly' => 'Boolean',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        if (!Permission::check('ADMINISTER_CSP')) {
            return;
        }

        $fields->addFieldToTab('Root.Main', CheckboxField::create(
            'CSPReportingOnly',
            'Set Content Security Policy to report-only mode'
        ));
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
