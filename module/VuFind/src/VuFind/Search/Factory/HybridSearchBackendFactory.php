<?php

namespace VuFind\Search\Factory;

use Psr\Container\ContainerInterface;
use VuFind\Config\ConfigManagerInterface;
use VuFindSearch\Backend\HybridSearch\Backend;
use VuFindSearch\Backend\Solr\Connector;

/**
 * Factory for the Hybrid SOLR backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Jesiel Viana <jesielviana@gmail.com>
 */
class HybridSearchBackendFactory extends AbstractSolrBackendFactory
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
        $hybridConfig = $config->Embedding ?? new \VuFind\Config\Config();
        $embeddingUrl = $hybridConfig->embedding_api_url ?? 'http://localhost:8000/embed';
        $vectorField = $hybridConfig->vector_field ?? 'vector';
        $topK = $hybridConfig->topK ?? 10;
        $minScore = $hybridConfig->min_score ?? 0.7;
        $rrfK = $hybridConfig->rrf_k ?? 60;
        $topKVector = $hybridConfig->topK_vector ?? 10;
        $model = $hybridConfig->model ?? 'sentence-transformers/paraphrase-multilingual-mpnet-base-v2';
        $encodingFormat = $hybridConfig->encoding_format ?? 'float';
        $user = $hybridConfig->user ?? 'user_123';

        $httpClient = $this->getService('VuFindHttp\HttpService')->createClient();

        $backend = new Backend(
            $connector,
            $httpClient,
            $embeddingUrl,
            $vectorField,
            $topK,
            $minScore,
            $rrfK,
            $topKVector,
            $model,
            $encodingFormat,
            $user
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
            $driver = $manager->get('HybridSearch');
            $driver->setRawData($data);
            return $driver;
        };
    }
}
