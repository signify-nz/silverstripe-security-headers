# Setup and Basic Configuration

The middleware is automatically enabled when you install the module.

## Configure the headers

While some default headers are already set for your convenience, you may wish to add new headers, alter the values for the default headers, or even remove the default headers. This is all done with the `headers` configuration mapping on the `SecurityHeaderMiddleware` extension.

### Add or amend a header value

In the below example, the `X-Frame-Options` header value is changed from the default. This same syntax is used to add new headers.

```yml
---
After: 'signify-security-headers'
---
Signify\Middleware\SecurityHeaderMiddleware:
  headers:
    global:
      X-Frame-Options: "allow-from https://example.com/"
```

### Remove a default header value

In the following example, the value for the `Strict-Transport-Security` header is cleared completely. Doing this means that the module will not add the `Strict-Transport-Security` header to any responses.
Note that either `null` or an empty string will have the same effect.

```yml
---
After: 'signify-security-headers'
---
Signify\Middleware\SecurityHeaderMiddleware:
  headers:
    global:
      Strict-Transport-Security: null
```

## Changing the Content Security Policy

To make it clearer what the policy for your specific application is, individual CSP attributes can't be overridden. Rather, you must declare the full value for the `Content-Security-Policy` header if you wish to override it.
We recommend copying the value we use in the packaged [_config/config.yml file](../../_config/config.yml), and building onto it from there.

## Updating Headers Via Code

The `SecurityHeaderMiddleware` class has two convenient extension points before adding headers to the response. You can use these in an `Extension` subclass to alter header values.

The `updateHeaders` extension method provides you with all headers as an array. It is useful for adding or removing headers under specific conditions and has a signature like so:
```PHP
public function updateHeaders(&$headers, HTTPRequest $request);
```
The `updateHeader` extension method provides you with each header name and value, one header at a time. This method is useful for altering the value for a given header. Its signature looks like this:
```PHP
public function updateHeader($header, &$value, HTTPRequest $request);
```

For example, if you use the [silverstripe/iframe](https://github.com/silverstripe/silverstripe-iframe) module you may want to ensure the URL set on a given IFramePage will be permitted by the CSP on that page, but _only_ on that page. That can be achieved like so:

In a php file:
```PHP
namespace App\Extensions;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\IFrame\IFramePage;
use SilverStripe\IFrame\IFramePageController;

class UpdateIframeCSPHeaderExtension extends Extension
{
    public function updateHeader($header, &$value, HTTPRequest $request)
    {
        // Ignore headers that aren't the content security policy.
        $cspHeaders = [
            'content-security-policy',
            'content-security-policy-report-only',
        ];
        if (!in_array(strtolower($header), $cspHeaders)) {
            return;
        }

        $params = $request->routeParams();
        // If the request is processed by an IFramePageController, update the CSP.
        if (!empty($params['Controller']) && $params['Controller'] == IFramePageController::class) {
            // Get the current IFramePage.
            if (!$page = IFramePage::get_by_link($request->getURL())) {
                return;
            }
            // Get the Iframe URL.
            $parts = parse_url($page->IFrameURL);
            if (!isset($parts['scheme']) || !isset($parts['host'])) {
                return;
            }
            // Update the CSP header value.
            $frameSrc = "{$parts['scheme']}://{$parts['host']}";
            $value = preg_replace('/(frame-src [^;]*?);/', '$1 ' . $frameSrc . ';', $value);
        }
    }
}
```
In YAML config:
```YAML
Signify\Middleware\SecurityHeaderMiddleware:
  extensions:
    - App\Extensions\UpdateIframeCSPHeaderExtension
```
