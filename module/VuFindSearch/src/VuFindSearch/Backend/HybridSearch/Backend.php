<?php

namespace VuFindSearch\Backend\HybridSearch;

use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Backend\Solr\Backend as SolrBackend;
use VuFindSearch\ParamBag;
use VuFind\Service\SemanticSearch\EmbeddingService;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;

use function count;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function max;
use function sprintf;

/**
 * SOLR Hybrid search backend with RRF.
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
     * @param \VuFindSearch\Backend\Solr\Connector $connector  SOLR connector
     * @param \Laminas\Http\Client                 $httpClient HTTP client
     * @param string                               $embedUrl   Embedding API URL
     * @param string                               $vectorFld  Vector field name
     * @param int                                  $topK       Standard top K
     * @param float                                $minScore   Minimum score
     * @param int                                  $rrfK       RRF K parameter
     * @param int                                  $topK       Top K for hybrid
     * @param bool                                 $vectorMultivalued Whether vector field is multivalued
     * @param string                               $model      Embedding model
     * @param string                               $encoding   Encoding format
     * @param string                               $user       User identifier
     */
    public function __construct(
        $connector,
        EmbeddingService $embeddingService,
        $vectorFld,
        $minScore,
        $rrfK,
        $topK,
        $vectorMultivalued
    ) {
        parent::__construct($connector);
        $this->embeddingService = $embeddingService;
        $this->vectorField = $vectorFld;
        $this->minScore = $minScore;
        $this->rrfK = $rrfK;
        $this->topK = $topK;
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

        // Solr /combined beta can return unstable numFound values depending on
        // limit/window. Use a lexical rows=0 count as stable lower bound.
        $lexicalCount = $this->getLexicalCount($lexicalQ, $fq, $allParents);
        // Probe a wider hybrid window to capture additional fused docs when
        // /combined reports numFound tied to the request limit.
        $hybridCount = $this->getHybridCountBound($combinedQuery, $params, (int)$limit);

        // Defensive normalization: keep response metadata consistent with the
        // returned docs so VuFind counters and rendered list stay in sync.
        $response = $this->normalizeCombinedResponse(
            $response,
            (int)$offset,
            (int)$limit,
            $lexicalCount,
            $hybridCount
        );

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
                            'f'          => $this->vectorField,
                            'topK'       => $this->topK,
                            'query'      => $vectorString,
                            'childrenOf' => $allParents,
                        ],
                    ],
                ],
            ];
        }

        return [
            'knn' => [
                'f'     => $this->vectorField,
                'topK'  => $this->topK,
                'query' => $vectorString,
            ],
        ];
    }

    /**
     * Normalize /combined response consistency.
     *
     * Some Solr beta responses may return a docs array bigger than the page
     * limit and/or a numFound lower than visible docs. This causes VuFind to
     * show contradictory counters versus rendered records.
     *
     * @param string   $response     Raw Solr JSON response
     * @param int      $offset       Requested offset
     * @param int      $limit        Requested limit
     * @param int|null $lexicalCount Optional lexical rows=0 total
     * @param int|null $hybridCount  Optional wider-window hybrid total bound
     *
     * @return string
     */
    protected function normalizeCombinedResponse(
        string $response,
        int $offset,
        int $limit,
        ?int $lexicalCount = null,
        ?int $hybridCount = null
    ): string {
        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['response']) || !is_array($data['response'])) {
            return $response;
        }

        $docs = $data['response']['docs'] ?? null;
        if (!is_array($docs)) {
            return $response;
        }

        if ($limit > 0 && count($docs) > $limit) {
            $data['response']['docs'] = array_slice($docs, 0, $limit);
            $docs = $data['response']['docs'];
        }

        $visibleCount = $offset + count($docs);
        $numFound = (int)($data['response']['numFound'] ?? 0);
        $data['response']['numFound'] = max(
            $numFound,
            $visibleCount,
            (int)$lexicalCount,
            (int)$hybridCount
        );
        $data['response']['start'] = $offset;

        $normalized = json_encode($data);
        return false === $normalized ? $response : $normalized;
    }

    /**
     * Get stable lexical total for current query/filter context.
     *
     * @param string     $lexicalQ   Lexical query string
     * @param array|null $fq         Filter query values
     * @param string     $allParents Parent selector used for nested vectors
     *
     * @return int|null
     */
    protected function getLexicalCount(string $lexicalQ, ?array $fq, string $allParents): ?int
    {
        $countParams = new ParamBag();
        $this->injectResponseWriter($countParams);
        $countParams->set('q', $lexicalQ);
        $countParams->set('rows', 0);
        $countParams->set('start', 0);

        if ($fq) {
            $countParams->set('fq', $fq);
        }
        if ($this->vectorMultivalued) {
            $fqValues = $countParams->get('fq') ?? [];
            if (!in_array($allParents, $fqValues, true)) {
                $countParams->add('fq', $allParents);
            }
        }

        $countResponse = $this->connector->search($countParams);
        $countData = json_decode($countResponse, true);
        if (!is_array($countData) || !isset($countData['response']['numFound'])) {
            return null;
        }
        return (int)$countData['response']['numFound'];
    }

    /**
     * Get a hybrid total lower bound by probing /combined with a wider limit.
     *
     * @param array    $combinedQuery Combined DSL payload
     * @param ParamBag $params        Original request params
     * @param int      $limit         Requested limit
     *
     * @return int|null
     */
    protected function getHybridCountBound(array $combinedQuery, ParamBag $params, int $limit): ?int
    {
        $probeQuery = $combinedQuery;
        $probeQuery['offset'] = 0;
        $probeQuery['limit'] = max($limit, 100);
        $probeQuery['fields'] = 'id';

        $probeParams = new ParamBag($params->getArrayCopy());
        $probeParams->set('hl', 'false');

        $probeResponse = $this->connector->postJson('combined', json_encode($probeQuery), $probeParams);
        $probeData = json_decode($probeResponse, true);
        if (!is_array($probeData) || !isset($probeData['response']) || !is_array($probeData['response'])) {
            return null;
        }

        $probeDocs = $probeData['response']['docs'] ?? null;
        $docsCount = is_array($probeDocs) ? count($probeDocs) : 0;
        $numFound = (int)($probeData['response']['numFound'] ?? 0);
        return max($docsCount, $numFound);
    }
}
