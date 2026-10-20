<?php

/*
 * Pensoft.Matomo configuration. Secrets and the environment flag come from
 * .env (never commit them); the defaults below apply to every new site.
 */
return [

    // Matomo server, e.g. https://matomo.pensoft.net
    'url' => env('MATOMO_URL', 'https://matomo.pensoft.net'),

    // API token of a Matomo super user (adding sites needs super user access).
    'token' => env('MATOMO_TOKEN'),

    // "production" once the site is hosted on its real address. Anything
    // else (e.g. "test") and matomo:register refuses to run.
    'environment' => env('MATOMO_ENVIRONMENT', 'test'),

    // Matomo user who gets "write" access to the new site.
    'grant_user' => env('MATOMO_GRANT_USER'),
    'grant_access' => env('MATOMO_GRANT_ACCESS', 'write'),

    // The site's Matomo ID when it is already registered (e.g. by hand).
    // The ID saved by matomo:register (backend > Settings > Matomo) wins.
    'site_id' => env('MATOMO_SITE_ID'),

    // How the site is registered. Name and URLs default to APP_NAME/APP_URL;
    // MATOMO_SITE_URLS takes extra addresses, comma separated.
    'site' => [
        'name' => env('MATOMO_SITE_NAME'),
        'urls' => env('MATOMO_SITE_URLS'),
    ],

    // Default settings for every new site (SitesManager.addSite).
    'defaults' => [
        'timezone'                => env('MATOMO_TIMEZONE', 'Europe/Sofia'),
        'currency'                => env('MATOMO_CURRENCY', 'EUR'),
        'ecommerce'               => 0,
        'siteSearch'              => 1,
        'searchKeywordParameters' => 'q,query,search',
        'excludedQueryParameters' => 'fbclid,gclid,msclkid,mc_cid,mc_eid',
        'excludedIps'             => env('MATOMO_EXCLUDED_IPS', ''),
        'keepURLFragments'        => 0,
        'excludeUnknownUrls'      => 0,
        'group'                   => env('MATOMO_GROUP', ''),
        'type'                    => 'website',
    ],

    // The tracking code ({{ matomo_tracking_code() }}): extra file types
    // counted as downloads (Matomo's own list lacks e.g. eps and svg).
    'tracker' => [
        'download_extensions' => env('MATOMO_DOWNLOAD_EXTENSIONS', 'eps|svg'),

        // Cookies (MATOMO_COOKIES):
        //  off            - no Matomo cookies at all (disableCookies); visits are
        //                   still counted. No cookie banner needed for Matomo.
        //  on             - Matomo's own cookies (returning visitors recognised);
        //                   needs consent under GDPR in most cases.
        //  consent        - visits are counted without cookies until the visitor
        //                   accepts; cookies only after that (requireCookieConsent).
        //  consent_strict - nothing is tracked at all until the visitor accepts
        //                   (requireConsent).
        // With the consent modes, a banner / privacy page calls
        // window.pensoftMatomo.accept() / .reject() / .withdraw(), or uses
        // data-matomo-consent="accept|reject|withdraw" on a button or link.
        'cookies' => env('MATOMO_COOKIES', 'off'),
    ],

    // Seconds to wait for the Matomo API.
    'timeout' => 20,
];
