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
Signify\SecurityHeaders\Middleware\SecurityHeaderMiddleware:
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
Signify\SecurityHeaders\Middleware\SecurityHeaderMiddleware:
  headers:
    global:
      Strict-Transport-Security: null
```

## Changing the Content Security Policy

To make it clearer what the policy for your specific application is, individual CSP attributes can't be overridden. Rather, you must declare the full value for the `Content-Security-Policy` header if you wish to override it.
We recommend copying the value we use in the packaged [_config/config.yml file](../../_config/config.yml), and building onto it from there.

## Managing the Content Security Policy via the CMS

In addition to the static, YAML-configured `Content-Security-Policy` header described above, this module provides a CMS interface for building a CSP dynamically from editable records. This is useful for content editors or developers who need to adjust CSP sources without a code deployment.

### CSP Directives and Policies

Two DataObjects work together to build the custom CSP:

- **CSP Directive** - represents a single CSP directive (e.g. `script-src`, `img-src`, `object-src`). Each directive has a set of checkboxes controlling which standard CSP keywords are included:
    - **Allow self** - adds `'self'`
    - **Allow unsafe inline** - adds `'unsafe-inline'` (only relevant to `script-src`/`style-src`)
    - **Allow unsafe eval** - adds `'unsafe-eval'` (only relevant to `script-src`)
    - **Allow data URI** - adds `data:` (relevant to `img-src`, `font-src`, `script-src`, `style-src`)
    - **Allow none** - adds `'none'`, blocking the directive entirely. This cannot be combined with any other option or with linked policies.

- **CSP Policy** - represents a single allowed source value (e.g. `https://www.youtube.com`) that can be linked to one or more directives via a many-to-many relationship. A `CSPPolicy` value must not contain whitespace, commas, semicolons, or quotes, since it's expected to be a single URL or source expression.

Both are managed in the CMS under **CSP Directives** and **CSP Policies** in the **CSP** admin area.

### How the custom CSP is built

Each CSP directive is resolved independently:
- If a `CSPDirective` has **Allow none** checked, it's included as `'none'` - no other options or linked policies are needed.
- Otherwise, if a `CSPDirective` has at least one checkbox checked (e.g. **Allow self**) or at least one linked `CSPPolicy`, it's built from those checkboxes plus any linked policy values.
- If a `CSPDirective` has no checkboxes checked and no linked policies, or if no `CSPDirective` record exists at all for a given directive name, that directive falls back to the value configured in the [YAML `Content-Security-Policy` header](#changing-the-content-security-policy) described above.

This means the CMS-managed directives and the YAML-configured base policy work together, directive by directive, rather than one replacing the other entirely. Any directive you explicitly configure via the CMS overrides its equivalent in the YAML config, while any directive you leave unconfigured continues to use the YAML value.

`block-all-mixed-content` is always appended to the generated policy exactly once, regardless of whether it appears in the YAML config.

### Disabling the custom CSP entirely

If you'd prefer to disable the CMS-managed custom CSP altogether and always use the YAML-configured `Content-Security-Policy` value instead, regardless of any `CSPDirective`/`CSPPolicy` records, set:

```yaml
Signify\SecurityHeaders\Middleware\SecurityHeaderMiddleware:
  enable_custom_csp: false
```

This is `true` by default.

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
Signify\SecurityHeaders\Middleware\SecurityHeaderMiddleware:
  extensions:
    - App\Extensions\UpdateIframeCSPHeaderExtension
```
