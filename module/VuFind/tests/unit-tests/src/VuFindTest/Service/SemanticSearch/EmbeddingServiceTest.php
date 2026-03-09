<?php

/**
 * EmbeddingService Test Class
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
use Laminas\Http\Response;
use VuFind\Service\SemanticSearch\EmbeddingService;

/**
 * EmbeddingService Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class EmbeddingServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test successful embedding request.
     *
     * @return void
     */
    public function testEmbedSuccess(): void
    {
        $http = $this->createMock(HttpClient::class);
        $response = $this->createMock(Response::class);
        $response->method('isSuccess')->willReturn(true);
        $response->method('getBody')
            ->willReturn('{"data":[{"embedding":[0.1,0.2,0.3]}]}');

        $http->expects($this->once())->method('setUri')->with('http://embed/api');
        $http->expects($this->once())->method('setMethod')->with('POST');
        $http->expects($this->once())->method('setRawBody')
            ->with(
                $this->equalTo(
                    '{"input":"hello","model":"model-x","encoding_format":"float","user":"u1"}'
                )
            );
        $http->expects($this->once())->method('setHeaders')
            ->with(['Content-Type' => 'application/json']);
        $http->expects($this->once())->method('send')->willReturn($response);

        $service = new EmbeddingService($http, 'http://embed/api', 'model-x', 'float', 'u1');
        $this->assertEquals([0.1, 0.2, 0.3], $service->embed('hello'));
    }

    /**
     * Test null return on unsuccessful response.
     *
     * @return void
     */
    public function testEmbedFailureResponse(): void
    {
        $http = $this->createMock(HttpClient::class);
        $response = $this->createMock(Response::class);
        $response->method('isSuccess')->willReturn(false);
        $http->method('send')->willReturn($response);

        $service = new EmbeddingService($http, 'http://embed/api', 'model-x', 'float', 'u1');
        $this->assertNull($service->embed('hello'));
    }

    /**
     * Test null return when HTTP client throws.
     *
     * @return void
     */
    public function testEmbedHandlesException(): void
    {
        $http = $this->createMock(HttpClient::class);
        $http->method('send')->willThrowException(new \Exception('network error'));

        $service = new EmbeddingService($http, 'http://embed/api', 'model-x', 'float', 'u1');
        $this->assertNull($service->embed('hello'));
    }
}
}
