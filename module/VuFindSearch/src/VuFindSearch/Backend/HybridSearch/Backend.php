<?php

namespace VuFindSearch\Backend\HybridSearch;

use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Backend\Solr\Backend as SolrBackend;
use VuFindSearch\ParamBag;
use VuFind\Service\SemanticSearch\EmbeddingService;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;

use function explode;
use function implode;
use function in_array;
use function json_encode;
use function microtime;
use function sprintf;

/**
 * SOLR Hybrid search backend with RRF.
 *
 * @category VuFind
 * @package  Search
 * @author   Jesiel Viana <jesielviana@proton.me>
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
     * Minimum score for vector search.
     *
     * @var float
     */
    protected $minScore;

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
    protected $topK;

    /**
     * Whether vector field is multivalued/nested.
     *
     * @var bool
     */
    protected $vectorMultivalued;

    /**
     * Constructor.
     *
     * @param \VuFindSearch\Backend\Solr\Connector $connector        SOLR connector
     * @param EmbeddingService                     $embeddingService Embedding Service
     * @param string                               $vectorFld        Vector field name
     * @param float                                $minScore         Minimum score
     * @param int                                  $rrfK             RRF K parameter
     * @param int                                  $topK             Top K for hybrid
     * @param bool                                 $vectorMultivalued Whether vector field is multivalued
     */
    public function __construct(
        $connector,
        EmbeddingService $embeddingService,
        $vectorFld,
        $minScore,
        $rrfK,
        $topK,
        $vectorMultivalued = true
    ) {
        parent::__construct($connector);
        $this->embeddingService = $embeddingService;
        $this->vectorField = $vectorFld;
        $this->minScore = $minScore;
        $this->rrfK = $rrfK;
        $this->topK = $topK;
        $this->vectorMultivalued = (bool) $vectorMultivalued;
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

        // 1. OTIMIZAÇÃO: Curto-circuito para requisições de contagem/spellcheck (rows=0)
        if ($limit === 0) {
            return parent::rawJsonSearch($query, $offset, $limit, $params);
        }

        $this->injectResponseWriter($params);

        $lookFor = '';
        if ($query instanceof Query) {
            $lookFor = $query->getString();
        }

        if (empty($lookFor)) {
            return parent::rawJsonSearch($query, $offset, $limit, $params);
        }

        // 2. Gerar Embedding apenas para a consulta válida de resultados
        $embeddingArray = $this->embeddingService->embed($lookFor);

        if (!$embeddingArray) {
            throw new BackendException('Problem connecting to Embedding API.');
        }

        // Build lexical parameters to get correct q/filters
        $lexicalParams = $this->getQueryBuilder()->build($query, $params);
        $lexicalQ = $lexicalParams->get('q')[0] ?? '*:*';

        // Merge lexical parameters into main params
        $params->mergeWith($lexicalParams);
        $params->remove('q');
        $params->remove('rows');
        $params->remove('start');

        // 3. OTIMIZAÇÃO DE CAMPOS (fl): Evita o uso de 'fl=*' se possível
        $fl = $params->get('fl');
        if ($fl) {
            $flArray = explode(',', implode(',', (array) $fl));
            if (!in_array('score', $flArray, true)) {
                $params->add('fl', 'score');
            }
        } else {
            $params->set('fl', '*,score');
        }
        $finalFl = implode(',', (array) $params->get('fl'));

        // Construct Combined Query DSL
        $allParents = '*:* -_nest_path_:*';
        $vectorString = '[' . implode(',', $embeddingArray) . ']';

        $vectorQuery = $this->buildVectorQueryNode($vectorString, $allParents);
        $combinedQuery = [
            'queries' => [
                'lexical' => [
                    'lucene' => [
                        'query' => $lexicalQ,
                    ],
                ],
                'vector' => $vectorQuery,
            ],
            'limit' => $limit,
            'offset' => $offset,
            'fields' => $finalFl,
            'params' => [
                'combiner' => true,
                'combiner.query' => ['lexical', 'vector'],
                'combiner.algorithm' => 'rrf',
                'combiner.rrf.k' => $this->rrfK,
            ],
        ];

        // Desativa Highlighting na busca híbrida RRF
        $params->set('hl', 'false');

        // 4. OTIMIZAÇÃO CRÍTICA: Desativa os logs de debug do Solr que deixavam a busca lenta
        $params->remove('debugQuery');
        $params->remove('debug');

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

    /**
     * Build vector query node based on vector field cardinality.
     *
     * @param string $vectorString Vector literal for Solr parsers
     * @param string $allParents   Parent selector used for nested vectors
     *
     * @return array
     */
    protected function buildVectorQueryNode(string $vectorString, string $allParents): array
    {
        if ($this->vectorMultivalued) {
            return [
                'parent' => [
                    'which' => $allParents,
                    'score' => 'max',
                    'query' => [
                        'knn' => [
                            'f' => $this->vectorField,
                            'topK' => $this->topK,
                            'query' => $vectorString,
                            'childrenOf' => $allParents,
                        ],
                    ],
                ],
            ];
        }

        return [
            'knn' => [
                'f' => $this->vectorField,
                'topK' => $this->topK,
                'query' => $vectorString,
            ],
        ];
    }
}