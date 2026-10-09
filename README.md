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
| `EP_HOST` | ElasticPress endpoint |
| `ES_AWS_KEY` / `ES_AWS_SECRET` | SigV4 signing of ElasticPress requests |
| `EVENTS_POSTTYPE` | The Events Calendar post type |
| `GOOG_MAP_KEY` | Google Maps key, injected into ACF and TEC |
| `WP_GEOMETA_DEBUG` | WP-GeoMeta debug level |

### Defined in the plugin

`MAIN_TAB_NAME` and `BV_PLUGIN_DIR_URL` in `voyage.php`; `CDN_URL` in
`includes/cdn_rewrite.php` (currently hardcoded to `//cdn.beau.voyage`).

### Referenced but **not defined anywhere**

These are read by live code paths yet are not defined in this plugin, in
`wp-config.php`, or anywhere else in the install. On PHP 8 an undefined
constant is a fatal `Error`, so any request reaching these lines dies:

| Constant | Referenced at |
| --- | --- |
| `BASIC_ID` | `includes/pms_actions.php:135`, `includes/pms_filters.php:18`, `shortcodes/shortcode_subscription.php` (default argument) |
| `PREMIUM_ID` | `voyage.php:438`, `voyage.php:632` (default argument), `voyage.php:633` |
| `DB_NAME_BLOGS` | `voyage.php:500`, `voyage.php:512` |
| `AWS_REGION` | `includes/elasticpress_aws.php:31` |
| `PMS_GROUP_NAME` | referenced once |
| `GOOG_TRANSLATEv2_CHARLIMIT` | `includes/trp_filters.php` |

`voyage.php:19-21` carries commented-out `define()` calls for `BASIC_ID`,
`PREMIUM_ID` and `PMS_GROUP_NAME` with placeholder values — they appear to have
been moved out and never reinstated. Two are used as **default parameter
values** (`bv_create_subsite(..., $master_id = PREMIUM_ID)` and
`shortcode_subscription($plan1 = BASIC_ID, ...)`), so they evaluate whenever the
function is called without that argument.

## Shortcodes

```
[bv_register_subsite_form]             Subsite registration form
[shortcode_subscription]               Subscription plan picker
[is_user_account_confirmed_shortcode]  Wraps content behind email confirmation
[delete_subsite_button]                Delete the current user's subsite
[delete_user_button]                   GDPR account deletion
```

## Layout

```
voyage.php                        Bootstrap, role capabilities, subsite lifecycle
shortcodes/shortcodes.php         Loader for the shortcode files
shortcodes/register_subsite.php   Subsite registration form
shortcodes/shortcode_subscription.php  Plan picker
shortcodes/custom_shortcodes.php  Account-confirmation gate
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
includes/geometry.php             Legacy postgeom table creation — NOT loaded
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
- **`includes/geometry.php` is not loaded** — its `include` is commented out at
  `voyage.php:43`.

## Known issues

- **`includes/geometry.php` does not parse.** `php -l` fails with
  `syntax error, unexpected token "global"` at line 22: line 21 is
  `$max_index_length=191` with no terminating semicolon. Harmless only because
  the file is never included; uncommenting that `include` would white-screen the
  site. Left as-is in the initial commit so the repository records exactly what
  is deployed.
- **The undefined constants above** are PHP 8 fatals on the paths that reach them.
- **`includes/handle_requests.php:1`** calls
  `require_once( wp_normalize_path( ABSPATH ) . 'wp-load.php' )` from inside a
  plugin that WordPress has already loaded, then handles `$_POST` at file scope.
  It checks a nonce field named `_wp_nonce` — note WordPress's own convention is
  `_wpnonce`.
- Indentation is inconsistent between files (`voyage.php` uses tabs, several
  includes use four spaces). Nothing has been reformatted.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Credits

Lai Consulting Team. Previously hosted at `gitlab.com/beauvoyage/voyage`.
