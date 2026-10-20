<?php namespace Pensoft\Matomo\Classes;

use Config;
use Pensoft\Matomo\Models\Settings;

/**
 * What the theme's tracking code needs (Twig: matomo_site_id(), matomo_url()).
 */
class Tracker
{
    /**
     * The site's Matomo ID: the one saved by matomo:register (Settings),
     * else MATOMO_SITE_ID; null while the site has none.
     */
    public static function siteId()
    {
        $id = (int) Settings::get('site_id');
        if ($id < 1) {
            $id = (int) Config::get('pensoft.matomo::site_id');
        }
        return $id > 0 ? $id : null;
    }

    /**
     * The Matomo server without its protocol, ending in "/", for the
     * tracker snippet's "var u=" (e.g. "//matomo.pensoft.net/").
     */
    public static function trackerUrl()
    {
        $url = (string) Config::get('pensoft.matomo::url');
        return '//' . rtrim(preg_replace('#^([a-z][a-z0-9+.-]*:)?//#i', '', trim($url)), '/') . '/';
    }
}
