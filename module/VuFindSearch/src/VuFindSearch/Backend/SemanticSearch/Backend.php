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
     * Embedding Model.
     *
     * @var string
     */
    protected $model;

    /**
     * Encoding Format.
     *
     * @var string
     */
    protected $encodingFormat;

    /**
     * User Identifier.
     *
     * @var string
     */
    protected $user;

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
        $minScore,
        $model = 'sentence-transformers/paraphrase-multilingual-mpnet-base-v2',
        $encodingFormat = 'float',
        $user = 'user_123'
    ) {
        parent::__construct($connector);
        $this->httpClient = $httpClient;
        $this->embeddingUrl = $embeddingUrl;
        $this->vectorField = $vectorField;
        $this->topK = $topK;
        $this->minScore = $minScore;
        $this->model = $model;
        $this->encodingFormat = $encodingFormat;
        $this->user = $user;
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
            $embeddingArray = $this->getEmbedding($lookFor);
            if ($embeddingArray) {
                $vectorString = '[' . implode(',', $embeddingArray) . ']';
                $semanticQuery = sprintf(
                    '{!vectorSimilarity f=%s minReturn=%f}%s',
                    $this->vectorField,
                    $this->minScore,
                    $vectorString
                );
            }
        }

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

        return $this->connector->search($params);
    }
    /**
     * Get embedding vector for text.
     *
     * @param string $text Text to embed
     *
     * @return ?array
     */
    public function getEmbedding(string $text): ?array
    {
        try {
            $this->httpClient->setUri($this->embeddingUrl);
            $this->httpClient->setMethod('POST');
            $payload = [
                'input'           => $text,
                'model'           => $this->model,
                'encoding_format' => $this->encodingFormat,
                'user'            => $this->user
            ];
            $this->httpClient->setRawBody(json_encode($payload));
            $this->httpClient->setHeaders(['Content-Type' => 'application/json']);

            $response = $this->httpClient->send();
            if ($response->isSuccess()) {
                $data = json_decode($response->getBody(), true);
                if (!empty($data['data']) && isset($data['data'][0]['embedding'])) {
                    return $data['data'][0]['embedding'];
                }
            }
        } catch (\Exception $e) {
            $this->log('error', 'Error calling embedding API: ' . $e->getMessage());
        }
        return null;
    }
}
