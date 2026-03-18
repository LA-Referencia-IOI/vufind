<?php

/**
 * Unit tests for SemanticSearch backend.
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Search
 */

namespace VuFind\Log {
    if (!interface_exists(LoggerAwareInterface::class)) {
        interface LoggerAwareInterface extends \Psr\Log\LoggerAwareInterface {}
    }
}

namespace VuFindTest\Backend\SemanticSearch {

    use PHPUnit\Framework\TestCase;
    use VuFindSearch\Backend\SemanticSearch\Backend;
    use VuFindSearch\Backend\Solr\Connector;
    use VuFind\Service\SemanticSearch\EmbeddingService;
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
                            && str_contains($q[0], '{!vectorSimilarity f=my_vector minReturn=0.700000 topK=10}')
                            && !empty($params->get('fl'))
                            && str_contains(implode(',', $params->get('fl')), 'score')
                            && null === $params->get('qf')
                            && null === $params->get('qt')
                            && null === $params->get('mm');
                    })
                )
                ->willReturn('{}');

            $embeddingService = $this->createMock(EmbeddingService::class);
            $embeddingService->expects($this->once())->method('embed')
                ->with($this->equalTo('hello world'))
                ->willReturn([0.11, 0.22]);

            $queryBuilder = $this->createMock(QueryBuilder::class);
            $queryBuilder->method('build')
                ->willReturn(new ParamBag(['q' => 'title:foo', 'qf' => 'title^2', 'qt' => 'edismax', 'mm' => '1<75%']));

            $backend = new Backend($connector, $embeddingService, 'my_vector', 0.7);
            $backend->setQueryBuilder($queryBuilder);
            $backend->rawJsonSearch(new Query('hello world'), 5, 10);
        }

        /**
         * Test error behavior when embedding is unavailable.
         *
         * @return void
         */
        public function testRawJsonSearchWithoutEmbeddingFallsBack(): void
        {
            $connector = $this->createMock(Connector::class);
            $connector->expects($this->never())->method('search');

            $embeddingService = $this->createMock(EmbeddingService::class);
            $embeddingService->expects($this->once())->method('embed')
                ->willReturn(null);

            $queryBuilder = $this->createMock(QueryBuilder::class);
            $queryBuilder->method('build')
                ->willReturn(new ParamBag(['q' => 'title:foo', 'qf' => 'title^2']));

            $backend = new Backend($connector, $embeddingService, 'my_vector', 0.7);
            $backend->setQueryBuilder($queryBuilder);
            $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
            $backend->rawJsonSearch(new Query('hello world'), 5, 10);
        }

        /**
         * Test handling of empty queries.
         *
         * @return void
         */
        public function testEmptyQuery(): void
        {
            $connector = $this->createMock(Connector::class);
            $connector->expects($this->once())->method('search')->willReturn('{}');
            $embeddingService = $this->createMock(EmbeddingService::class);
            $embeddingService->expects($this->never())->method('embed');
            $queryBuilder = $this->createMock(QueryBuilder::class);
            $queryBuilder->method('build')->willReturn(new ParamBag(['q' => '*:*']));

            $backend = new Backend($connector, $embeddingService, 'my_vector', 0.7);
            $backend->setQueryBuilder($queryBuilder);
            $this->assertEquals('{}', $backend->rawJsonSearch(new Query(''), 0, 10));
        }
    }
}
