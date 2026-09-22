# Prestashop Cloudflare

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.2-green)](https://php.net/)
[![Minimum Prestashop Version](https://img.shields.io/badge/prestashop-%3E%3D%201.7.6.0-green)](https://www.prestashop.com)
[![GitHub release](https://img.shields.io/github/v/release/Pixel-Open/prestashop-cloudflare)](https://github.com/Pixel-Open/prestashop-cloudflare/releases)

## About this fork

This is a PresTeamShop fork of [Pixel-Open/prestashop-cloudflare](https://github.com/Pixel-Open/prestashop-cloudflare).

Upstream targets PrestaShop 8 / Symfony 4.4 but declares compatibility from
1.7.6.0. On PrestaShop 1.7 the module does not work: **every one of its three
entry points returns HTTP 500**. Measured and fixed on 1.7.7.4:

**1. The configuration screen — `You have requested a non-existent service "twig"`.**
A module settings page is served by `AdminModules`, a legacy controller, and the
legacy container does not expose `twig`. `js.twig` turned out to be plain
JavaScript without a single Twig tag, so it is now read from disk.

**2. The dashboard toolbar button — same missing service.**
Its markup is now built in PHP. `toolbar.html.twig` also relied on `path()`,
which only exists inside Symfony's Twig environment, so the URL is resolved
through the router; the button is skipped if the router is unreachable rather
than breaking the page it renders on.

**3. *Clear Cloudflare Cache* — `The controller for URI "/modules/cloudflare/clearCache" is not callable. Class "pixel.cloudflare.controller" does not exist`.**
Three things were missing, and each hid the next:
- the controller was only registered in the legacy container, so the Symfony
  router never saw it → added `config/admin/services.yml`;
- it lacked the `controller.service_arguments` tag, without which it never
  enters the locator the resolver queries;
- and the service id was not a class name. Symfony 3.4 runs `class_exists($id)`
  **before** asking the container, so friendly ids can never resolve — arbitrary
  ids only work from Symfony 4.1. The service is now registered under its FQCN
  (the old id is kept as an alias) and the route points at it.

On top of that the classes were not autoloadable at all: PrestaShop 1.7 does not
load a module's `vendor/autoload.php`, and this module never required it — unlike
every module that does work on 1.7.

**4. Both buttons — `Undefined class constant 'LOG_SEVERITY_LEVEL_ERROR'`.**
`PrestaShopLogger` declares no severity constants before PrestaShop 8; the module
referenced them six times. One sits inside a `catch`, where it also swallowed the
original error it was trying to log. Replaced with module-level constants.

All of it stays compatible with PrestaShop 8, where an FQCN service id is the
standard. Everything else is upstream, under its original MIT license.

## Presentation

Cloudflare API features in Prestashop:

- Clear Cloudflare Cache in the Prestashop admin

![Flush Cloudflare Cache](screenshot.png)

## Requirements

- Prestashop >= 1.7.6.0 / Prestashop >= 8.0 / Prestashop >= 9.0
- PHP >= 7.2.0

## Installation

Download the **pixel_cloudflare.zip** file from the [last release](https://github.com/Pixel-Open/prestashop-cloudflare/releases/latest) assets.

### Admin

Go to the admin module catalog section and click **Upload a module**. Select the downloaded zip file.

### Manually

Move the downloaded file in the Prestashop **modules** directory and unzip the archive. Go to the admin module catalog section and search for "Cloudflare".

## Configuration

From the module manager, find the module and click on configure.

| Field                | Description                                                                            | Required |
|:---------------------|:---------------------------------------------------------------------------------------|----------|
| Zone ID              | The website Zone ID                                                                    | Y        |
| Authentication mode  | The authentication mode: API Token or Global API key                                   | Y        |
| API Token *          | A valid token from your Cloudflare Account with permission on "Cache Purge" for "Zone" | Y        |
| Global API Key       | The Cloudflare Global API key                                                          | Y        |
| Account Email        | Email address associated with your Cloudflare account                                  | Y        |

\* For an API Token authentication (more secure), create a new custom API token with permissions on:

- Zone - Cache Purge - Purge
- Zone - Zone Settings - Edit
- Zone - Zone Settings - Read

![Flush Cloudflare Cache](token.png)

## Clear the cache

In admin, go to *Advanced settings > Performance*

- Clear only Cloudflare cache with the button: **Clear Cloudflare Cache**
- Clear prestashop and Cloudflare cache with the button: **Clear cache**
