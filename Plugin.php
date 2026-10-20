<?php namespace Pensoft\Matomo;

use System\Classes\PluginBase;
use Pensoft\Matomo\Classes\Tracker;

/**
 * Matomo: registers the site in Matomo (php artisan matomo:register) and
 * gives the theme the site's Matomo ID for its tracking code.
 */
class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'pensoft.matomo::lang.plugin.name',
            'description' => 'pensoft.matomo::lang.plugin.description',
            'author'      => 'Pensoft',
            'icon'        => 'icon-bar-chart',
        ];
    }

    public function register()
    {
        $this->registerConsoleCommand('matomo.register', \Pensoft\Matomo\Console\RegisterSite::class);
    }

    public function registerSettings()
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

    public function registerFormWidgets()
    {
        return [
            \Pensoft\Matomo\FormWidgets\RegisterButton::class => 'matomoregister',
        ];
    }

    public function registerPermissions()
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
     *  - matomo_url(): the Matomo server, protocol-relative.
     */
    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'matomo_tracking_code' => [Tracker::class, 'trackingCode'],
                'matomo_site_id'       => [Tracker::class, 'siteId'],
                'matomo_url'           => [Tracker::class, 'trackerUrl'],
            ],
        ];
    }
}
