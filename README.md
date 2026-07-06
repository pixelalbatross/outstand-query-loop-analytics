# Outstand Query Loop Analytics

> Populates the Query Loop block with popular posts based on analytics data.

Connects to your analytics provider, retrieves the top posts by pageviews, resolves them to WordPress post IDs, and caches the results. Works with all existing Query Loop features — pagination, filtering, and custom templates.

## Features

See [docs/popular-posts.md](docs/popular-posts.md) for the full feature guide.

## Installation

### Manual Installation

1. Download the latest release ZIP from the [Releases page](https://github.com/pixelalbatross/outstand-query-loop-analytics/releases/latest).
2. Go to Plugins > Add New > Upload Plugin in your WordPress admin area.
3. Upload the ZIP file and click Install Now.
4. Activate the plugin.

### Install with Composer

To include this plugin as a dependency in your Composer-managed WordPress project:

1. Add the plugin to your project using the following command:

```bash
composer require outstand/query-loop-analytics
```

2. Run `composer install`.
3. Activate the plugin from your WordPress admin area or using WP-CLI.

## Quick start

1. Go to **Settings > Query Loop Analytics** and connect your analytics provider ([setup guide](docs/popular-posts.md#supported-integrations)).
2. Select your analytics property.
3. In the block editor, insert the **Popular Posts** block (a Query Loop variation) from the inserter — or start from a **Popular Posts** pattern (_titles & dates_ or _grid with excerpt_). Posts are ordered by pageviews automatically.

## Requirements

- WordPress 6.7 or higher
- PHP 8.2 or higher

### Tests

JS tests run locally via Jest:

```bash
npm run test:js
```

PHP tests run inside a `wp-env` container:

```bash
npm run test:setup   # first time only — starts Docker WP + test DB
npm run test:unit
```

## Changelog

All notable changes to this project are documented in [CHANGELOG.md](https://github.com/pixelalbatross/outstand-query-loop-analytics/blob/main/CHANGELOG.md).

## License

This project is licensed under the [GPL-3.0-or-later](https://spdx.org/licenses/GPL-3.0-or-later.html).
