<?php
return array(
    'controllers'=>array(
        'invokables'=>array(
            'Otcpip\Controller\Otcpipn'=>'Otcpip\Controller\OtcpipnController',
        ),
    ),
    'console'=>array(
        'router'=>array(
            'routes'=>array(
                'do-actions'=>array(
                    'options'=>array(
                        'route'=>'do [--verbose|-v] <planID> <doAction>',
                        'defaults'=>array(
                            'controller'=>'Otcpip\Controller\Otcpipn',
                            'action'=>'do',
                        ),
                    ),
                ),
            ),
        ),
    ),
);