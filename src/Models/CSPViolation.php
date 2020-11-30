<?php

namespace Signify\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;

class CSPViolation extends DataObject
{
    private static $plural_name = 'CSP Violations';

    private static $table_name = 'Signify_CSPViolation';

    private static $db = [
        'ReportedTime' => 'Datetime',
        'Disposition' => 'Varchar(7)',
        'BlockedURI' => 'Varchar(255)',
        'EffectiveDirective' => 'Varchar(255)',
        'Violations' => 'Int',
    ];

    private static $many_many = [
        'Documents' => CSPDocument::class,
    ];

    private static $summary_fields = [
        'ReportedTime' => 'Latest Report',
        'Disposition',
        'BlockedURI',
        'DocumentURIs',
        'EffectiveDirective',
        'Violations',
    ];

    private static $default_sort = 'ReportedTime DESC';

    public function getDocumentURIs()
    {
        return DBField::create_field('Text', implode(', ', $this->Documents()->Column('URI')));
    }
}
