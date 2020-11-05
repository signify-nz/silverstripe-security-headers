# Setup and Basic Configuration

## Apply the extension

Apply the `SecurityHeaderControllerExtension` to the controller of your choice - but we recommend applying it to `SilverStripe\Control\Controller` to ensure all responses from the website include your security headers.

For example, add this to your `_config/config.yml` file:

```yml
SilverStripe\Control\Controller:
  extensions:
    - Signify\Extensions\SecurityHeaderControllerExtension
```

## Configure the headers

While some default headers are already set for your convenience, you may wish to add new headers, alter the values for the default headers, or even remove the default headers. This is all done with the `headers` configurable array on the `SecurityHeaderControllerExtension` extension.

### Add or amend a header value

In the below example, the `X-Frame-Options` header value is changed from the default. This same syntax is used to add new headers.

```yml
---
After: 'signify-security-headers'
---
Signify\Extensions\SecurityHeaderControllerExtension:
  headers:
    X-Frame-Options: "allow-from https://example.com/"
```

### Remove a default header value

In the following example, the value for the `Strict-Transport-Security` header is cleared completely. Doing this means that the module will not add the `Strict-Transport-Security` header to any responses.
Note that either `null` or an empty string will have the same effect.

```yml
---
After: 'signify-security-headers'
---
Signify\Extensions\SecurityHeaderControllerExtension:
  headers:
    Strict-Transport-Security: null
```

## Changing the Content Security Policy

To maintain configuration compatibility with the [Guttmann/silverstripe-security-headers](https://github.com/guttmann/silverstripe-security-headers) module, and to make it clearer what the policy for your specific application is, individual CSP attributes can't be overridden. Rather, you must declare the full value for the `Content-Security-Policy` header if you wish to override it.
We recommend copying the value we use in the packaged [_config/config.yml file](../../_config/config.yml), and building onto it from there.
