<?php

/**
 * @package     Cybersalt.Plugin
 * @subpackage  System.CsTemplateCacheClear
 *
 * @copyright   Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;

return new class () implements InstallerScriptInterface {
    use DatabaseAwareTrait;

    public function __construct()
    {
        $this->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));
    }

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        // Joomla calls postflight() on uninstall too: no card, no changes then.
        if (!\in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }

        // On first install switch the plugin on, so it works with no admin trip.
        // An update keeps whatever the site owner chose.
        if ($type === 'install' || $type === 'discover_install') {
            $this->enablePlugin();
        }

        $this->renderPostInstallCard($type);

        return true;
    }

    private function enablePlugin(): void
    {
        try {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('cstemplatecacheclear'));
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            // Non-fatal: the site owner can switch it on in System, Plugins.
        }
    }

    private function getExtensionId(): int
    {
        try {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('cstemplatecacheclear'));

            return (int) $db->setQuery($query)->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function renderPostInstallCard(string $type): void
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $title     = $esc(Text::_($type === 'update'
            ? 'PLG_SYSTEM_CSTEMPLATECACHECLEAR_UPDATED'
            : 'PLG_SYSTEM_CSTEMPLATECACHECLEAR_INSTALLED'));
        $lead      = $esc(Text::_('PLG_SYSTEM_CSTEMPLATECACHECLEAR_CARD_LEAD'));
        $stylesL   = $esc(Text::_('PLG_SYSTEM_CSTEMPLATECACHECLEAR_CARD_STYLES'));
        $settingsL = $esc(Text::_('PLG_SYSTEM_CSTEMPLATECACHECLEAR_CARD_SETTINGS'));

        $extensionId = $this->getExtensionId();
        $stylesUrl   = $esc('index.php?option=com_templates&view=styles&client_id=0');
        $settingsUrl = $esc($extensionId > 0
            ? 'index.php?option=com_plugins&task=plugin.edit&extension_id=' . $extensionId
            : 'index.php?option=com_plugins&view=plugins&filter[folder]=system&filter[search]=cstemplatecacheclear');

        echo <<<HTML
<div class="card border-success mt-3">
    <div class="card-header bg-success text-white">
        <h3 class="card-title mb-0">{$title}</h3>
    </div>
    <div class="card-body">
        <p class="lead">{$lead}</p>
        <p class="mb-0">
            <a class="btn btn-primary text-white text-decoration-none me-2" href="{$stylesUrl}">
                <span class="icon-paint-brush" aria-hidden="true"></span> {$stylesL}
            </a>
            <a class="btn btn-secondary text-white text-decoration-none" href="{$settingsUrl}">
                <span class="icon-cog" aria-hidden="true"></span> {$settingsL}
            </a>
        </p>
    </div>
</div>
HTML;
    }
};
