<?php

class CSPViolationsReport extends SS_Report
{
    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'Content security violations');
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

    public function columns()
    {
        return singleton(CSPViolation::class)->summaryFields();
    }

    public function getReportField()
    {
        /* @var $gridConfig GridFieldConfig */
        $gridField = parent::getReportField();
        $gridConfig = $gridField->getConfig();

        $dispositions = array_unique(CSPViolation::get()->column('Disposition'));
        $directives = array_unique(CSPViolation::get()->column('EffectiveDirective'));

        $gridConfig->addComponents(
            new GridFieldDeleteAction(),
            $relationsButton = new GridFieldDeleteRelationsButton('before'),
        );
        $relationsButton->setFilterFields([
            $dateTime = DatetimeField::create('ReportedTime'),
            DropdownField::create('Disposition', 'Disposition', $this->getDropdownArray($dispositions, $dispositions)),
            TextField::create('BlockedURI'),
            ListboxField::create(
                'EffectiveDirective',
                'EffectiveDirective',
                $this->getDropdownArray($directives, $directives)
            )->setMultiple(true),
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
        ]);
        $dateTime->getDateField()->setAttribute('type', 'date');
        $dateTime->getTimeField()->setAttribute('type', 'time');

        return $gridField;
    }

    protected function getDropdownArray($options)
    {
        $array = [];
        foreach ($options as $option) {
            if (!$option) {
                $array[$option] = '(empty)';
            } else {
                $array[$option] = $option;
            }
        }
        return $array;
    }
}
