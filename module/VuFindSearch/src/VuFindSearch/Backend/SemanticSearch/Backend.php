<?php

namespace VuFindSearch\Backend\SemanticSearch;

use VuFindSearch\Backend\Exception\BackendException;
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
     * Top K results for k-NN search.
     *
     * @var int
     */
    protected $topK;

    /**
     * Query parser type.
     *
     * @var string
     */
    protected $queryParser;

    /**
     * Whether vector field is multivalued/nested.
     *
     * @var bool
     */
    protected $vectorMultivalued;

    /**
     * Constructor.
     *
     * @param Connector        $connector        SOLR connector
     * @param EmbeddingService $embeddingService Embedding Service
     * @param string           $vectorField      Vector field name
     * @param float            $minScore         Minimum score
     * @param int              $topK             Top K results
     * @param string           $queryParser      Query parser type
     * @param bool             $vectorMultivalued Whether vector field is multivalued
     */
    public function __construct(
        Connector $connector,
        EmbeddingService $embeddingService,
        $vectorField,
        $minScore,
        $topK = 10,
        $queryParser = 'knn',
        $vectorMultivalued = true
    ) {
        parent::__construct($connector);
        $this->embeddingService = $embeddingService;
        $this->vectorField = $vectorField;
        $this->minScore = $minScore;
        $this->topK = $topK;
        $this->queryParser = $queryParser;
        $this->vectorMultivalued = (bool)$vectorMultivalued;
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


        $embeddingArray = $this->embeddingService->embed($lookFor);

        if (!$embeddingArray) {
            throw new BackendException('Problem connecting to Embedding API.');
        }


        $vectorString = '[' . implode(',', $embeddingArray) . ']';

        $semanticQuery = $this->buildSemanticQuery($params, $vectorString);

        // Build standard parameters
        $params->mergeWith($this->getQueryBuilder()->build($query, $params));

        // If we have a semantic query, overwrite the 'q' parameter to avoid QueryBuilder escaping
        // and also clear edismax-specific parameters that might conflict with k-NN
        if ($semanticQuery) {
            $params->set('q', $semanticQuery);
            if ($this->queryParser !== 'vectorSimilarity') {
                $params->add('fq', '{!frange l=' . $this->minScore . '}query($q)');
            }
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

        // Enable highlighting
        $params->set('hl', 'true');
        $params->set('hl.q', $lookFor);

        $startTime = microtime(true);
        $response = $this->connector->search($params);
        $this->log('debug', sprintf('SemanticSearch: Solr search time: %.4f seconds', microtime(true) - $startTime));

        return $response;
    }

    /**
     * Build semantic query based on vector field cardinality.
     *
     * @param ParamBag $params       Search params
     * @param string   $vectorString Vector literal for Solr parsers
     *
     * @return string
     */
    protected function buildSemanticQuery(ParamBag $params, string $vectorString): string
    {
        if ($this->vectorMultivalued) {
            if ($this->queryParser === 'vectorSimilarity') {
                $params->set(
                    'children.q',
                    sprintf(
                        '{!vectorSimilarity f=%s minReturn=%f}%s',
                        $this->vectorField,
                        $this->minScore,
                        $vectorString
                    )
                );
            } else {
                $params->set(
                    'children.q',
                    sprintf(
                        '{!knn f=%s topK=%d childrenOf=$allParents}%s',
                        $this->vectorField,
                        $this->topK,
                        $vectorString
                    )
                );
            }

            $params->set('allParents', '*:* -_nest_path_:*');
            return '{!parent which=$allParents score=max v=$children.q}';
        }

        if ($this->queryParser === 'vectorSimilarity') {
            return sprintf(
                '{!vectorSimilarity f=%s minReturn=%f}%s',
                $this->vectorField,
                $this->minScore,
                $vectorString
            );
        }

        return sprintf(
            '{!knn f=%s topK=%d}%s',
            $this->vectorField,
            $this->topK,
            $vectorString
        );
    }
}
