<?php

/**
 * Factory for Embedding Service.
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

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use VuFind\Config\ConfigManagerInterface;

/**
 * Factory for Embedding Service.
 *
 * @category VuFind
 * @package  Service
 * @author   Jesiel Viana <jesielviana@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EmbeddingServiceFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get(ConfigManagerInterface::class)->getConfigObject('embedding');
        $semanticConfig = $config->Embedding ?? new \VuFind\Config\Config();

        $embeddingUrl = $semanticConfig->embedding_api_url ?? 'https://openrouter.ai/api/v1/embeddings';
        $model = $semanticConfig->model ?? 'openai/text-embedding-3-small';
        $encodingFormat = $semanticConfig->encoding_format ?? 'float';
        $user = $semanticConfig->user ?? 'user_123';
        $apiKey = $semanticConfig->embedding_api_key
            ?? getenv('EMBEDDING_API_KEY')
            ?: $semanticConfig->openrouter_api_key
            ?? getenv('OPENROUTER_API_KEY')
            ?: getenv('OPENAI_API_KEY')
            ?: '';
        $siteUrl = $semanticConfig->embedding_site_url
            ?? getenv('EMBEDDING_SITE_URL')
            ?: $semanticConfig->openrouter_site_url
            ?? getenv('OPENROUTER_SITE_URL')
            ?: '';
        $appName = $semanticConfig->embedding_app_name
            ?? getenv('EMBEDDING_APP_NAME')
            ?: $semanticConfig->openrouter_app_name
            ?? getenv('OPENROUTER_APP_NAME')
            ?: '';

        $httpClient = $container->get('VuFindHttp\HttpService')->createClient();

        return new EmbeddingService(
            $httpClient,
            $embeddingUrl,
            $model,
            $encodingFormat,
            $user,
            $apiKey,
            $siteUrl,
            $appName
        );
    }
}
