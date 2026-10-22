<?php namespace Pensoft\Matomo\Models;

use Model;

/**
 * The site's Matomo registration (backend > Settings > Matomo): written by
 * matomo:register, editable by hand.
 */
class Settings extends Model
{
    public $implement = [\System\Behaviors\SettingsModel::class];

    public $settingsCode = 'pensoft_matomo_settings';

    public $settingsFields = 'fields.yaml';
}
