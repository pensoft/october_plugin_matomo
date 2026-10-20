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
        $mode = static::cookieMode();
        $push = [];
        // Cookie / consent settings must come before trackPageView.
        if ($mode === 'off') {
            $push[] = "  _paq.push(['disableCookies']);";
        } elseif ($mode === 'consent') {
            $push[] = "  _paq.push(['requireCookieConsent']);";
        } elseif ($mode === 'consent_strict') {
            $push[] = "  _paq.push(['requireConsent']);";
        }
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
            . "</script>\n"
            . (in_array($mode, ['consent', 'consent_strict'], true) ? static::consentScript($mode) : '')
            . "<!-- End Matomo Code -->\n";
        return new \Twig\Markup($html, 'UTF-8');
    }

    /**
     * The consent API for the consent modes:
     *   window.pensoftMatomo.accept() / .reject() / .withdraw() / .state()
     *   (state: "accepted", "rejected" or null = not asked yet);
     *   or a button / link with data-matomo-consent="accept|reject|withdraw".
     * The choice is kept in localStorage; <html data-matomo-consent="...">
     * shows it (e.g. to hide a banner once answered), and a
     * "pensoft-matomo-consent" event fires on document when it changes.
     * Matomo itself remembers the consent in its own cookie.
     */
    protected static function consentScript($mode)
    {
        $strict = $mode === 'consent_strict';
        $give = $strict ? 'rememberConsentGiven' : 'rememberCookieConsentGiven';
        $forget = $strict ? 'forgetConsentGiven' : 'forgetCookieConsentGiven';
        return "<script>\n"
            . "(function (w, d) {\n"
            . "  var KEY = 'pensoft-matomo-consent';\n"
            . "  function read() { try { return w.localStorage.getItem(KEY); } catch (e) { return null; } }\n"
            . "  function set(state, call) {\n"
            . "    w._paq.push([call]);\n"
            . "    try { w.localStorage.setItem(KEY, state); } catch (e) {}\n"
            . "    d.documentElement.setAttribute('data-matomo-consent', state);\n"
            . "    try { d.dispatchEvent(new CustomEvent('pensoft-matomo-consent', { detail: { state: state } })); } catch (e) {}\n"
            . "  }\n"
            . "  w.pensoftMatomo = {\n"
            . "    mode: '" . $mode . "',\n"
            . "    state: read,\n"
            . "    accept: function () { set('accepted', '" . $give . "'); },\n"
            . "    reject: function () { set('rejected', '" . $forget . "'); },\n"
            . "    withdraw: function () { set('rejected', '" . $forget . "'); }\n"
            . "  };\n"
            . "  d.documentElement.setAttribute('data-matomo-consent', read() || 'pending');\n"
            . "  d.addEventListener('click', function (e) {\n"
            . "    var el = e.target.closest && e.target.closest('[data-matomo-consent]');\n"
            . "    if (!el || el === d.documentElement) return;\n"
            . "    var action = el.getAttribute('data-matomo-consent');\n"
            . "    if (typeof w.pensoftMatomo[action] !== 'function' || action === 'state') return;\n"
            . "    e.preventDefault();\n"
            . "    w.pensoftMatomo[action]();\n"
            . "  });\n"
            . "})(window, document);\n"
            . "</script>\n";
    }

    /**
     * Tracking code pasted into the active theme by hand: a setSiteId with
     * a fixed number (e.g. copied from another project, with its ID).
     * Returns [['file' => 'layouts/default.htm', 'line' => 126, 'id' => '81'], ...].
     */
    public static function hardcodedSnippets($themePath = null)
    {
        $found = [];
        foreach (static::themeFiles($themePath) as $rel => $path) {
            foreach ((array) @file($path) as $n => $line) {
                if (preg_match('/[\'"]setSiteId[\'"]\s*,\s*[\'"]?(\d+)/', $line, $m)) {
                    $found[] = ['file' => $rel, 'line' => $n + 1, 'id' => $m[1]];
                }
            }
        }
        return $found;
    }

    /**
     * True when the active theme has something that gives or withdraws
     * Matomo consent (data-matomo-consent="..." or window.pensoftMatomo).
     */
    public static function hasConsentControls($themePath = null)
    {
        foreach (static::themeFiles($themePath) as $path) {
            $text = (string) @file_get_contents($path);
            if (strpos($text, 'data-matomo-consent') !== false || strpos($text, 'pensoftMatomo.') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * The cookie mode (MATOMO_COOKIES): off, on, consent or consent_strict.
     * Anything else counts as "off" - the safe choice.
     */
    public static function cookieMode()
    {
        $mode = strtolower(trim((string) Config::get('pensoft.matomo::tracker.cookies')));
        return in_array($mode, ['on', 'consent', 'consent_strict'], true) ? $mode : 'off';
    }

    /**
     * The theme files October uses (.htm in layouts, pages, partials,
     * content; backups such as .bak are left out), as [relative => full path].
     */
    protected static function themeFiles($themePath = null)
    {
        if ($themePath === null) {
            $theme = \Cms\Classes\Theme::getActiveTheme();
            $themePath = $theme ? $theme->getPath() : null;
        }
        if (!$themePath || !is_dir($themePath)) {
            return [];
        }
        $files = [];
        foreach (['layouts', 'pages', 'partials', 'content'] as $dir) {
            if (!is_dir($themePath . '/' . $dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($themePath . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (strtolower($file->getExtension()) === 'htm') {
                    $files[str_replace('\\', '/', substr($file->getPathname(), strlen($themePath) + 1))] = $file->getPathname();
                }
            }
        }
        return $files;
    }
}
