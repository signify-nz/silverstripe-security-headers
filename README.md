# SilverStripe security headers

Inspired by [Guttmann/silverstripe-security-headers](https://github.com/guttmann/silverstripe-security-headers). The configuration format used is compatible with that module.

SilverStripe module for easily adding a selection of [useful HTTP headers](https://wiki.owasp.org/index.php/OWASP_Secure_Headers_Project#tab=Headers).

Additionally provides a report of Content Security Policy violations.

Comes with a default set of headers configured, but can be used to add any headers you wish (as well as overriding or removing the default headers).

## Install

For SilverStripe 3, see the [appropriate branch](https://gitea.signify.nz/gsartorelli/silverstripe-security-headers/src/branch/1)

Install via [composer](https://getcomposer.org):

```bash
composer require signify-nz/silverstripe-security-headers
```

## Usage

For information on how to setup and use this module, please refer to the [documentation](docs/00_index.md).

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
