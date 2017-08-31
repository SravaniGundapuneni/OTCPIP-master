<?php
namespace Otcpip;

use Zend\ModuleManager\Feature\ConsoleBannerProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\Console\Adapter\AdapterInterface as Console;

class Module implements ConsoleBannerProviderInterface,ConsoleUsageProviderInterface
{
    /**
     * 
     * @return type
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * 
     * @return type
     */
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
        return "OTCPIP 0.0.1";
    }
    
    /**
     * 
     * @param \Zend\Console\Adapter\AdapterInterface $console
     * @return type
     */
    public function getConsoleUsage(Console $console)
    {
        return array(
            'do <planId> <action>'=>'Do the required action.',
            array('<planId>','Please provide the plan.'),
            array('<action>','Do the required action.'),
            array('--verbose','Turn on verbose mode.'),
            array('--v','Same as --verbose'),
        );
    }
}
