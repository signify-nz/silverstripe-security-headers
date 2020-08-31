<?php

namespace Signify\Reports;

use SilverStripe\Reports\Report;
use Signify\Models\CSPViolation;

class CSPViolationsReport extends Report
{
    public function title()
    {
        return _t(__CLASS__ . '.TITLE', 'Content security violations');
    }

    public function description()
    {
        return _t(
            __CLASS__ . '.DESCRIPTION',
            'Lists violations caught by the Content Security Policy.'
            . ' For more details see <a href="{url}" target="_blank">the MDN documentation</a>.',
            ['url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP#Violation_report_syntax']
        );
    }

    public function sourceRecords($params = [], $sort = null, $limit = null)
    {
        return CSPViolation::get();
    }

}

