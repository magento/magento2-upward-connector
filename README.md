# Magento 2 UPWARD connector module

The Magento 2 UPWARD connector is a module for routing requests to [UPWARD-PHP][].
This module replaces the default Magento frontend theme with a PWA Studio storefront application.

PWA Studio storefront deployments in the Magento Commerce Cloud use this module to keep Magento and storefront code on the same server.

## Installation

The Magento 2 UPWARD connector module is part of the [Magento Cloud deployment][] steps in the official PWA Studio docs.

## Configuration

The Magento 2 UPWARD connector has additional settings that can be configured in the admin area under:

**Stores > Configuration > General > Web > UPWARD PWA Configuration**.

### General configuration

These are the configurations for the UPWARD process itself.

#### UPWARD Config File

This configuration is the location of the UPWARD configuration file for the UPWARD-PHP server.

This module adds a new directive to the env.php to securely set the path to the upward.yaml file.
```php
    // ...
    'downloadable_domains' => [
        // ...
    ],
    # New configuration point
    'pwa_path' => [
        'default' => [
            'default' => '/var/www/html/pwa/dist/upward.yml'
        ],
        'website' => [
            '<website_code>' => '/var/www/html/anotherpwa/dist/upward.yml' # Can point a website to a different installation
        ],
        'store' => [
            '<store_code>' => '' # Blank string (or false) to serve default Magento storefront
        ]
    ]
```

For ease of use, this module provides a new command for setting the path
```shell
# Set the default scope to an empty string (will serve base Magento store front)
bin/magento pwa:upward:set

# Set the website with code <website_code> to /var/www/html/pwa/dist/upward.yml
bin/magento pwa:upward:set --path /var/www/html/pwa/dist/upward.yml --scopeType website --scopeCode <website_code>

# Set the website with code <website_code> to an empty string (will serve base Magento store front)
bin/magento pwa:upward:set --scopeType website --scopeCode <website_code>

# Set the website with code <store_code> to /var/www/html/pwa/dist/upward.yml
bin/magento pwa:upward:set --path /var/www/html/pwa/dist/upward.yml --scopeType store --scopeCode <store_code>
```

_You can use `bin/magento store:list` or `bin/magento store:website:list` to easily get the store/website code for configuration._

_You may use a path relative to your web root or an absolute path for the value of this configuration._
- Relative: `pwa/dist/upward.yml`
- Absolute: `/var/www/html/pwa/dist/upward.yml`

If you have previously configured the UPWARD yaml path using the `config:set` command or environment variables, it will continue to work as a fallback, so long as no
default has been set as per the example above.

The configuration works the same way normal store configurations work. It falls back from store view > website > global (default),
trying to serve the more specific available scope first.

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

```sh
curl -A Googlebot https://www.example.com/ > page.html
```

To configure prerender locally for testing purposes, see https://docs.prerender.io/test-it/.

#### Troubleshooting partial rendered pages

There is no way to tell when a PWA page fully loads.
For prerendering it is possible to force prerender to wait for a predefined timeout before setting the `window.prerenderReady` flag.

Add the following to the runtime script:

```js
window.prerenderReady = false;
setTimeout(function () {
  window.prerenderReady = true;
}, 1000 * 15);
```

For more information, see https://docs.prerender.io/test-it/.

## Service Worker Note

Avoid sharing the same hostname between your PWA Studio storefront and the Magento 2 admin backend.
This causes the storefront Service Worker to intercept backend requests when you have both the storefront and admin tabs open at the same time on your browser.
If you cannot avoid sharing the hostname, access one service at a time or use a private browsing session per service.

[upward-php]: https://github.com/magento/upward-php
[magento cloud deployment]: http://pwastudio.io/tutorials/cloud-deploy/
[prerender.io]: https://docs.prerender.io/
[system-specific best practices]: https://devdocs.magento.com/guides/v2.4/config-guide/prod/config-reference-var-name.html
