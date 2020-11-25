<?php

namespace Signify\Reports;

use SilverStripe\Reports\Report;
use Signify\Models\CSPViolation;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use Signify\Forms\GridField\GridFieldDeleteRelationsButton;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\ListboxField;

class CSPViolationsReport extends Report
{
    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'CSP violations');
    }

    public function description()
    {
        $desc = _t(
            __CLASS__ . '.DESCRIPTION',
            'Lists violations caught by the Content Security Policy.'
            . ' For more details see <a href="{url}" target="_blank">the MDN documentation</a>.',
            ['url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP#Violation_report_syntax']
        );
        return str_replace('</a>', ' <span class="font-icon-external-link"></span></a>', $desc);
    }

    public function sourceRecords($params = [], $sort = null, $limit = null)
    {
        return CSPViolation::get();
    }

    public function getReportField()
    {
        Requirements::css('signify-nz/silverstripe-security-headers:client/dist/main.css');
        /* @var $gridConfig \SilverStripe\Forms\GridField\GridFieldConfig */
        $gridField = parent::getReportField();
        $gridConfig = $gridField->getConfig();

        $dispositions = CSPViolation::get()->columnUnique('Disposition');
        $dispositions = array_combine($dispositions, $dispositions);
        $directives = CSPViolation::get()->columnUnique('EffectiveDirective');
        $directives = array_combine($directives, $directives);

        $gridConfig->addComponents([
            new GridFieldDeleteAction(),
            GridFieldDeleteRelationsButton::create('buttons-before-left')
            ->setFilterFields([
                DatetimeField::create('ReportedTime'),
                DropdownField::create('Disposition', 'Disposition', $dispositions),
                TextField::create('BlockedURI'),
                ListboxField::create('EffectiveDirective', 'EffectiveDirective', $directives),
                NumericField::create('Violations', '# Violations'),
                TextField::create('Documents.URI', 'Document URIs'),
            ])
            ->setFilterOptions([
                'ReportedTime' => GridFieldDeleteRelationsButton::NUMBER_DATE_FILTER_OPTIONS,
                'Disposition' => [
                    'ExactMatch',
                ],
                'BlockedURI' => GridFieldDeleteRelationsButton::STRING_FILTER_OPTIONS,
                'EffectiveDirective' => [
                    'ExactMatch',
                ],
                'Violations' => GridFieldDeleteRelationsButton::NUMBER_DATE_FILTER_OPTIONS,
                'Documents.URI' => GridFieldDeleteRelationsButton::STRING_FILTER_OPTIONS,
            ]),
        ]);

        return $gridField;
    }
}
