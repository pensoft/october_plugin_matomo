<?php namespace Pensoft\Matomo\Classes;

use Carbon\Carbon;
use Config;
use Illuminate\Support\Arr;
use Pensoft\Matomo\Exceptions\MatomoException;
use Pensoft\Matomo\Models\Settings;

/**
 * Registers this site in Matomo: checks it is in production, adds the
 * site with the default settings (or finds it when it is already there),
 * saves its ID, and gives the configured user access to it.
 * Used by php artisan matomo:register.
 */
class Registrar
{
    /** @var array config/config.php */
    protected $config;

    /** @var MatomoClient|null */
    protected $client;

    /** @var callable */
    protected $log;

    public function __construct(array $config, MatomoClient $client = null, callable $log = null)
    {
        $this->config = $config;
        $this->client = $client;
        $this->log = $log ?: function () {};
    }

    /**
     * A registrar with the plugin's configuration (config/config.php + .env).
     */
    public static function fromConfig(callable $log = null)
    {
        $config = [];
        foreach (['url', 'token', 'environment', 'grant_user', 'grant_access', 'site_id', 'site', 'defaults', 'timeout'] as $key) {
            $config[$key] = Config::get('pensoft.matomo::' . $key);
        }
        return new static($config, null, $log);
    }

    /** True when MATOMO_ENVIRONMENT is "production". */
    public function isProduction()
    {
        return strtolower(trim((string) $this->get('environment'))) === 'production';
    }

    /** MATOMO_ENVIRONMENT as set (for display). */
    public function environment()
    {
        return trim((string) $this->get('environment'));
    }

    /** The Matomo user who gets access, and the access level. */
    public function grantUser()
    {
        return [(string) $this->get('grant_user'), (string) $this->get('grant_access')];
    }

    /**
     * Stops when the site is not marked as production, or its address is
     * still a local / test one.
     *
     * @throws MatomoException
     */
    public function assertProduction()
    {
        $env = strtolower(trim((string) $this->get('environment')));
        if ($env !== 'production') {
            throw new MatomoException(
                'This site is still in the test phase (MATOMO_ENVIRONMENT=' . ($env !== '' ? $env : 'not set') . ') and is not hosted yet, '
                . 'so it must not be registered in Matomo. The process was stopped and nothing was changed. '
                . 'Once the site is live on its real address, set MATOMO_ENVIRONMENT=production in .env and run the command again.'
            );
        }
        foreach ($this->siteUrls() as $url) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
                || preg_match('/\.(test|local|localhost|example|invalid)$/', $host)) {
                throw new MatomoException(
                    'The site address "' . $url . '" is a local or test address, so the site is not hosted yet. '
                    . 'Set APP_URL (or MATOMO_SITE_URLS) to its real address before registering it. Nothing was changed.'
                );
            }
        }
    }

    /**
     * Checks everything needed is configured.
     *
     * @throws MatomoException
     */
    public function assertConfigured($needsGrantUser = true)
    {
        $missing = [];
        if (!trim((string) $this->get('url'))) {
            $missing[] = 'MATOMO_URL';
        }
        if (!trim((string) $this->get('token'))) {
            $missing[] = 'MATOMO_TOKEN';
        }
        if ($needsGrantUser && !trim((string) $this->get('grant_user'))) {
            $missing[] = 'MATOMO_GRANT_USER';
        }
        if (!$this->siteUrls()) {
            $missing[] = 'APP_URL (or MATOMO_SITE_URLS)';
        }
        if ($missing) {
            throw new MatomoException('Missing configuration in .env: ' . implode(', ', $missing) . '.');
        }
        $access = $this->get('grant_access');
        if (!in_array($access, ['view', 'write', 'admin'], true)) {
            throw new MatomoException('MATOMO_GRANT_ACCESS must be view, write or admin (it is "' . $access . '").');
        }
    }

    /** The site's addresses: APP_URL first, then MATOMO_SITE_URLS. */
    public function siteUrls()
    {
        $urls = [];
        $extra = preg_split('/\s*,\s*/', (string) $this->get('site.urls'), -1, PREG_SPLIT_NO_EMPTY);
        foreach (array_merge([(string) config('app.url')], $extra) as $u) {
            $u = rtrim(trim($u), '/');
            if ($u !== '' && !in_array($u, $urls, true)) {
                $urls[] = $u;
            }
        }
        return $urls;
    }

    /** The name the site gets in Matomo. */
    public function siteName()
    {
        $name = trim((string) $this->get('site.name'));
        if ($name === '') {
            $name = trim((string) config('app.name'));
        }
        if ($name === '' || $name === 'October CMS' || $name === 'Laravel') {
            $urls = $this->siteUrls();
            $name = $urls ? (string) parse_url($urls[0], PHP_URL_HOST) : 'Website';
        }
        return $name;
    }

    /** The settings every new site gets (config "defaults"). */
    public function defaults()
    {
        return (array) $this->get('defaults');
    }

    /** The ID the site already has (saved, or MATOMO_SITE_ID), else null. */
    public function existingId()
    {
        return Tracker::siteId();
    }

    /**
     * Registers the site. Returns ['id' => int, 'created' => bool].
     *  - $force: go ahead although the site already has a saved ID (a site
     *    Matomo already has under this address is still reused, never doubled).
     *  - $dryRun: only report what would happen.
     *
     * @throws MatomoException
     */
    public function register($force = false, $dryRun = false, $passwordConfirmation = null)
    {
        $this->assertProduction();
        $this->assertConfigured();

        $current = $this->existingId();
        if ($current && !$force) {
            throw new MatomoException(
                'This site already has Matomo ID ' . $current . ', so it was not registered again. '
                . 'Use --access-only to grant access only, or --force to register it anyway.'
            );
        }

        $urls = $this->siteUrls();
        $name = $this->siteName();
        $client = $this->client();

        // Already in Matomo under this address (e.g. added by hand): use it.
        $found = $client->siteIdsForUrl($urls[0]);
        if ($found) {
            $id = $found[0];
            $this->say('Found "' . $urls[0] . '" in Matomo already (ID ' . $id . ') - using it instead of adding a duplicate.');
            $created = false;
        } elseif ($dryRun) {
            $this->say('Dry run - would add the site "' . $name . '" (' . implode(', ', $urls) . ') with: ' . json_encode($this->defaults()));
            $this->say('Dry run - would give ' . $this->get('grant_user') . ' "' . $this->get('grant_access') . '" access to it.');
            return ['id' => null, 'created' => false];
        } else {
            $id = $client->addSite($name, $urls, $this->defaults());
            $this->say('Added the site "' . $name . '" to Matomo: ID ' . $id . '.');
            $created = true;
        }

        if ($dryRun) {
            $this->say('Dry run - would save ID ' . $id . ' and give ' . $this->get('grant_user') . ' "' . $this->get('grant_access') . '" access to it.');
            return ['id' => $id, 'created' => false];
        }

        // Saved before the access step: if that fails, a second run must
        // not add the site again.
        $this->save($id, $urls[0]);
        $this->grantAccess($id, $passwordConfirmation);

        return ['id' => $id, 'created' => $created];
    }

    /**
     * Gives the configured user access to the site.
     *
     * @throws MatomoException
     */
    public function grantAccess($id, $passwordConfirmation = null)
    {
        $user = (string) $this->get('grant_user');
        $access = (string) $this->get('grant_access');
        try {
            $this->client()->setUserAccess($user, $access, $id, $passwordConfirmation);
        } catch (MatomoException $e) {
            $hint = stripos($e->getMessage(), 'password') !== false
                ? ' Matomo asks for the password of the token\'s user: run php artisan matomo:register --access-only --password.'
                : ' Fix the problem, then run php artisan matomo:register --access-only.';
            throw new MatomoException('The site has Matomo ID ' . $id . ' (saved), but giving ' . $user . ' access failed: ' . $e->getMessage() . '.' . $hint);
        }
        Settings::set('access_granted_to', $user . ' (' . $access . ')');
        $this->say('Gave ' . $user . ' "' . $access . '" access to site ' . $id . '.');
    }

    protected function save($id, $url)
    {
        Settings::set([
            'site_id' => $id,
            'registered_url' => $url,
            'registered_at' => Carbon::now()->toDateTimeString(),
        ]);
        $this->say('Saved Matomo ID ' . $id . ' (backend > Settings > Matomo); the tracking code now uses it.');
    }

    protected function client()
    {
        if (!$this->client) {
            $this->client = new MatomoClient($this->get('url'), $this->get('token'), (int) $this->get('timeout') ?: 20);
        }
        return $this->client;
    }

    protected function get($key)
    {
        return Arr::get($this->config, $key);
    }

    protected function say($message)
    {
        call_user_func($this->log, $message);
    }
}
