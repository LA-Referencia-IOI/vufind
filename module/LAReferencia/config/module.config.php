<?php

namespace LAReferencia\Module\Configuration;

use LAReferencia\RecordDataFormatter\Specs\DefaultRecord as LAReferenciaDefaultRecordSpec;
use LAReferencia\RecordDriver\DefaultRecord as LAReferenciaDefaultRecordDriver;
use VuFind\RecordDataFormatter\Specs\DefaultRecord as VuFindDefaultRecord;
use VuFind\RecordDataFormatter\Specs\DefaultRecordFactory;
use VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory;

$config = [
    'controllers' => [
        'factories' => [
            'LAReferencia\\Controller\\BulkExportController' =>
                'VuFind\\Controller\\AbstractBaseFactory',
        ],
        'aliases' => [
            'BulkExport' => 'LAReferencia\\Controller\\BulkExportController',
            'bulkexport' => 'LAReferencia\\Controller\\BulkExportController',
        ],
    ],
    'router' => [
        'routes' => [
            'bulkexport-home' => [
                'type' => 'Laminas\\Router\\Http\\Literal',
                'options' => [
                    'route' => '/bulkexport/home',
                    'defaults' => [
                        'controller' => 'BulkExport',
                        'action' => 'Home',
                    ],
                ],
            ],
            'bulkexport-csv' => [
                'type' => 'Laminas\\Router\\Http\\Literal',
                'options' => [
                    'route' => '/bulkexport/csv',
                    'defaults' => [
                        'controller' => 'BulkExport',
                        'action' => 'CSV',
                    ],
                ],
            ],
            'bulkexport-download' => [
                'type' => 'Laminas\\Router\\Http\\Literal',
                'options' => [
                    'route' => '/bulkexport/download',
                    'defaults' => [
                        'controller' => 'BulkExport',
                        'action' => 'Download',
                    ],
                ],
            ],
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'recorddataformatter_specs' => [
                'aliases' => [
                    VuFindDefaultRecord::class => LAReferenciaDefaultRecordSpec::class,
                ],
                'factories' => [
                    LAReferenciaDefaultRecordSpec::class => DefaultRecordFactory::class,
                ],
            ],
            'recorddriver' => [
                'aliases' => [
                    'solrdefault' => LAReferenciaDefaultRecordDriver::class,
                ],
                'factories' => [
                    LAReferenciaDefaultRecordDriver::class =>
                        SolrDefaultWithoutSearchServiceFactory::class,
                ],
            ],
        ],
    ],
];

return $config;
