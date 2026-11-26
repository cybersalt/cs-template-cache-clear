<?php

/**
 * @package     Cybersalt.Plugin
 * @subpackage  System.CsTemplateCacheClear
 *
 * @copyright   Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Cybersalt\Plugin\System\CsTemplateCacheClear\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryAwareInterface;
use Joomla\CMS\Cache\CacheControllerFactoryAwareTrait;
use Joomla\CMS\Event\Model\AfterCleanCacheEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

/**
 * Cybersalt Template Cache Clear
 *
 * Empties Joomla's page cache (System - Page Cache) when a site template
 * style is saved (Save and Save & Close) or made the default, so visitors see
 * the change on their next page load instead of when the cached pages expire.
 *
 * Each site template style gets a "Clear cache on save" switch on its edit
 * screen (default: on). A template can declare a "clearcacheonsave" field in
 * its own templateDetails.xml instead; the plugin then uses that field and
 * adds nothing.
 *
 * Joomla itself already empties the com_templates and _system cache groups
 * when a style is saved, so the page group is the only one left to clear.
 */
final class CsTemplateCacheClear extends CMSPlugin implements
    SubscriberInterface,
    DatabaseAwareInterface,
    CacheControllerFactoryAwareInterface
{
    use DatabaseAwareTrait;
    use CacheControllerFactoryAwareTrait;

    /** Name of the switch in the template style's params. */
    public const FIELD = 'clearcacheonsave';

    /** Auto-load language files */
    protected $autoloadLanguage = true;

    /** Set once the page cache has been emptied in this request. */
    private bool $cleared = false;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepareForm' => 'onContentPrepareForm',
            'onExtensionAfterSave' => 'onExtensionAfterSave',
            'onContentCleanCache'  => 'onContentCleanCache',
        ];
    }

    /**
     * Add the "Clear cache on save" tab to a site template style's edit form.
     * Also runs when the form is validated on save (admin, API and the
     * front-end Template Settings screen), so the value isn't filtered out.
     */
    public function onContentPrepareForm(PrepareFormEvent $event): void
    {
        $form = $event->getForm();

        if ($form->getName() !== 'com_templates.style') {
            return;
        }

        // The template declares the switch itself: use that one.
        if ($form->getField(self::FIELD, 'params') !== false) {
            return;
        }

        if ($this->getStyleClientId($event->getData()) !== 0) {
            return;
        }

        $form->loadFile(JPATH_PLUGINS . '/system/cstemplatecacheclear/forms/style.xml', false);
    }

    /**
     * Save and Save & Close on a template style (admin, Web Services API, and
     * the front-end Template Settings screen all save through the same model).
     */
    public function onExtensionAfterSave(AfterSaveEvent $event): void
    {
        // A new style (Duplicate) can't be showing on the site yet.
        if ($event->getContext() !== 'com_templates.style' || $event->getIsNew()) {
            return;
        }

        $style = $event->getItem();

        if ((int) ($style->client_id ?? -1) !== 0) {
            return;
        }

        if ($this->isSwitchedOn($style->params ?? '')) {
            $this->clearPageCache();
        }
    }

    /**
     * "Default" star in Templates: Styles. Joomla doesn't fire a save event
     * for it, only a cache clean of com_templates after the change is made.
     */
    public function onContentCleanCache(AfterCleanCacheEvent $event): void
    {
        if ($event->getDefaultGroup() !== 'com_templates' || !$this->getApplication()->isClient('administrator')) {
            return;
        }

        $input = $this->getApplication()->getInput();
        $task  = $input->getCmd('task');

        // The component dispatcher splits "styles.setDefault" into controller + task.
        if (!str_contains($task, '.')) {
            $task = $input->getCmd('controller') . '.' . $task;
        }

        if ($input->getCmd('option') !== 'com_templates'
            || !\in_array($task, ['styles.setDefault', 'styles.unsetDefault'], true)
        ) {
            return;
        }

        $ids = array_filter(ArrayHelper::toInteger((array) $input->get('cid', [], 'array')));

        if ($ids === []) {
            return;
        }

        try {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__template_styles'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->whereIn($db->quoteName('id'), array_values($ids), ParameterType::INTEGER);

            $rows = (array) $db->setQuery($query)->loadColumn();
        } catch (\Throwable $e) {
            return;
        }

        foreach ($rows as $params) {
            if ($this->isSwitchedOn((string) $params)) {
                $this->clearPageCache();

                return;
            }
        }
    }

    /**
     * Styles saved before the switch existed have no value: treat as on.
     */
    private function isSwitchedOn($params): bool
    {
        try {
            $registry = $params instanceof Registry ? $params : new Registry($params);
        } catch (\Throwable $e) {
            return true;
        }

        return (string) $registry->get(self::FIELD, '1') !== '0';
    }

    /**
     * Client id (0 = site, 1 = administrator) of the style being edited, or -1.
     */
    private function getStyleClientId($data): int
    {
        $data = (object) $data;

        if (isset($data->client_id) && $data->client_id !== '') {
            return (int) $data->client_id;
        }

        $id = (int) ($data->id ?? 0) ?: $this->getApplication()->getInput()->getInt('id');

        if ($id <= 0) {
            return -1;
        }

        try {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('client_id'))
                ->from($db->quoteName('#__template_styles'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);

            $clientId = $db->setQuery($query)->loadResult();
        } catch (\Throwable $e) {
            return -1;
        }

        return $clientId === null ? -1 : (int) $clientId;
    }

    /**
     * Empty the page cache group once per request. Site and administrator
     * share one cache folder (administrator/cache, or Global Configuration's
     * cache path), and the configured cache handler (file, Redis, Memcached,
     * APCu) is used.
     */
    private function clearPageCache(): void
    {
        if ($this->cleared) {
            return;
        }

        $this->cleared = true;
        $app           = $this->getApplication();

        try {
            $cache = $this->getCacheControllerFactory()->createCacheController('callback', [
                'defaultgroup' => 'page',
                'cachebase'    => $app->get('cache_path', JPATH_CACHE),
            ]);

            $done = (bool) $cache->clean();
        } catch (\Throwable $e) {
            $done = false;
        }

        // Only worth saying when there is a page cache to empty.
        if (!$app->isClient('administrator')
            || !(int) $this->params->get('show_message', 1)
            || !PluginHelper::isEnabled('system', 'cache')
        ) {
            return;
        }

        if ($done) {
            $app->enqueueMessage(Text::_('PLG_SYSTEM_CSTEMPLATECACHECLEAR_MSG_CLEARED'), 'message');
        } else {
            $app->enqueueMessage(Text::_('PLG_SYSTEM_CSTEMPLATECACHECLEAR_MSG_FAILED'), 'warning');
        }
    }
}
