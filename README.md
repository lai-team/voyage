# beauVoyage — Basic functions (`voyage`)

The core plugin behind [beau.voyage](https://beau.voyage/): a WordPress
**multisite** plugin that ties together memberships, per-member subsites,
geotagged travel content, search, and the site's white-labelled admin.

Running in production at https://symphony.beau.voyage.

It is the counterpart to the [`bv_map`](https://github.com/lai-team/bv_map)
plugin, which renders the journey map; `bv_map` calls several functions defined
here (`bv_parentcat`, `bv_childcat`, `bv_userlocation`, `bv_member_privilege`)
and reads the `$special_categories` global.

## Requirements

- WordPress **multisite** — the plugin calls `switch_to_blog()`, `wpmu_*` and
  loads `wp-admin/includes/ms.php` at bootstrap
- PHP 7.4+
- Required plugins:
  - **Paid Member Subscriptions** (+ add-ons) — memberships, plans, signup flow
  - **The Events Calendar** — itineraries are `tribe_events`
  - **Advanced Custom Fields Pro** — field groups registered in code
  - **ElasticPress** — search across subsites
  - **TranslatePress** — machine-translation defaults
  - **Admin Columns Pro** — column layouts stored in `acp-settings/`
  - **Amazon Polly** — *only* for its bundled AWS SDK, see below
  - **WP-GeoMeta** — spatial meta, shared with `bv_map`

## Configuration

### Defined in `wp-config.php`

| Constant | Used for |
| --- | --- |
| `BASIC_ID` / `PREMIUM_ID` | PMS subscription plan ids |
| `PREMIUM_SPACE_LIMIT` | Upload quota for premium subsites |
| `MIN_LENGTH_URL` | Minimum subsite slug length |
| `EP_HOST` | ElasticPress endpoint |
| `ES_AWS_KEY` / `ES_AWS_SECRET` / `AWS_REGION` | SigV4 signing of ElasticPress requests |
| `EVENTS_POSTTYPE` | The Events Calendar post type |
| `GOOG_MAP_KEY` | Google Maps key, injected into ACF and TEC |
| `GOOG_TRANSLATEv2_KEY` / `GOOG_TRANSLATEv2_CHARLIMIT` | TranslatePress machine translation |
| `WP_GEOMETA_DEBUG` | WP-GeoMeta debug level |

None of these is guarded with `defined()` at its point of use, so a staging or
rebuilt environment without this exact `wp-config.php` fataled on PHP 8. The
ElasticPress ones are now guarded; the rest are not.

### Defined in the plugin

`MAIN_TAB_NAME` and `BV_PLUGIN_DIR_URL` in `voyage.php`; `CDN_URL` in
`includes/cdn_rewrite.php` (defaults to `//cdn.beau.voyage`, overridable from
`wp-config.php`).

### Referenced but not defined anywhere

`DB_NAME_BLOGS` was the only genuinely undefined constant in the codebase, read
at `voyage.php:500` and `:512`. On PHP 8 that is a fatal `Error`, and it was
thrown part-way through subsite deletion, after the site had already been
removed. Both references were in the `information_schema` sweep that has since
been deleted, so nothing reads it now.

`PMS_GROUP_NAME` is also undefined but is never read — only a commented-out
`define()` remains.

> **Correction.** An earlier revision of this README, and the commit message of
> `64b73b4`, claimed that `BASIC_ID`, `PREMIUM_ID`, `AWS_REGION`,
> `PMS_GROUP_NAME` and `GOOG_TRANSLATEv2_CHARLIMIT` were undefined. That was
> wrong: all but `PMS_GROUP_NAME` are defined in `wp-config.php` using double
> quotes, and the scan that produced the claim only matched single-quoted
> `define()` calls.

## Shortcodes

```
[bv_register_subsite_form]             Subsite registration form
[shortcode_subscription]               Subscription plan picker
```

## Layout

```
voyage.php                        Bootstrap, role capabilities, subsite lifecycle
shortcodes/shortcodes.php         Loader for the shortcode files
shortcodes/register_subsite.php   Subsite registration form
shortcodes/shortcode_subscription.php  Plan picker
includes/pms_actions.php          Paid Member Subscriptions actions, GDPR deletion
includes/pms_filters.php          Plan output and membership filters
includes/search_filter.php        Cross-subsite search; private-category exclusion
includes/elasticpress_aws.php     SigV4 signing for ElasticPress
includes/tec_hooks.php            The Events Calendar integration
includes/acf_metadata.php         ACF field groups (Geolocation etc.) in code
includes/acf_googlefilters.php    Google Maps key into ACF
includes/trp_filters.php          TranslatePress machine-translation defaults
includes/whitelabel.php           Admin menu/branding for non-admin roles
includes/gutenbergblocks.php      Allowed block types per role
includes/cdn_rewrite.php          Rewrites media URLs to the CDN
includes/spatial_functions.php    Journey statistics
includes/handle_requests.php      Front-end POST handling for subsite creation
acp-settings/                     Admin Columns Pro layouts (written at runtime)
assets/                           Admin/front CSS and JS, white-label script
```

## Behaviour worth knowing before you change anything

- **Role capabilities are mutated on every request.** `voyage.php:56-62` runs at
  file scope, not on activation: editors are granted `manage_options` and lose
  `edit_tribe_venues` / `edit_tribe_organizers`; contributors gain
  `read_private_posts`. Granting `manage_options` to editors is a broad
  privilege change, and because it writes to the roles option it also happens
  outside any activation hook.
- **The AWS SDK is borrowed from another plugin.** `includes/elasticpress_aws.php:6`
  loads `WP_PLUGIN_DIR . '/amazon-polly/vendor/aws/aws-autoloader.php'`.
  Deactivating or removing Amazon Polly breaks ElasticPress signing.
- **`acp-settings/` is written at runtime.** `voyage.php` points Admin Columns
  Pro's file storage at this directory via the `acp/storage/file/directory`
  filter. The files are intended to be version-controlled, but editing columns
  in wp-admin produces uncommitted changes here.

## Known issues

Still open, deliberately — recorded rather than changed:

- **`bv_member_privilege()` is `current_user_can('read_private_posts')`**, and
  `voyage.php` grants that capability to the entire `contributor` role. "Paying
  member" therefore evaluates to "any contributor", here and in the `bv_map`
  REST endpoint. Tightening it decides who can see paid content, so it needs a
  product decision rather than a guess.
- **`pms_filters.php` sets `$output = ''`**, discarding what PMS core appended
  at the same filter priority — which includes the payment-gateway selector.
  This may mean paid signups fail server-side validation. Unconfirmed: the
  registration page redirects for anonymous visitors. Check it logged in before
  changing anything, because if the premise is wrong the fix breaks checkout.
- **Block restrictions in `gutenbergblocks.php` are UI-only.** `allowed_block_types`
  filters the inserter; it does not validate `post_content` on save, so block
  markup can still arrive via the REST API or the code editor. The real control
  is the `unfiltered_html` capability, which nothing here touches.
- **`whitelabel.php` grants `manage_privacy_options` to user IDs 1–3**, a
  persistent capability keyed on a magic numeric range.
- **`includes/elasticpress_aws.php` borrows the AWS SDK** from the
  `amazon-polly` plugin's vendor directory. Now guarded, so a missing file
  degrades instead of fataling — but ElasticPress 5.x ships its own SigV4
  support and this filter should be retired in favour of it.
- **`wp-geometa` has been patched locally** (`wp-geoutil.php:714` adds
  `linestring` and `point` to `get_capabilities()`). Any update to that plugin
  reverts the patch, at which point `WP_GeoUtil::point()` returns null. The ACF
  save path no longer discards the user's pin when that happens, but map
  geometry will stop being generated.
- **Large forks of upstream code**: `bv_get_adjacent_post()` is ~190 lines of
  WordPress core's `get_adjacent_post()`, and `bv_pms_output_subscription_plans()`
  is ~175 lines of PMS 2.4.0's `pms_output_subscription_plans()`. Both are
  pinned to the version they were copied from and must be re-synced by hand.
- Roughly 430 lines of `voyage.php` are commented-out code inside otherwise-live
  functions.
- Indentation is inconsistent between files (`voyage.php` uses tabs, several
  includes use four spaces). Nothing has been reformatted.

Fixed since the initial import — see the commit log on `refactor/inspect-and-fix`
for the full reasoning on each:

- A logged-in member could strip capabilities from **every user on the network**
  via the subsite deletion path.
- Deleting subsite 12 would have dropped the database tables of sites 120–129
  (`LIKE 'wp_12_%'` without `esc_like()`; `_` is a wildcard).
- Members who cancelled kept the role their paid plan granted.
- The AWS request-signing host guard failed open.
- Editors network-wide, including on `beau.voyage`, were granted `manage_options`.
- Restricted posts were readable by direct permalink.
- `includes/geometry.php`, which did not parse *and* redeclared the core
  function `delete_post_meta()`, was deleted.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Credits

Lai Consulting Team. Previously hosted at `gitlab.com/beauvoyage/voyage`.
