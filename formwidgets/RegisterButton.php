<?php namespace Pensoft\Matomo\FormWidgets;

use Backend;
use BackendAuth;
use Flash;
use Redirect;
use ApplicationException;
use Backend\Classes\FormField;
use Backend\Classes\FormWidgetBase;
use Pensoft\Matomo\Classes\Registrar;
use Pensoft\Matomo\Classes\Tracker;
use Pensoft\Matomo\Exceptions\MatomoException;

/**
 * "Register in Matomo" button on backend > Settings > Matomo: connects to
 * Matomo, registers the site's domain, saves the new site ID (the tracking
 * code uses it at once) and gives MATOMO_GRANT_USER access. Same steps and
 * the same production check as php artisan matomo:register.
 */
class RegisterButton extends FormWidgetBase
{
    protected $defaultAlias = 'matomoregister';

    public function render()
    {
        $registrar = Registrar::fromConfig();
        $this->vars['isProduction'] = $registrar->isProduction();
        $this->vars['environment'] = $registrar->environment();
        $this->vars['siteName'] = $registrar->siteName();
        $this->vars['siteUrls'] = $registrar->siteUrls();
        $this->vars['grant'] = $registrar->grantUser();
        $this->vars['siteId'] = Tracker::siteId();
        $this->vars['hardcoded'] = Tracker::hardcodedSnippets();
        return $this->makePartial('registerbutton');
    }

    /** Not a model value: nothing to save. */
    public function getSaveValue($value)
    {
        return FormField::NO_SAVE_DATA;
    }

    /**
     * AJAX: runs the registration. Errors (not in production, missing
     * configuration, Matomo errors) stop it and are shown to the user.
     */
    public function onRegisterMatomo()
    {
        if (!BackendAuth::getUser() || !BackendAuth::getUser()->hasAccess('pensoft.matomo.settings')) {
            throw new ApplicationException(trans('pensoft.matomo::lang.button.no_permission'));
        }

        $steps = [];
        $registrar = Registrar::fromConfig(function ($message) use (&$steps) { $steps[] = $message; });
        $password = trim((string) post('matomo_password')) ?: null;

        try {
            // First, and on its own: outside production nothing else runs.
            $registrar->assertProduction();
            $result = $registrar->register((bool) post('matomo_force'), false, $password);
        } catch (MatomoException $e) {
            throw new ApplicationException($e->getMessage());
        }

        Flash::success(trans('pensoft.matomo::lang.button.success', ['id' => $result['id']]) . ' ' . implode(' ', $steps));
        // Reload the page so the form shows the saved ID and status.
        return Redirect::to(Backend::url('system/settings/update/pensoft/matomo/settings'));
    }
}
