<?php

namespace VuFindSearch\Backend\SemanticSearch;

use Laminas\Http\Client as HttpClient;
use VuFindSearch\Backend\Solr\Backend as SolrBackend;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFind\Service\SemanticSearch\EmbeddingService;

use function sprintf;

/**
 * SOLR Semantic search backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Jesiel Viana <jesielviana@gmail.com>
 */
class Backend extends SolrBackend
{
    /**
     * Embedding Service.
     *
     * @var EmbeddingService
     */
    protected $embeddingService;

    /**
     * Vector field name.
     *
     * @var string
     */
    protected $vectorField;

    /**
     * Minimum score for k-NN search.
     *
     * @var float
     */
    protected $minScore;

    /**
     * Constructor.
     *
     * @param Connector        $connector        SOLR connector
     * @param EmbeddingService $embeddingService Embedding Service
     * @param string           $vectorField      Vector field name
     * @param float            $minScore         Minimum score
     */
    public function __construct(
        Connector $connector,
        EmbeddingService $embeddingService,
        $vectorField,
        $minScore
    ) {
        parent::__construct($connector);
        $this->embeddingService = $embeddingService;
        $this->vectorField = $vectorField;
        $this->minScore = $minScore;
    }

    /**
     * Perform a search and return a raw response.
     *
     * @param AbstractQuery $query  Search query
     * @param int           $offset Search offset
     * @param int           $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return string
     */
    public function rawJsonSearch(
        AbstractQuery $query,
        $offset,
        $limit,
        ?ParamBag $params = null
    ) {
        $params = $params ?: new ParamBag();
        $this->injectResponseWriter($params);

        $params->set('rows', $limit);
        $params->set('start', $offset);

        $lookFor = '';
        if ($query instanceof Query) {
            $lookFor = $query->getString();
        }

        if (empty($lookFor)) {
            return parent::rawJsonSearch($query, $offset, $limit, $params);
        }


        $startTime = microtime(true);
        $embeddingArray = $this->embeddingService->embed($lookFor);
        $this->log('debug', sprintf('SemanticSearch: Embedding retrieval time: %.4f seconds', microtime(true) - $startTime));

        if (!$embeddingArray) {
            return null;
        }

        $vectorString = '[' . implode(',', $embeddingArray) . ']';
        $semanticQuery = sprintf(
            '{!vectorSimilarity f=%s minReturn=%f}%s',
            $this->vectorField,
            $this->minScore,
            $vectorString
        );

        // Build standard parameters
        $params->mergeWith($this->getQueryBuilder()->build($query, $params));

        // If we have a semantic query, overwrite the 'q' parameter to avoid QueryBuilder escaping
        // and also clear edismax-specific parameters that might conflict with k-NN
        if ($semanticQuery) {
            $params->set('q', $semanticQuery);
            $params->remove('qf');
            $params->remove('qt');
            $params->remove('mm');

            // Ensure 'score' is in the field list (fl)
            $fl = $params->get('fl');
            if ($fl) {
                if (!str_contains(implode(',', (array)$fl), 'score')) {
                    $params->add('fl', 'score');
                }
            } else {
                $params->set('fl', '*,score');
            }
        }

        $startTime = microtime(true);
        $response = $this->connector->search($params);
        $this->log('debug', sprintf('SemanticSearch: Solr search time: %.4f seconds', microtime(true) - $startTime));

        return $response;
    }
}
