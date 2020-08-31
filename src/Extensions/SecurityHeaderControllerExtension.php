<?php

namespace Signify\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Config\Configurable;

class SecurityHeaderControllerExtension extends Extension
{
    use Configurable;

    /**
     * An array of HTTP headers.
     * @config
     * @var array
     */
    private static $headers;

    public function onAfterInit()
    {
        $response = $this->owner->getResponse();

        $headersToSend = (array) $this->config()->get('headers');

        foreach ($headersToSend as $header => $value) {
            $response->addHeader($header, $value);
        }
    }

}
