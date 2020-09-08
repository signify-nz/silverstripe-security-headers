# SilverStripe security headers

Inspired by [Guttmann/silverstripe-security-headers](https://github.com/guttmann/silverstripe-security-headers). The configuration format used is compatible with that module.

SilverStripe module for easily adding a selection of [useful HTTP headers](https://wiki.owasp.org/index.php/OWASP_Secure_Headers_Project#tab=Headers).

Additionally provides a report of Content Security Policy violations.

Comes with a default set of headers configured, but can be used to add any headers you wish.

## Install

Install via [composer](https://getcomposer.org):

    composer require signify-nz/silverstripe-security-headers

## Usage

### Apply the extensions

Apply the `SecurityHeaderControllerExtension` to the controller of your choice - but we recommend applying it to `SilverStripe\Control\Controller` to ensure all responses from the website include your security headers.

For example, add this to your `_config/config.yml` file:

```yml
SilverStripe\Control\Controller:
  extensions:
    - Signify\SecurityHeaderControllerExtension
```

You can also optionally apply the `SecurityHeaderSiteconfigExtension` extension to SiteConfig. This gives you the option to set the Content Security Policy to report only mode (this swaps from using the `Content-Security-Policy` header to the `Content-Security-Policy-Report-Only` header) so that you can test CSP settings without enforcing them.
It also comes with its own permission in case you want some members to have access to the site settings, but not this CSP setting.

```yml
SilverStripe\SiteConfig\SiteConfig:
  extensions:
    - Signify\Extensions\SecurityHeaderSiteconfigExtension
```

### Configure the headers

While some default headers are already set for your convenience, you may wish to add new headers, alter the values for the default headers, or even remove the default headers. This is all done with the `headers` configurable array on the `SecurityHeaderControllerExtension` extension.

#### Add or amend a header value

In the below example, the `X-Frame-Options` header value is changed from the default. This same syntax is used to add new headers.

```yml
---
After: 'signify-security-headers'
---
Signify\SecurityHeaderControllerExtension:
  headers:
    X-Frame-Options: "allow-from https://example.com/"
```

#### Remove a default header value

In the following example, the value for the `Strict-Transport-Security` header is cleared completely. Doing this means that the module will not add the `Strict-Transport-Security` header to any responses.
Note that either `null` or an empty string will have the same effect.

```yml
---
After: 'signify-security-headers'
---
Signify\SecurityHeaderControllerExtension:
  headers:
    Strict-Transport-Security: null
```

#### Changing the Content Security Policy

To maintain configuration compatibility with the [Guttmann/silverstripe-security-headers](https://github.com/guttmann/silverstripe-security-headers) module, and to make it clearer what the policy for your specific application is, individual CSP attributes can't be overridden. Rather, you must declare the full value for the `Content-Security-Policy` header if you wish to override it.  
We recommend copying the value we use in the packaged `_config/config.yml` file, and building onto it from there.

### Content Security Policy Violation Reports

Unless disabled (see below), content security policy violations will automatically be added to a report in the CMS in the reports section (at `/admin/reports/show/Signify-Reports-CSPViolationsReport`).  
Both the report-uri directive and the report-to directive/header combination are supported, though report-to is currently disabled by default as the implementation for that directive and header are expected to change (see the [working draft](https://www.w3.org/TR/reporting/) and the [editor's draft](https://w3c.github.io/reporting/).

If you supply your own endpoint using the report-uri directive, the default will also be added to it. It is expected that browsers will send a report to each endpoint in the report-uri directive.

#### Enabling report-to

Because report-to isn't widely supported yet, the report-uri directive will always be present even if report-to is enabled. browsers that support report-to will prefer it over report-uri, but everyone else will keep consuming the report-uri directive.

Note that only one endpoint can be provided for CSP reporting when using the report-to directive. Additionally supplied endpoints will be ignored by the browser. For this reason, if you supply your own report-to directive and header in your configuration, this module will not add the default one and the CMS report will only be added to by browsers that do not support report-to.

To enable the report-to directive and Report-To header to be used (for browsers that support it) to report violations, use the following yml configuration:

```yml
---
After: 'signify-security-headers'
---
Signify\SecurityHeaderControllerExtension:
  use_report_to: true
```

Note that you can provide your own report-to directive and Report-To header even if the above value is set to false, though browsers that support it may ignore the report-uri directive, resulting in reports only arriving at your defined URI and not the default one.

#### Disabling reporting

If you don't want the CMS reporting endpoint to automatically be added to the CSP configuration, you can add the following yml configuration:

```yml
---
After: 'signify-security-headers'
---
Signify\SecurityHeaderControllerExtension:
  enable_reporting: false
```

Note that this does not disable the endpoint or remove the report from the CMS - it only stops the endpoint from being _automatically_ added to the Content-Security-Policy heade
  
If `enable_reporting` is set to false, the value of `use_report_to` (see above) no longer matters

Note that this also doesn't affect the ability to set the CSP to report-only mode with SecurityHeaderControllerExtension (see [Apply the extensions](#apply-the-extensions)).
