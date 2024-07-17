<?php

namespace Signify\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\CMS\Model\SiteTree;

class CSPDocument extends DataObject
{
    private static $table_name = 'Signify_Document';

    private static $db = [
        'URI' => 'Varchar(255)',
    ];

    private static $has_one = [
        'SiteTree' => SiteTree::class,
    ];

    private static $belongs_many_many = [
        'CSPViolations' => CSPViolation::class,
    ];
}
