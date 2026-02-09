<?php

namespace VuFindSearch\Backend\SemanticSearch;

use Laminas\Http\Client as HttpClient;
use VuFindSearch\Backend\Solr\Backend as SolrBackend;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;

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
     * HTTP client for embedding API.
     *
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * Embedding API URL.
     *
     * @var string
     */
    protected $embeddingUrl;

    /**
     * Vector field name.
     *
     * @var string
     */
    protected $vectorField;

    /**
     * Top K results for k-NN search.
     *
     * @var int
     */
    protected $topK;

    /**
     * Minimum score for k-NN search.
     *
     * @var float
     */
    protected $minScore;

    /**
     * Constructor.
     *
     * @param Connector  $connector    SOLR connector
     * @param HttpClient $httpClient   HTTP client
     * @param string     $embeddingUrl Embedding API URL
     * @param string     $vectorField  Vector field name
     * @param int        $topK         Top K results
     */
    public function __construct(
        Connector $connector,
        HttpClient $httpClient,
        $embeddingUrl,
        $vectorField,
        $topK,
        $minScore
    ) {
        parent::__construct($connector);
        $this->httpClient = $httpClient;
        $this->embeddingUrl = $embeddingUrl;
        $this->vectorField = $vectorField;
        $this->topK = $topK;
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

        $semanticQuery = null;
        if (!empty($lookFor) && !str_starts_with($lookFor, '{!knn')) {
            try {
                $this->httpClient->setUri($this->embeddingUrl);
                $this->httpClient->setMethod('POST');
                $this->httpClient->setRawBody(json_encode(['text' => $lookFor]));
                $this->httpClient->setHeaders(['Content-Type' => 'application/json']);

                $response = $this->httpClient->send();
                if ($response->isSuccess()) {
                    $data = json_decode($response->getBody(), true);
                    if (isset($data['embedding'])) {
                        $vectorString = '[' . implode(',', $data['embedding']) . ']';
                        $semanticQuery = sprintf(
                            '{!knn f=%s topK=%d}%s',
                            $this->vectorField,
                            (int)($this->topK ?? 10),
                            $vectorString
                        );
                    }
                }
            } catch (\Exception $e) {
                $this->log('error', 'Error calling embedding API: ' . $e->getMessage());
            }
        }

        // Build standard parameters
        $params->mergeWith($this->getQueryBuilder()->build($query, $params));

        // If we have a semantic query, overwrite the 'q' parameter to avoid QueryBuilder escaping
        // and also clear edismax-specific parameters that might conflict with k-NN
        if ($semanticQuery) {
            $params->set('q', $semanticQuery);
            $params->add('fq', '{!frange l=' . $this->minScore . '}query($q)');
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

        return $this->connector->search($params);
    }
}
