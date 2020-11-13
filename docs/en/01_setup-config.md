# Setup and Basic Configuration

The middleware is already added when you install the module.

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
    Strict-Transport-Security: null
```

## Changing the Content Security Policy

To make it clearer what the policy for your specific application is, individual CSP attributes can't be overridden. Rather, you must declare the full value for the `Content-Security-Policy` header if you wish to override it.
We recommend copying the value we use in the packaged [_config/config.yml file](../../_config/config.yml), and building onto it from there.
