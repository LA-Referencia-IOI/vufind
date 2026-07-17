<?php

/**
 * Unit tests for HybridSearch backend.
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

namespace VuFindTest\Backend\HybridSearch {

    use PHPUnit\Framework\TestCase;
    use VuFindSearch\Backend\HybridSearch\Backend;
    use VuFindSearch\Backend\Solr\Backend as SolrBackend;
    use VuFindSearch\Backend\Solr\Connector;
    use VuFind\Service\SemanticSearch\EmbeddingService;
    use VuFindSearch\Backend\Solr\QueryBuilder;
    use VuFindSearch\ParamBag;
    use VuFindSearch\Query\Query;

    /**
     * Unit tests for HybridSearch backend.
     *
     * @category VuFind
     * @package  Search
     */
    class BackendTest extends TestCase
    {
        /**
         * Verify backend inheritance from Solr backend.
         *
         * @return void
         */
        public function testBackendExtendsSolrBackend(): void
        {
            $this->assertTrue(is_subclass_of(Backend::class, SolrBackend::class));
        }

        /**
         * Test hybrid search query generation when embedding is available.
         *
         * @return void
         */
        public function testRawJsonSearchBuildsCombinedQuery(): void
        {
            $connector = $this->createMock(Connector::class);
            $connector->expects($this->once())->method('postJson')
                ->with(
                    $this->equalTo('combined'),
                    $this->callback(function ($json) {
                        $data = json_decode($json, true);
                        return isset($data['queries']['lexical']['lucene']['query'])
                            && $data['queries']['lexical']['lucene']['query'] === 'title:foo'
                            && isset($data['queries']['vector']['knn']['query'])
                            && str_contains($data['queries']['vector']['knn']['query'], '[0.11,0.22]')
                            && $data['queries']['vector']['knn']['f'] === 'my_vector'
                            && $data['queries']['vector']['knn']['topK'] === 10
                            && $data['params']['combiner'] === true
                            && $data['params']['combiner.algorithm'] === 'rrf'
                            && $data['params']['combiner.rrf.k'] === 60
                            && $data['limit'] === 10
                            && $data['offset'] === 5
                            && str_contains($data['fields'], 'score');
                    }),
                    $this->callback(function ($params) {
                        return $params instanceof ParamBag
                            && $params->get('hl') === ['true'];
                    })
                )
                ->willReturn('{}');

            $embeddingService = $this->createMock(EmbeddingService::class);
            $embeddingService->expects($this->once())->method('embed')
                ->with($this->equalTo('hello world'))
                ->willReturn([0.11, 0.22]);

            $queryBuilder = $this->createMock(QueryBuilder::class);
            $queryBuilder->method('build')
                ->willReturn(new ParamBag(['q' => 'title:foo', 'qf' => 'title^2']));

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
