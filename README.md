# Pensoft.Matomo

Registers an October CMS site in Matomo and gives the theme its Matomo ID,
so the tracking code needs no hard-coded ID.

`php artisan matomo:register`:

1. **stops while the site is not in production**: `MATOMO_ENVIRONMENT` must be
   `production`, and the site address must not be a local / test one
   (`localhost`, `*.test`, `*.local` ...). Otherwise it explains that the site
   is still in the test phase and not hosted yet, and changes nothing;
2. looks the site up in Matomo by its address and reuses it if it is
   already there (no duplicates), otherwise adds it with the default
   settings (`config/config.php` > `defaults`);
3. saves the site ID (backend > Settings > Matomo) - the tracking code
   uses it from then on;
4. gives `MATOMO_GRANT_USER` `write` access to the site.

## Setup

In `.env` (never commit the token):

```
MATOMO_URL=https://matomo.pensoft.net
MATOMO_TOKEN=<API token of a Matomo super user>
MATOMO_ENVIRONMENT=test          # set to production once the site is live
MATOMO_GRANT_USER=<matomo login>
```

Optional: `MATOMO_GRANT_ACCESS` (`view` / `write` / `admin`, default `write`),
`MATOMO_SITE_NAME` (default `APP_NAME`, else the host), `MATOMO_SITE_URLS`
(extra addresses besides `APP_URL`, comma separated), `MATOMO_TIMEZONE`
(`Europe/Sofia`), `MATOMO_CURRENCY` (`EUR`), `MATOMO_EXCLUDED_IPS`,
`MATOMO_GROUP`, and `MATOMO_SITE_ID` for a site registered by hand.

Then `php artisan october:migrate` (records the plugin version).

## Usage

### Backend button

Backend > Settings > Matomo > **Register in Matomo** (needs the
"Manage the Matomo settings" permission). The panel shows the
environment, the site that will be registered, who gets access and whether
tracking is active. The button does the same as the command below, and
outside production it stops with an error message without contacting
Matomo. On success the page reloads with the new site ID filled in, and
the tracking code uses it straight away. The optional password field is
only sent with that request and is never saved.

### Command

```
php artisan matomo:register --dry-run      # what would happen; changes nothing
php artisan matomo:register                # register + grant access
php artisan matomo:register --access-only  # only grant access (e.g. after a failure)
php artisan matomo:register --password     # also ask for the token owner's password,
                                           # if Matomo wants it for access changes
php artisan matomo:register --force        # register although the site has an ID
```

The token is always sent in the POST body, never in a URL. If giving
access fails, the site ID is still saved, so running the command again does
not add the site twice - use `--access-only`.

## Theme

```twig
{% set matomo_id = matomo_site_id() %}
{% if matomo_id %}
    ... var u="{{ matomo_url() }}"; ... _paq.push(['setSiteId', '{{ matomo_id }}']); ...
{% endif %}
```

`matomo_site_id()` is the saved ID, else `MATOMO_SITE_ID`, else `null` - so a
test site without an ID sends no tracking at all.
