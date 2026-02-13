<?php

/**
 * Embedding Service.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Service
 * @author   Jesiel Viana <jesielviana@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Service\SemanticSearch;

use Laminas\Http\Client as HttpClient;
use VuFind\Log\LoggerAwareInterface;

/**
 * Embedding Service.
 *
 * @category VuFind
 * @package  Service
 * @author   Jesiel Viana <jesielviana@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EmbeddingService implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

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
     * @param HttpClient $httpClient     HTTP client
     * @param string     $embeddingUrl   Embedding API URL
     * @param string     $model          Embedding Model
     * @param string     $encodingFormat Encoding Format
     * @param string     $user           User Identifier
     */
    public function __construct(
        HttpClient $httpClient,
        string $embeddingUrl,
        string $model,
        string $encodingFormat,
        string $user
    ) {
        $this->httpClient = $httpClient;
        $this->embeddingUrl = $embeddingUrl;
        $this->model = $model;
        $this->encodingFormat = $encodingFormat;
        $this->user = $user;
    }

    /**
     * Get embedding vector for text.
     *
     * @param string $text Text to embed
     *
     * @return ?array
     */
    public function embed(string $text): ?array
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
