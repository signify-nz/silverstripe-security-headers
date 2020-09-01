# SilverStripe security headers

Inspired by [Guttmann/silverstripe-security-headers](https://github.com/guttmann/silverstripe-security-headers). The configuration format used is compatible with that module.

SilverStripe module for easily adding a selection of [useful HTTP headers](https://www.owasp.org/index.php/List_of_useful_HTTP_headers).

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

Documentation for this is TBC as the functionality is still in development.
