<?php

/** 
 *  @copyright  Copyright (c) 2014 Navarro Discount Pharmacy (http://www.navarro.com)
 *  @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */

namespace Otcpip\Service;

use Zend\ServiceManager\ConfigInterface;
use Zend\ServiceManager\ServiceManager;

/**
 * Description of ServiceManagerConfig
 *
 * @author MSCS C. Gabriel Varela S. <cvarela@navarro.com>
 */
class ServiceManagerConfig implements ConfigInterface
{
    /**
     * 
     * @param \Zend\ServiceManager\ServiceManager $serviceManager
     */
    public function configureServiceManager(ServiceManager $serviceManager) {
        $serviceManager->addInitializer(function($instance) use ($serviceManager){
            if($instance instanceof EventManagerAwareInterface){
                $instance->getEventManager()->setSharedManager(
                            $serviceManager->get('SharedEventManager')
                        );
            }
            else
            {
                $instance->setEventManager($serviceManager->get('EventManager'));
            }
        });
        
        $serviceManager->addInitializer(function ($instance) use ($serviceManager){
            if($instance instanceof ServiceManagerAwareInterface)
            {
                $instance->setServiceManager($serviceManager);
            }
        });
        
        $serviceManager->addInitializer(function($instance) use ($serviceManager){
            if($instance instanceof ServiceLocatorAwareInterface)
            {
                $instance->setServiceLocator($serviceManager);
            }
        });
        
        $serviceManager->setService('ServiceManager', $serviceManager);
        $serviceManager->setAlias('Zend\ServiceManager\ServiceLocatorInterface', 'ServiceManager');
        $serviceManager->setAlias('Zend\ServiceManager\ServiceManager', 'ServiceManager');
    }
}
