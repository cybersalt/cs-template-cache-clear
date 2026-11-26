<?php

/**
 * @package     Cybersalt.Plugin
 * @subpackage  System.CsTemplateCacheClear
 *
 * @copyright   Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Cybersalt\Plugin\System\CsTemplateCacheClear\Extension\CsTemplateCacheClear;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new CsTemplateCacheClear(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('system', 'cstemplatecacheclear')
                );
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase($container->get(DatabaseInterface::class));
                $plugin->setCacheControllerFactory($container->get(CacheControllerFactoryInterface::class));

                return $plugin;
            }
        );
    }
};
