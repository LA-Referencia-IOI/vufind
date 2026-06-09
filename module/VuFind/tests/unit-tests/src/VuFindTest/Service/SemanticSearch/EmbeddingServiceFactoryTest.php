<?php

/**
 * EmbeddingServiceFactory Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFind\Log {
    if (!interface_exists(LoggerAwareInterface::class)) {
        interface LoggerAwareInterface extends \Psr\Log\LoggerAwareInterface {}
    }
}

namespace VuFindTest\Service\SemanticSearch {

    use Laminas\Http\Client as HttpClient;
    use VuFind\Config\Config;
    use VuFind\Config\ConfigManagerInterface;
    use VuFind\Service\SemanticSearch\EmbeddingService;
    use VuFind\Service\SemanticSearch\EmbeddingServiceFactory;

    /**
     * EmbeddingServiceFactory Test Class
     *
     * @category VuFind
     * @package  Tests
     */
    class EmbeddingServiceFactoryTest extends \PHPUnit\Framework\TestCase
    {
        use \VuFindTest\Feature\ReflectionTrait;

        /**
         * Test factory with configured values.
         *
         * @return void
         */
        public function testFactoryWithConfigValues(): void
        {
            $container = new \VuFindTest\Container\MockContainer($this);
            $httpClient = $this->createMock(HttpClient::class);

            $configManager = $container->get(ConfigManagerInterface::class);
            $configManager->method('getConfigObject')->with('embedding')
                ->willReturn(
                    new Config(
                        [
                            'Embedding' => [
                                'embedding_api_url' => 'http://embed/custom',
                                'model' => 'model-z',
                                'dimensions' => 3,
                                'encoding_format' => 'base64',
                                'embedding_api_key' => 'k777',
                                'embedding_site_url' => 'https://example.org',
                                'embedding_app_name' => 'my-app',
                            ],
                        ]
                    )
                );

            $httpService = $container->get('VuFindHttp\HttpService');
            $httpService->expects($this->once())->method('createClient')->willReturn($httpClient);

            $factory = new EmbeddingServiceFactory();
            $service = $factory($container, EmbeddingService::class);

            $this->assertInstanceOf(EmbeddingService::class, $service);
            $this->assertSame($httpClient, $this->getProperty($service, 'httpClient'));
            $this->assertEquals('http://embed/custom', $this->getProperty($service, 'embeddingUrl'));
            $this->assertEquals('model-z', $this->getProperty($service, 'model'));
            $this->assertEquals('base64', $this->getProperty($service, 'encodingFormat'));
            $this->assertEquals('k777', $this->getProperty($service, 'apiKey'));
            $this->assertEquals('https://example.org', $this->getProperty($service, 'siteUrl'));
            $this->assertEquals('my-app', $this->getProperty($service, 'appName'));
        }

        /**
         * Test factory defaults for optional values.
         *
         * @return void
         */
        public function testFactoryDefaults(): void
        {
            $container = new \VuFindTest\Container\MockContainer($this);
            $httpClient = $this->createMock(HttpClient::class);

            $container->get(ConfigManagerInterface::class)
                ->method('getConfigObject')
                ->willReturn(
                    new Config(
                        [
                            'Embedding' => [
                                'embedding_api_url' => 'http://embed/default',
                                'model' => 'model-default',
                                'embedding_api_key' => '',
                                'embedding_site_url' => '',
                                'embedding_app_name' => '',
                            ],
                        ]
                    )
                );
            $container->get('VuFindHttp\HttpService')
                ->method('createClient')
                ->willReturn($httpClient);

            $factory = new EmbeddingServiceFactory();
            $service = $factory($container, EmbeddingService::class);

            $this->assertEquals('http://embed/default', $this->getProperty($service, 'embeddingUrl'));
            $this->assertEquals('model-default', $this->getProperty($service, 'model'));
            $this->assertEquals('float', $this->getProperty($service, 'encodingFormat'));
            $this->assertEquals('', $this->getProperty($service, 'apiKey'));
            $this->assertEquals('', $this->getProperty($service, 'siteUrl'));
            $this->assertEquals('', $this->getProperty($service, 'appName'));
        }

        /**
         * Test factory throws when required fields are missing.
         *
         * @return void
         */
        public function testFactoryThrowsWithoutRequiredValues(): void
        {
            $container = new \VuFindTest\Container\MockContainer($this);
            $container->get(ConfigManagerInterface::class)
                ->method('getConfigObject')
                ->willReturn(new Config([]));

            $factory = new EmbeddingServiceFactory();
            $this->expectException(\InvalidArgumentException::class);
            $factory($container, EmbeddingService::class);
        }
    }
}
