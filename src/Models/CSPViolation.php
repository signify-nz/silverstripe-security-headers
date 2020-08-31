<?php
namespace Signify\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;

class CSPViolation extends DataObject
{

    private static $table_name = 'Signify_CSPViolation';

    private static $db = [
        'Disposition' => 'Varchar(7)',
        'BlockedURI' => 'Varchar(255)',
        'ViolatedDirective' => 'Varchar(255)',
        'EffectiveDirective' => 'Varchar(255)',
        'Violations' => 'Int',
    ];

    private static $many_many = [
        'Documents' => CSPDocument::class,
    ];

    private static $summary_fields = [
        'LastEdited' => 'Latest Report',
        'Disposition',
        'BlockedURI',
        'DocumentURIs',
        'ViolatedDirective',
        'EffectiveDirective',
        'Violations',
    ];

    private static $default_sort = 'LastEdited DESC';

    public function getDocumentURIs()
    {
        return DBField::create_field('Text', implode(', ', $this->Documents()->Column('URI')));
    }
}

