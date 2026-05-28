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

use Psr\Log\LoggerAwareInterface;
use Laminas\Http\Client as HttpClient;

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
     * Embedding API key.
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Optional site URL (sent as HTTP-Referer for compatible providers).
     *
     * @var string
     */
    protected $siteUrl;

    /**
     * Optional application name (sent as X-Title for compatible providers).
     *
     * @var string
     */
    protected $appName;

    /**
     * Constructor.
     *
     * @param HttpClient $httpClient     HTTP client
     * @param string     $embeddingUrl   Embedding API URL
     * @param string     $model          Embedding Model
     * @param string     $encodingFormat Encoding Format
     * @param string     $apiKey         Embedding API key
     * @param string     $siteUrl        Optional provider site URL
     * @param string     $appName        Optional provider application name
     */
    public function __construct(
        HttpClient $httpClient,
        string $embeddingUrl,
        string $model,
        string $encodingFormat,
        string $apiKey,
        string $siteUrl,
        string $appName
    ) {
        $this->httpClient = $httpClient;
        $this->embeddingUrl = $embeddingUrl;
        $this->model = $model;
        $this->encodingFormat = $encodingFormat;
        $this->apiKey = $apiKey;
        $this->siteUrl = $siteUrl;
        $this->appName = $appName;
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
        $startTime = microtime(true);
        try {
            $normalizedText = $this->normalizeText($text);
            $this->httpClient->setUri($this->embeddingUrl);
            $this->httpClient->setMethod('POST');
            $payload = [
                'input'           => $normalizedText,
                'model'           => $this->model,
                'encoding_format' => $this->encodingFormat,
            ];
            $this->httpClient->setRawBody(json_encode($payload));
            $headers = ['Content-Type' => 'application/json'];
            if (!empty($this->apiKey)) {
                $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            }
            if (!empty($this->siteUrl)) {
                $headers['HTTP-Referer'] = $this->siteUrl;
            }
            if (!empty($this->appName)) {
                $headers['X-Title'] = $this->appName;
            }
            $this->httpClient->setHeaders($headers);

            $response = $this->httpClient->send();
            $this->log(
                'debug',
                sprintf(
                    'Embedding retrieval time: %.4f seconds [url=%s]',
                    microtime(true) - $startTime,
                    $this->embeddingUrl
                )
            );
            if ($response->isSuccess()) {
                $data = json_decode($response->getBody(), true);
                if (!empty($data['data']) && isset($data['data'][0]['embedding'])) {
                    return $data['data'][0]['embedding'];
                }
                $this->log('error', 'Unexpected embedding API response format.');
            } else {
                $this->log(
                    'error',
                    'Embedding API request failed with status code: ' . $response->getStatusCode()
                );
            }
        } catch (\Exception $e) {
            $this->log('error', 'Error calling embedding API: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Normalize text before sending to the embedding API.
     *
     * @param string $input Input text
     *
     * @return string
     */
    private function normalizeText(string $input): string
    {
        if ('' === trim($input)) {
            return '';
        }

        $normalized = trim($input);

        // Join words split by hyphen + line break (e.g. multi-\nlingual).
        $normalized = preg_replace('/-\h*\R\h*/u', '', $normalized) ?? $normalized;

        // Lowercase with UTF-8 support.
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($normalized, 'UTF-8')
            : strtolower($normalized);

        // Replace remaining line breaks with spaces.
        $normalized = preg_replace('/\R+/u', ' ', $normalized) ?? $normalized;

        // Collapse multiple spaces/tabs/newlines.
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        // Unicode normalization (canonical representation), when intl is available.
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($normalized, \Normalizer::FORM_C) ?? $normalized;
        }

        return trim($normalized);
    }
}
