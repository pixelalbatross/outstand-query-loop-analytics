# Popular Posts

Populates a Query Loop with the most popular posts based on analytics data. The plugin connects to your analytics provider, retrieves the top posts by pageviews, resolves page paths to WordPress post IDs, and caches the results.

## Use cases

- Displaying trending or most-read posts without manual curation
- Building "popular this month" sections powered by real analytics data
- Combining popular posts with other Query Loop features (pagination, filtering, templates)

## How it works

### Block editor

1. Insert the **Popular Posts** variation of the Query Loop block (Inserter → search "Popular Posts").
2. Like a normal Query Loop, a setup screen appears — **Choose** a Popular Posts pattern (titles & dates, or grid with excerpt) or **Start blank**. Both paths keep popularity ordering (the patterns carry the namespace + `orderByPopularity`; see `includes/Patterns.php`).
3. Scope it with the controls — **Post Type**, **Display** (items per page, offset, max pages), and **Filters** (taxonomy, author, search).

The variation hides the controls that conflict with popularity ordering (inherit, order, sticky) via `allowedControls`, since popularity governs both order and the result set. Post type selection, per-page/pagination, taxonomy/author filtering, and custom post templates all continue to work.

The editor preview is **WYSIWYG**: the `core/post-template` edit component fetches the ranked IDs from the REST endpoint and injects them as `include` + `orderby` query args (via block-context override, no `ServerSideRender`), so the canvas mirrors the frontend. Post-type scoping happens automatically — the REST query filters `include` to the queried post type.

### Data flow

```
WP-Cron event fires (interval = cache duration)
  |
  v
Configured? -> No -> skip
  |- Yes -> Error backoff active? -> Yes -> skip
            |- No -> Refresh auth token if expired
                     |
                     v
                     Fetch top pages from analytics provider
                     |
                     v
                     Resolve page paths to WP post IDs (url_to_postid)
                     |
                     v
                     Filter by post type and publish status
                     |
                     v
                     Store in option (autoload off)
```

On the frontend, when a Query Loop block has popular posts enabled:

```
Query Loop renders
  |
  v
[query_loop_block_query_vars filter]
  |- Read cached popular post IDs
  |- Set post__in = popular IDs (preserves popularity order)
  |- Set orderby = post__in
  |
  v
WP_Query runs, populated with the most popular posts
```

### Data refresh

- Analytics data is fetched by a WP-Cron event scheduled at the cache-duration interval.
- Default cache duration is 12 hours (configurable in settings; changing it reschedules the event).
- On API error, a 15-minute backoff transient is set to prevent retry storms.
- The cron event is (re)scheduled on `admin_init` when the plugin is configured.

## PHP API

### `get_popular_posts()`

Get the cached popular posts data.

```php
use function Outstand\WP\QueryLoop\Analytics\get_popular_posts;

$popular = get_popular_posts( 5 );
```

Returns an array of associative arrays:

```php
[
    [ 'post_id' => 123, 'pageviews' => 4500 ],
    [ 'post_id' => 456, 'pageviews' => 3200 ],
    // ...
]
```

Returns an empty array if no data is cached or the plugin is not connected.

### `popular_posts` query arg

Use in any `WP_Query` to populate with popular posts:

```php
$query = new WP_Query( [
    'popular_posts'  => true,
    'posts_per_page' => 10,
] );
```

This sets `post__in` to the cached popular post IDs and `orderby` to `post__in`, preserving the popularity order. If an existing `post__in` is set, only posts that appear in both lists are included.

### `GET /outstand-query-loop-analytics/v1/popular-posts`

REST route returning the ranked popular post IDs (flat array), used by the block-editor WYSIWYG preview. Requires the `edit_posts` capability.

```
GET /wp-json/outstand-query-loop-analytics/v1/popular-posts  ->  [ 6, 9, 4, 10, 8 ]
```

## Filters

### `outstand_query_loop_analytics_date_range`

Override the report date range. Default: last 30 days.

```php
add_filter( 'outstand_query_loop_analytics_date_range', function ( array $range ): array {
    return [
        'start' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
        'end'   => gmdate( 'Y-m-d', strtotime( 'yesterday' ) ),
    ];
} );
```

### `outstand_query_loop_analytics_fetch_limit`

Override the maximum number of pages to fetch. Default: 20.

```php
add_filter( 'outstand_query_loop_analytics_fetch_limit', fn () => 50 );
```

### `outstand_query_loop_analytics_post_types`

Override which post types are resolved from page paths. Default: `['post']`.

```php
add_filter( 'outstand_query_loop_analytics_post_types', fn () => [ 'post', 'page', 'product' ] );
```

### `outstand_query_loop_analytics_popular_posts_data`

Filter the final popular posts array before caching.

```php
add_filter( 'outstand_query_loop_analytics_popular_posts_data', function ( array $posts ): array {
    // Remove posts with fewer than 100 pageviews.
    return array_filter( $posts, fn ( $p ) => $p['pageviews'] >= 100 );
} );
```

## Settings

Configurable via **Settings > Query Loop Analytics**:

| Setting | Default | Description |
|---------|---------|-------------|
| Date Range | 30 days | How many days of analytics data to query |
| Maximum Posts | 20 | Max pages to fetch |
| Cache Duration | 12 hours | How long to cache the results |

## Supported integrations

### Google Analytics 4

Connects to the GA4 Data API via OAuth 2.0 to retrieve pageview data.

#### Setup

**1. Create Google Cloud credentials**

1. Create a project in [Google Cloud Console](https://console.cloud.google.com/).
2. Enable the **Google Analytics Data API** and **Google Analytics Admin API**.
3. Go to **APIs & Services > Credentials** and create an **OAuth 2.0 Client ID** (Web application type).
4. Add the authorized redirect URI: `https://your-site.com/outstand-query-loop-analytics/oauth-callback`.
5. Copy the Client ID and Client Secret.

**2. Configure credentials**

Provide credentials via constants in `wp-config.php` (recommended):

```php
define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_ID', 'your-client-id.apps.googleusercontent.com' );
define( 'OUTSTAND_QUERY_LOOP_ANALYTICS_CLIENT_SECRET', 'your-client-secret' );
```

Or enter them in **Settings > Query Loop Analytics** in the WordPress admin. When constants are defined, the credentials fields are hidden from the settings page.

**3. Connect and select a property**

1. Go to **Settings > Query Loop Analytics**.
2. Click **Connect to Google Analytics**.
3. Authorize the application in the Google consent screen.
4. Select your GA4 property from the dropdown and save.

#### How it works

- Fetches the `screenPageViews` metric grouped by `pagePath` dimension from the GA4 Data API.
- Uses the `analytics.readonly` OAuth scope.
- OAuth tokens are encrypted at rest using `sodium_crypto_secretbox` with a key derived from WordPress authentication salts.
- Token refresh is handled automatically when the access token expires.

#### Stored data

| Storage | Key | Description |
|---------|-----|-------------|
| Option | `outstand_query_loop_analytics_settings` | Plugin settings (credentials, property ID, cache config) |
| Option | `outstand_query_loop_analytics_tokens` | Encrypted OAuth tokens |
| Option | `outstand_query_loop_analytics_popular_posts` | Cached popular posts array (autoload off) |

All data is removed on plugin uninstall.

#### Security

- OAuth tokens are encrypted at rest using `sodium_crypto_secretbox`.
- The OAuth callback is protected by a state nonce (`wp_create_nonce` / `wp_verify_nonce`).
- All settings actions require the `manage_options` capability.
- Disconnecting revokes the Google OAuth token before deleting stored credentials.
