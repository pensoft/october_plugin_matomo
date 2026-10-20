<?php namespace Pensoft\Matomo\Console;

use Illuminate\Console\Command;
use Pensoft\Matomo\Classes\Registrar;
use Pensoft\Matomo\Exceptions\MatomoException;

/**
 * php artisan matomo:register
 *
 * Registers this site in Matomo with the default settings, saves its ID for
 * the tracking code and gives MATOMO_GRANT_USER write access to it.
 * Refuses while MATOMO_ENVIRONMENT is not "production".
 */
class RegisterSite extends Command
{
    protected $signature = 'matomo:register
        {--dry-run : Show what would be done, change nothing}
        {--force : Register even when the site already has a Matomo ID}
        {--access-only : Only give MATOMO_GRANT_USER access to the saved site}
        {--password : Ask for the token owner\'s Matomo password (if Matomo wants it for access changes)}';

    protected $description = 'Register this site in Matomo, save its ID for the tracking code and grant a user access.';

    public function handle()
    {
        $log = function ($message) { $this->line('  ' . $message); };
        $registrar = Registrar::fromConfig($log);

        try {
            $registrar->assertProduction();
        } catch (MatomoException $e) {
            $this->warn('');
            $this->warn('  STOPPED - the site is not in production.');
            $this->warn('  ' . $e->getMessage());
            $this->warn('');
            return 1;
        }

        $password = $this->option('password') ? $this->secret('Matomo password of the token\'s user') : null;

        try {
            if ($this->option('access-only')) {
                $registrar->assertConfigured();
                $id = $registrar->existingId();
                if (!$id) {
                    $this->error('This site has no Matomo ID yet - run php artisan matomo:register first.');
                    return 1;
                }
                if ($this->option('dry-run')) {
                    $log('Dry run - would give access to site ' . $id . '.');
                    return 0;
                }
                $registrar->grantAccess($id, $password);
                $this->info('Done.');
                return 0;
            }

            $this->info('Registering "' . $registrar->siteName() . '" (' . implode(', ', $registrar->siteUrls()) . ') in Matomo...');
            $result = $registrar->register((bool) $this->option('force'), (bool) $this->option('dry-run'), $password);
            if ($this->option('dry-run')) {
                $this->info('Dry run - nothing was changed.');
            } else {
                $this->info('Done: this site is Matomo site ' . $result['id'] . ($result['created'] ? ' (new).' : ' (existing).'));
            }
            return 0;
        } catch (MatomoException $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
