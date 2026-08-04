<?php

namespace LAReferencia\Module\Configuration;

use LAReferencia\RecordDataFormatter\Specs\DefaultRecord as LAReferenciaDefaultRecordSpec;
use LAReferencia\RecordDriver\HybridSearch as LAReferenciaHybridSearchDriver;
use LAReferencia\RecordDriver\LAReferenciaSolrDefault;
use LAReferencia\RecordDriver\SemanticSearch as LAReferenciaSemanticSearchDriver;
use VuFind\RecordDataFormatter\Specs\DefaultRecord as VuFindDefaultRecord;
use VuFind\RecordDataFormatter\Specs\DefaultRecordFactory;
use VuFind\RecordDriver\HybridSearchFactory;
use VuFind\RecordDriver\SemanticSearchFactory;
use VuFind\RecordDriver\SolrDefaultFactory;

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
                    'solrdefault' => LAReferenciaSolrDefault::class,
                    'semanticsearch' => LAReferenciaSemanticSearchDriver::class,
                    'hybridsearch' => LAReferenciaHybridSearchDriver::class,
                ],
                'factories' => [
                    LAReferenciaSolrDefault::class =>
                        SolrDefaultFactory::class,
                    LAReferenciaSemanticSearchDriver::class =>
                        SemanticSearchFactory::class,
                    LAReferenciaHybridSearchDriver::class =>
                        HybridSearchFactory::class,
                ],
            ],
        ],
    ],
];

return $config;
