<?php

return [
    'plugin' => [
        'name' => 'Matomo',
        'description' => 'Registers the site in Matomo and provides its ID to the tracking code.',
    ],
    'settings' => [
        'label' => 'Matomo',
        'description' => 'The site\'s Matomo ID (set by php artisan matomo:register).',
    ],
    'permissions' => [
        'settings' => 'Manage the Matomo settings',
    ],
    'fields' => [
        'site_id' => 'Matomo site ID',
        'site_id_comment' => 'Used by the tracking code. Filled in by php artisan matomo:register; leave empty to use MATOMO_SITE_ID from .env (no tracking when neither is set).',
        'registered_url' => 'Registered address',
        'registered_at' => 'Registered at',
        'access_granted_to' => 'Access granted to',
    ],
    'hardcoded' => [
        'title' => 'Tracking code pasted into the theme',
        'text' => 'The theme has Matomo tracking code with a fixed site ID. It ignores the ID saved here, keeps sending visits to that site (often another project\'s, if it was copied), and counts them twice next to matomo_tracking_code():',
        'line' => 'line :line - site ID :id',
        'fix' => 'Remove it and put this line in the layout\'s <head> instead:',
        'console' => 'Tracking code with a fixed site ID found in the theme (it ignores the registered ID; replace it with {{ matomo_tracking_code() }}):',
    ],
    'button' => [
        'section' => 'Register in Matomo',
        'label' => 'Register in Matomo',
        'working' => 'Registering in Matomo...',
        'confirm' => 'Register this site in Matomo now? It adds the site (or finds it, if Matomo already has it), saves its ID for the tracking code and grants access.',
        'success' => 'Registered in Matomo: site ID :id - the tracking code uses it now.',
        'no_permission' => 'You do not have permission to manage the Matomo settings.',
        'not_production_title' => 'The site is not in production',
        'not_production' => 'MATOMO_ENVIRONMENT is ":env". Registration is blocked until the site is live on its real address and MATOMO_ENVIRONMENT=production is set in .env.',
        'environment' => 'Environment (MATOMO_ENVIRONMENT)',
        'site' => 'Site to register',
        'access' => 'Access granted to',
        'not_set' => 'is not set',
        'tracking' => 'Tracking code',
        'tracking_on' => 'active - Matomo site :id',
        'tracking_off' => 'off - the site has no Matomo ID yet',
        'password' => 'Your Matomo password (optional)',
        'password_comment' => 'Only if Matomo asks to confirm the password for access changes. Sent with this request only, never saved.',
        'force' => 'The site already has ID :id - go ahead anyway (a site Matomo already has under this address is reused, not doubled).',
    ],
];
