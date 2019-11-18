# Magento 2 UPWARD connector module

The Magento 2 UPWARD connector is a module for routing requests to [UPWARD-PHP][].
This module replaces the default Magento frontend theme with a PWA Studio storefront application.

PWA Studio storefront deployments in the Magento Commerce Cloud use this module to keep Magento and storefront code on the same server.

## Installation

The Magento 2 UPWARD connector module is part of the [Magento Cloud deployment][] steps in the official PWA Studio docs.

## Configuration

The Magento 2 UPWARD connector is configured in the admin area under:

**Stores > Configuration > General > Web > UPWARD PWA Configuration**.

### UPWARD Config File

This configuration is the location of the UPWARD configuration file for the UPWARD-PHP server.

_Use the absolute path on the server for the value of this configuration._

Example: `/app/node_modules/@magento/venia-concept/dist/upward.yml`

### Front Name Whitelist

This configuration allows you to specify a line-separated list of routes to forward to the default Magento theme.

Example:

```text
contact
privacy-policy-cookie-restriction-mode
```

With this example, when a visitor navigates to either `<Magento store URL>/contact` or `<Magento store URL>/privacy-policy-cookie-restriction-mode`, they will land on a page rendered by Magento instead of the storefront application.

[upward-php]: https://github.com/magento-research/upward-php
[magento cloud deployment]: http://pwastudio.io/tutorials/cloud-deploy/