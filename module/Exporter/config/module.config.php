<?php
return array(
    'controllers'=>array(
        'invokables'=>array(
            'Exporter\Controller\Exporter'=>'Exporter\Controller\ExporterController',
        ),
    ),
    'console'=>array(
        'router'=>array(
            'routes'=>array(
                'export-actions'=>array(
                    'options'=>array(
                        'route'=>'export [--verbose|-v] <planID> <exportAction>',
                        'defaults'=>array(
                            'controller'=>'Exporter\Controller\Exporter',
                            'action'=>'export',
                        ),
                    ),
                ),
            ),
        )
    ),
);