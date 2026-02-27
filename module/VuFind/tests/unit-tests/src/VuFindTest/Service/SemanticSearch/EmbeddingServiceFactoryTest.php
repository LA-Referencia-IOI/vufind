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
        interface LoggerAwareInterface extends \Psr\Log\LoggerAwareInterface
        {
        }
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
        $configManager->method('getConfigObject')->with('semanticsearch')
            ->willReturn(
                new Config(
                    [
                        'SemanticSearch' => [
                            'embedding_api_url' => 'http://embed/custom',
                            'model' => 'model-z',
                            'encoding_format' => 'base64',
                            'user' => 'u777',
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
        $this->assertEquals('u777', $this->getProperty($service, 'user'));
    }

    /**
     * Test factory default fallbacks.
     *
     * @return void
     */
    public function testFactoryDefaults(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $httpClient = $this->createMock(HttpClient::class);

        $container->get(ConfigManagerInterface::class)
            ->method('getConfigObject')
            ->willReturn(new Config([]));
        $container->get('VuFindHttp\HttpService')
            ->method('createClient')
            ->willReturn($httpClient);

        $factory = new EmbeddingServiceFactory();
        $service = $factory($container, EmbeddingService::class);

        $this->assertEquals('http://localhost:8000/embed', $this->getProperty($service, 'embeddingUrl'));
        $this->assertEquals(
            'sentence-transformers/paraphrase-multilingual-mpnet-base-v2',
            $this->getProperty($service, 'model')
        );
        $this->assertEquals('float', $this->getProperty($service, 'encodingFormat'));
        $this->assertEquals('user_123', $this->getProperty($service, 'user'));
    }
}
}
