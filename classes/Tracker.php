<?php namespace Pensoft\Matomo\Classes;

use Config;
use Pensoft\Matomo\Models\Settings;

/**
 * The site's tracking code (Twig: matomo_tracking_code(), matomo_site_id(),
 * matomo_url()) and a check for tracking code pasted into the theme by hand.
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

    /**
     * The whole Matomo tracking snippet for the page <head>, or nothing
     * while the site has no Matomo ID (a test site sends no tracking).
     * Twig: {{ matomo_tracking_code() }}
     *
     * @return \Twig\Markup
     */
    public static function trackingCode()
    {
        $id = static::siteId();
        if (!$id) {
            return new \Twig\Markup('', 'UTF-8');
        }
        $flags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $push = [];
        $extensions = trim((string) Config::get('pensoft.matomo::tracker.download_extensions'));
        if ($extensions !== '') {
            $push[] = "  _paq.push(['addDownloadExtensions', " . json_encode($extensions, $flags) . "]);";
        }
        $push[] = "  _paq.push(['trackPageView']);";
        $push[] = "  _paq.push(['enableLinkTracking']);";

        $html = "<!-- Matomo (Pensoft.Matomo) -->\n<script>\n"
            . "  var _paq = window._paq = window._paq || [];\n"
            . implode("\n", $push) . "\n"
            . "  (function() {\n"
            . "    var u=" . json_encode(static::trackerUrl(), $flags) . ";\n"
            . "    _paq.push(['setTrackerUrl', u+'matomo.php']);\n"
            . "    _paq.push(['setSiteId', '" . (int) $id . "']);\n"
            . "    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];\n"
            . "    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);\n"
            . "  })();\n"
            . "</script>\n<!-- End Matomo Code -->\n";
        return new \Twig\Markup($html, 'UTF-8');
    }

    /**
     * Tracking code pasted into the active theme by hand: a setSiteId with
     * a fixed number (e.g. copied from another project, with its ID).
     * Returns [['file' => 'layouts/default.htm', 'line' => 126, 'id' => '81'], ...].
     */
    public static function hardcodedSnippets($themePath = null)
    {
        if ($themePath === null) {
            $theme = \Cms\Classes\Theme::getActiveTheme();
            $themePath = $theme ? $theme->getPath() : null;
        }
        if (!$themePath || !is_dir($themePath)) {
            return [];
        }
        $found = [];
        foreach (['layouts', 'pages', 'partials', 'content'] as $dir) {
            if (!is_dir($themePath . '/' . $dir)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($themePath . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                // Only files October uses (.htm); backups (.bak) are ignored.
                if (strtolower($file->getExtension()) !== 'htm') {
                    continue;
                }
                $lines = @file($file->getPathname());
                foreach ((array) $lines as $n => $line) {
                    if (preg_match('/[\'"]setSiteId[\'"]\s*,\s*[\'"]?(\d+)/', $line, $m)) {
                        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($themePath) + 1));
                        $found[] = ['file' => $rel, 'line' => $n + 1, 'id' => $m[1]];
                    }
                }
            }
        }
        return $found;
    }
}
