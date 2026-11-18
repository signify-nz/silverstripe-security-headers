<?php

namespace Signify\SecurityHeaders\Admin;

use Signify\SecurityHeaders\Middleware\SecurityHeaderMiddleware;
use Signify\SecurityHeaders\Models\CSPDirective;
use Signify\SecurityHeaders\Models\CSPPolicy;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Security\Permission;

/**
 * Management interface for CSP Directives and CSP Policies.
 */
class CSPAdmin extends ModelAdmin
{
    private static $url_segment = 'csp';

    private static $menu_title = 'CSP';

    private static $menu_icon_class = 'font-icon-shield';

    private static $managed_models = [
        CSPDirective::class,
        CSPPolicy::class,
    ];

    /**
     * {@inheritDoc}
     */
    public function canView($member = null)
    {
        if (!SecurityHeaderMiddleware::config()->get('enable_custom_csp')) {
            return false;
        }

        return Permission::check('ADMINISTER_CSP', 'any', $member) && parent::canView($member);
    }
}
