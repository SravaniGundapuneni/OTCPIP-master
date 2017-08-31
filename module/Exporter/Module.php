<?php
namespace Exporter;

use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\Console\Adapter\AdapterInterface as Console;

class Module implements ConsoleBannerProviderInterface,ConsoleUsageProviderInterface
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
    
    /**
     * 
     * @param \Zend\Console\Adapter\AdapterInterface $console
     * @return string
     */
    public function getConsoleBanner(Console $console)
    {
        return "OTCPIP Exporter 0.0.1";
    }
    
    /**
     * 
     * @param \Zend\Console\Adapter\AdapterInterface $console
     * @return type
     */
    public function getConsoleUsage(Console $console)
    {
        return array(
            'export <planId> <entity>'=>'Export the required entity.',
            array('<planId>','Please provide the plan.'),
            array('<entity>','Export the required entity.'),
            array('--verbose','Turn on verbose mode.'),
            array('--v','Same as --verbose'),
        );
    }
}
