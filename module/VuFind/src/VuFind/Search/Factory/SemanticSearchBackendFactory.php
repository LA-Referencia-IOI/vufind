<?php

/**
 * Factory for the semantic SOLR backend.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Factory;

use Psr\Container\ContainerInterface;
use VuFind\Config\ConfigManagerInterface;
use VuFindSearch\Backend\SemanticSearch\Backend;
use VuFindSearch\Backend\Solr\Connector;
use VuFind\Service\SemanticSearch\EmbeddingService;

/**
 * Factory for the semantic SOLR backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SemanticSearchBackendFactory extends AbstractSolrBackendFactory
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->searchConfig = 'searches';
        $this->searchYaml = 'searchspecs.yaml';
        $this->facetConfig = 'facets';
        $this->defaultIndexName = 'biblio';
    }

    /**
     * Create service
     *
     * @param ContainerInterface $sm      Service manager
     * @param string             $name    Requested service name
     * @param array              $options Extra options (unused)
     *
     * @return Backend
     */
    public function __invoke(ContainerInterface $sm, $name, ?array $options = null)
    {
        $this->setup($sm);
        $config = $this->getService(ConfigManagerInterface::class)->getConfigObject('embedding');
        if (isset($config->Embedding->default_core)) {
            $this->defaultIndexName = $config->Embedding->default_core;
        }
        return parent::__invoke($sm, $name, $options);
    }

    /**
     * Create the SOLR backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $config = $this->getService(ConfigManagerInterface::class)->getConfigObject('embedding');
        $semanticConfig = $config->Embedding ?? new \VuFind\Config\Config();
        $vectorField = $semanticConfig->vector_field ?? 'vector';
        $minScore = $semanticConfig->min_score ?? 0.0;
        $topK = $semanticConfig->topK ?? 10;

        $embeddingService = $this->getService(EmbeddingService::class);
        $backend = new Backend(
            $connector,
            $embeddingService,
            $vectorField,
            $minScore,
            $topK
        );

        $pageSize = $this->getIndexConfig('record_batch_size', 100);
        $maxClauses = $this->getIndexConfig('maxBooleanClauses', $pageSize);
        if ($pageSize > 0 && $maxClauses > 0) {
            $backend->setPageSize(min($pageSize, $maxClauses));
        }
        $backend->setQueryBuilder($this->createQueryBuilder());
        $backend->setSimilarBuilder($this->createSimilarBuilder());
        if ($this->logger) {
            $backend->setLogger($this->logger);
        }
        $backend->setRecordCollectionFactory($this->createRecordCollectionFactory());
        return $backend;
    }

    /**
     * Create listeners.
     *
     * @param \VuFindSearch\Backend\Solr\Backend $backend Backend
     *
     * @return void
     */
    protected function createListeners(\VuFindSearch\Backend\Solr\Backend $backend)
    {
        parent::createListeners($backend);
    }

    /**
     * Get the callback for creating a record.
     *
     * Returns a callable or null to use RecordCollectionFactory's default method.
     *
     * @return callable|null
     */
    protected function getCreateRecordCallback(): ?callable
    {
        $manager = $this->getService(\VuFind\RecordDriver\PluginManager::class);
        return function ($data) use ($manager) {
            $driver = $manager->get('SemanticSearch');
            $hl = $data['__highlight_details'] ?? [];
            unset($data['__highlight_details']);
            $driver->setRawData($data);
            $driver->setHighlightDetails($hl);
            return $driver;
        };
    }
}
