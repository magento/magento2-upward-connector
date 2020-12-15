# Magento 2 UPWARD connector module

The Magento 2 UPWARD connector is a module for routing requests to [UPWARD-PHP][].
This module replaces the default Magento frontend theme with a PWA Studio storefront application.

PWA Studio storefront deployments in the Magento Commerce Cloud use this module to keep Magento and storefront code on the same server.

## Installation

The Magento 2 UPWARD connector module is part of the [Magento Cloud deployment][] steps in the official PWA Studio docs.

## Configuration

The Magento 2 UPWARD connector is configured in the admin area under:

**Stores > Configuration > General > Web > UPWARD PWA Configuration**.

### General configuration

These are the configurations for the UPWARD process itself.
#### UPWARD Config File

This configuration is the location of the UPWARD configuration file for the UPWARD-PHP server.

_Use the absolute path on the server for the value of this configuration._

Example: `/app/node_modules/@magento/venia-concept/dist/upward.yml`

#### Front Name Allowlist

This configuration allows you to specify a line-separated list of routes to forward to the default Magento theme.

Example:

```text
contact
privacy-policy-cookie-restriction-mode
```

With this example, when a visitor navigates to either `<Magento store URL>/contact` or `<Magento store URL>/privacy-policy-cookie-restriction-mode`, they will land on a page rendered by Magento instead of the storefront application.

### Prerender.io Configuration

[Prerender.io][] support in the upward-connector module allows your site to send prerendered static html to search bots.

A middleware layer checks each request to see if it comes from a crawler and if allowed, sends it to the prerender service.
These configuration entries let you configure which pages to send to Prerender.io to serve the static HTML versions of that page.
If a page is not configured for prerendering, the request continues using the normal server routes.

| Configuration                    | Description                                                                                    | Example                       |
| -------------------------------- | ---------------------------------------------------------------------------------------------- | ----------------------------- |
| Enable Prerender For Search Bots | This enables prerender functionality for this store view.                                      |                               |
| Prerender URL                    | Url of the prerender service.                                                                  | https://service.prerender.io/ |
| Prerender.io Token               | Token to use for the prenderer.io hosted service                                               |                               |
| Crawler User Agents              | Line break separated list of keywords to detect the crawler in the user-agent request header   |                               |
| Blocked List                     | Resources that will not be sent for prerendering. Use `*` as a wildcard character.             | `.js` `*/cart`                |
| Allowed List                     | Explicitly allowed resources to be sent for prerendering. If empty, all resources are allowed. |                               |

#### Testing prerendered pages

To see how a crawler sees a prerendered page, set your browser's User Agent to `Googlebot` and visit your URL.
You can also run this on the command line and change the sample URL to your storefront's URL:

``` sh
curl -A Googlebot https://www.example.com/ > page.html
```

To configure prerender locally for testing purposes, see https://docs.prerender.io/test-it/.

#### Troubleshooting partial rendered pages

There is no way to tell when a PWA page fully loads.
For prerendering it is possible to force prerender to wait for a predefined timeout before setting the `window.prerenderReady` flag.

Add the following to the runtime script:

``` js
window.prerenderReady = false;
setTimeout(function () { window.prerenderReady = true }, 1000 * 15)
```

For more information, see https://docs.prerender.io/test-it/.

## Service Worker Note

Avoid sharing the same hostname between your PWA Studio storefront and the Magento 2 admin backend.
This causes the storefront Service Worker to intercept backend requests when you have both the storefront and admin tabs open at the same time on your browser.
If you cannot avoid sharing the hostname, access one service at a time or use a private browsing session per service.

[upward-php]: https://github.com/magento/upward-php
[magento cloud deployment]: http://pwastudio.io/tutorials/cloud-deploy/
[prerender.io]: https://docs.prerender.io/
