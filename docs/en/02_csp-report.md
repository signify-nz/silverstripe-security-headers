# Content Security Policy Violations Report

Unless disabled ([see below](#disabling-reporting), content security policy violations will automatically be added to a report in the CMS in the reports section (at `/admin/reports/show/Signify-SecurityHeaders-Reports-CSPViolationsReport`).
Both the report-uri directive and the report-to directive/header combination are supported, though report-to is currently disabled by default as the implementation for that directive and header are expected to change (see the [working draft](https://www.w3.org/TR/reporting/) and the [editor's draft](https://w3c.github.io/reporting/)).

If you supply your own endpoint using the report-uri directive, the default will also be added to it. It is expected that browsers will send a report to each endpoint in the report-uri directive.

## Report-Only mode

You can optionally apply the `SecurityHeaderSiteconfigExtension` extension to SiteConfig. This gives you the option to set the Content Security Policy to report only mode (this swaps from using the `Content-Security-Policy` header to the `Content-Security-Policy-Report-Only` header) so that you can test CSP settings without enforcing them.
It also comes with its own permission in case you want some members to have access to the site settings, but not this CSP setting.

```yml
SilverStripe\SiteConfig\SiteConfig:
  extensions:
    - Signify\SecurityHeaders\Extensions\SecurityHeaderSiteconfigExtension
```

## Enabling report-to

Because report-to isn't widely supported yet, the report-uri directive will always be present even if report-to is enabled. browsers that support report-to will prefer it over report-uri, but everyone else will keep consuming the report-uri directive.

Note that only one endpoint can be provided for CSP reporting when using the report-to directive. Additionally supplied endpoints will be ignored by the browser. For this reason, if you supply your own report-to directive and header in your configuration, this module will not add the default one and the CMS report will only be added to by browsers that do not support report-to.

To enable the report-to directive and Report-To header to be used (for browsers that support it) to report violations, use the following yml configuration:

```yml
---
After: 'signify-security-headers'
---
Signify\SecurityHeaders\Extensions\SecurityHeaderControllerExtension:
  use_report_to: true
```

Note that you can provide your own report-to directive and Report-To header even if the above value is set to false, though browsers that support it may ignore the report-uri directive, resulting in reports only arriving at your defined URI and not the default one.

## Disabling reporting

If you don't want the CMS reporting endpoint to automatically be added to the CSP configuration, you can add the following yml configuration:

```yml
---
After: 'signify-security-headers'
---
Signify\SecurityHeaders\Extensions\SecurityHeaderControllerExtension:
  enable_reporting: false
```

Note that this does not disable the endpoint or remove the report from the CMS - it only stops the endpoint from being _automatically_ added to the Content-Security-Policy header

If `enable_reporting` is set to false, the value of `use_report_to` (see above) no longer matters

Note that this also doesn't affect the ability to set the CSP to report-only mode with SecurityHeaderControllerExtension (see [Apply the extensions](#apply-the-extensions)).
