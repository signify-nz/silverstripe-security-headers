<?php

namespace Signify\SecurityHeaders\Models;

use SilverStripe\CMS\Controllers\RootURLController;
use SilverStripe\ORM\DataObject;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;

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

    public static function get_or_create(string $documentURI): CSPDocument
    {
        $document = self::get()->filter(['URI' => $documentURI])->first();
        if (!$document) {
            $document = self::create(['URI' => $documentURI]);
            $siteTreeLink = $documentURI;
            if (!Director::makeRelative($siteTreeLink)) {
                $siteTreeLink = RootURLController::get_homepage_link();
            }
            if ($siteTree = SiteTree::get_by_link($siteTreeLink)) {
                $document->SiteTreeID = $siteTree->ID;
            }
            $document->write();
        }
        return $document;
    }
}
