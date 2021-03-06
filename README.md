[![Build Status](https://travis-ci.com/signify-nz/silverstripe-security-headers.svg?branch=master)](https://travis-ci.com/signify-nz/silverstripe-security-headers)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/signify-nz/silverstripe-security-headers/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/signify-nz/silverstripe-security-headers/?branch=master)

# SilverStripe security headers

Inspired by [Guttmann/silverstripe-security-headers](https://github.com/guttmann/silverstripe-security-headers).

SilverStripe module for easily adding a selection of [useful HTTP headers](https://wiki.owasp.org/index.php/OWASP_Secure_Headers_Project#tab=Headers).

Additionally provides a report of Content Security Policy violations.

Comes with a default set of headers configured, but can be used to add any headers you wish (as well as overriding or removing the default headers).

## Install

For SilverStripe 3, see the [appropriate branch](https://github.com/signify-nz/silverstripe-security-headers/tree/1)

Install via [composer](https://getcomposer.org):

```bash
composer require signify-nz/silverstripe-security-headers
```

## Usage

For information on how to setup and use this module, please refer to the [documentation](docs/en/00_index.md).

## Contributing

If you would like to contribute either via code fixes, enhancements, or localisations, please see [the contributing guidelines](CONTRIBUTING.md).

## CSS/JS Development
### Setup
For development you will need Node.js and yarn installed.

Next, you need to install the required npm packages.
```bash
yarn install
```
### Compiling assets
You can compile assets using `yarn watch`.

Produce minified (production) files using `yarn package`.

### Linting
Check over your JavaScript and SASS source code individually:

```bash
yarn lint-js
yarn lint-sass
```

You can also lint both in a single command:
```bash
yarn lint
```
