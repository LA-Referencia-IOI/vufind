<?php

namespace VuFindSearch\Backend\HybridSearch;

use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Backend\SemanticSearch\Backend as SemanticBackend;
use VuFindSearch\ParamBag;
use VuFind\Service\SemanticSearch\EmbeddingService;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;

use function json_encode;
use function sprintf;

/**
 * SOLR Hybrid search backend with RRF.
 *
 * @category VuFind
 * @package  Search
 * @author   Jesiel Viana <jesielviana@gmail.com>
 */
class Backend extends SemanticBackend
{
    /**
     * RRF K parameter.
     *
     * @var int
     */
    protected $rrfK;

    /**
     * Top K for vector search in hybrid mode.
     *
     * @var int
     */
    protected $topKVector;

    /**
     * Constructor.
     *
     * @param \VuFindSearch\Backend\Solr\Connector $connector  SOLR connector
     * @param \Laminas\Http\Client                 $httpClient HTTP client
     * @param string                               $embedUrl   Embedding API URL
     * @param string                               $vectorFld  Vector field name
     * @param int                                  $topK       Standard top K
     * @param float                                $minScore   Minimum score
     * @param int                                  $rrfK       RRF K parameter
     * @param int                                  $topKVector Vector top K for hybrid
     * @param string                               $model      Embedding model
     * @param string                               $encoding   Encoding format
     * @param string                               $user       User identifier
     */
    public function __construct(
        $connector,
        EmbeddingService $embeddingService,
        $vectorFld,
        $minScore,
        $rrfK = 60,
        $topKVector = 10
    ) {
        parent::__construct(
            $connector,
            $embeddingService,
            $vectorFld,
            $minScore
        );
        $this->rrfK = $rrfK;
        $this->topKVector = $topKVector;
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

        // Build lexical parameters to get correct q/filters
        $lexicalParams = $this->getQueryBuilder()->build($query, $params);
        $lexicalQ = $lexicalParams->get('q')[0] ?? '*:*';

        // Merge lexical parameters into main params (except q, rows, start which are in JSON)
        $params->mergeWith($lexicalParams);
        $params->remove('q');
        $params->remove('rows');
        $params->remove('start');

        // Ensure 'score' is in the field list (fl)
        $fl = $params->get('fl');
        if ($fl) {
            $flArray = explode(',', implode(',', (array)$fl));
            if (!in_array('score', $flArray)) {
                $params->add('fl', 'score');
            }
        } else {
            $params->set('fl', '*,score');
        }
        $finalFl = implode(',', (array)$params->get('fl'));

        // Construct Combined Query DSL
        $vectorString = '[' . implode(',', $embeddingArray) . ']';
        $combinedQuery = [
            'queries' => [
                'lexical' => [
                    'lucene' => [
                        'query' => $lexicalQ,
                    ],
                ],
                'vector' => [
                    'knn' => [
                        'f'     => $this->vectorField,
                        'topK'  => $this->topKVector,
                        'query' => $vectorString,
                    ],
                ],
            ],
            'limit'  => $limit,
            'offset' => $offset,
            'fields' => $finalFl,
            'params' => [
                'combiner'           => true,
                'combiner.query'     => ['lexical', 'vector'],
                'combiner.algorithm' => 'rrf',
                'combiner.rrf.k'     => $this->rrfK,
            ],
        ];


        // Enable highlighting
        $params->set('hl', 'true');
        $params->set('hl.q', $lexicalQ);


        // Add filters from original params if present
        $fq = $params->get('fq');
        if ($fq) {
            $combinedQuery['filter'] = $fq;
        }

        $startTime = microtime(true);
        $response = $this->connector->postJson('combined', json_encode($combinedQuery), $params);
        $this->log('debug', sprintf('HybridSearch: Solr combined search time: %.4f seconds', microtime(true) - $startTime));

        return $response;
    }
}
