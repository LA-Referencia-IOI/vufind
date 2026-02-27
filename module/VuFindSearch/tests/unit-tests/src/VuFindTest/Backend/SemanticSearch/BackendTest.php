<?php

/**
 * Unit tests for SemanticSearch backend.
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Search
 */

namespace VuFindTest\Backend\SemanticSearch;

use Laminas\Http\Client as HttpClient;
use Laminas\Http\Response;
use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\SemanticSearch\Backend;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\QueryBuilder;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Unit tests for SemanticSearch backend.
 *
 * @category VuFind
 * @package  Search
 */
class BackendTest extends TestCase
{
    /**
     * Test semantic raw search query generation when embedding is available.
     *
     * @return void
     */
    public function testRawJsonSearchBuildsVectorQuery(): void
    {
        $connector = $this->createMock(Connector::class);
        $connector->expects($this->once())->method('search')
            ->with(
                $this->callback(function ($params) {
                    $q = $params->get('q');
                    return $params instanceof ParamBag
                        && $params->get('rows') === [10]
                        && $params->get('start') === [5]
                        && !empty($q[0])
                        && str_contains($q[0], '{!vectorSimilarity f=my_vector minReturn=0.700000}')
                        && !empty($params->get('fl'))
                        && str_contains(implode(',', $params->get('fl')), 'score')
                        && null === $params->get('qf')
                        && null === $params->get('qt')
                        && null === $params->get('mm');
                })
            )
            ->willReturn('{}');

        $http = $this->createMock(HttpClient::class);
        $response = $this->createMock(Response::class);
        $response->method('isSuccess')->willReturn(true);
        $response->method('getBody')
            ->willReturn('{"data":[{"embedding":[0.11,0.22]}]}');
        $http->method('send')->willReturn($response);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('build')
            ->willReturn(new ParamBag(['q' => 'title:foo', 'qf' => 'title^2', 'qt' => 'edismax', 'mm' => '1<75%']));

        $backend = new Backend($connector, $http, 'http://embed/api', 'my_vector', 10, 0.7);
        $backend->setQueryBuilder($queryBuilder);
        $backend->rawJsonSearch(new Query('hello world'), 5, 10);
    }

    /**
     * Test regular raw search behavior when embedding cannot be generated.
     *
     * @return void
     */
    public function testRawJsonSearchWithoutEmbeddingFallsBack(): void
    {
        $connector = $this->createMock(Connector::class);
        $connector->expects($this->once())->method('search')
            ->with(
                $this->callback(function ($params) {
                    return $params instanceof ParamBag
                        && $params->get('q') === ['title:foo']
                        && $params->get('qf') === ['title^2']
                        && $params->get('fl') === null;
                })
            )
            ->willReturn('{}');

        $http = $this->createMock(HttpClient::class);
        $response = $this->createMock(Response::class);
        $response->method('isSuccess')->willReturn(false);
        $http->method('send')->willReturn($response);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('build')
            ->willReturn(new ParamBag(['q' => 'title:foo', 'qf' => 'title^2']));

        $backend = new Backend($connector, $http, 'http://embed/api', 'my_vector', 10, 0.7);
        $backend->setQueryBuilder($queryBuilder);
        $backend->rawJsonSearch(new Query('hello world'), 0, 10);
    }

    /**
     * Test exception handling in getEmbedding().
     *
     * @return void
     */
    public function testGetEmbeddingHandlesException(): void
    {
        $connector = $this->createMock(Connector::class);
        $http = $this->createMock(HttpClient::class);
        $http->method('send')->willThrowException(new \Exception('network error'));
        $backend = new Backend($connector, $http, 'http://embed/api', 'my_vector', 10, 0.7);
        $this->assertNull($backend->getEmbedding('hello world'));
    }
}

