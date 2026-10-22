<?php namespace Pensoft\Matomo;

use System\Classes\PluginBase;
use System\Classes\MarkupManager;
use Pensoft\Matomo\Classes\Tracker;

/**
 * Matomo: registers the site in Matomo (php artisan matomo:register) and
 * gives the theme the site's Matomo ID for its tracking code.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'pensoft.matomo::lang.plugin.name',
            'description' => 'pensoft.matomo::lang.plugin.description',
            'author'      => 'Pensoft',
            'icon'        => 'icon-bar-chart',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('matomo.register', \Pensoft\Matomo\Console\RegisterSite::class);
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'pensoft.matomo::lang.settings.label',
                'description' => 'pensoft.matomo::lang.settings.description',
                'category'    => 'system::lang.system.categories.cms',
                'icon'        => 'icon-bar-chart',
                'class'       => \Pensoft\Matomo\Models\Settings::class,
                'order'       => 600,
                'keywords'    => 'matomo analytics tracking',
                'permissions' => ['pensoft.matomo.settings'],
            ],
        ];
    }

    public function registerFormWidgets(): array
    {
        return [
            \Pensoft\Matomo\FormWidgets\RegisterButton::class => 'matomoregister',
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'pensoft.matomo.settings' => [
                'tab'   => 'pensoft.matomo::lang.plugin.name',
                'label' => 'pensoft.matomo::lang.permissions.settings',
            ],
        ];
    }

    /**
     * Twig:
     *  - matomo_tracking_code(): the whole tracking snippet for <head>, or
     *    nothing while the site has no Matomo ID - one line in the layout;
     *  - matomo_site_id(): the site's Matomo ID, or null;
     *  - matomo_url(): the Matomo server, protocol-relative;
     *  - matomo_cookie_mode(): off, on, consent or consent_strict (e.g. to
     *    show a cookie banner only when there is consent to ask for).
     *
     * Registered through MarkupManager in boot(): on October 4 functions
     * returned from registerMarkupTags() do not reach the theme's Twig.
     */
    public function boot(): void
    {
        MarkupManager::instance()->registerCallback(function ($manager): void {
            $manager->registerFunctions([
                'matomo_tracking_code' => fn() => Tracker::trackingCode(),
                'matomo_site_id'       => fn() => Tracker::siteId(),
                'matomo_url'           => fn() => Tracker::trackerUrl(),
                'matomo_cookie_mode'   => fn() => Tracker::cookieMode(),
            ]);
        });
    }
}
