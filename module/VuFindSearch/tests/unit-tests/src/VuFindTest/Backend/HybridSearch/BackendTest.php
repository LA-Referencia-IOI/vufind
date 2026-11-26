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
                        $vector = $data['queries']['vector']['parent']['query']['knn'] ?? [];
                        return ($data['queries']['lexical']['edismax']['query'] ?? null) === 'hello world'
                            && ($vector['query'] ?? null) === '[0.11,0.22]'
                            && ($vector['f'] ?? null) === 'my_vector'
                            && ($vector['topK'] ?? null) === 10
                            && $data['params']['combiner'] === true
                            && $data['params']['combiner.algorithm'] === 'rrf'
                            && $data['params']['combiner.rrf.k'] === 60
                            && $data['limit'] === 10
                            && $data['offset'] === 5
                            && str_contains($data['fields'], 'score');
                    }),
                    $this->callback(function ($params) {
                        return $params instanceof ParamBag
                            && $params->get('hl') === ['true']
                            && $params->get('defType') === null
                            && $params->get('qt') === null
                            && $params->get('qf') === ['title^2'];
                    })
                )
                ->willReturn('{}');

            $embeddingService = $this->createMock(EmbeddingService::class);
            $embeddingService->expects($this->once())->method('embed')
                ->with($this->equalTo('hello world'))
                ->willReturn([0.11, 0.22]);

            $queryBuilder = $this->createMock(QueryBuilder::class);
            $queryBuilder->method('build')
                ->willReturn(new ParamBag(['q' => 'hello world', 'qf' => 'title^2', 'defType' => 'edismax']));

            $backend = new Backend($connector, $embeddingService, 'my_vector', 0.7, 60, 10);
            $backend->setQueryBuilder($queryBuilder);
            $backend->rawJsonSearch(new Query('hello world'), 5, 10);
        }

        public function testHighlightCanBeDisabled(): void
        {
            $connector = $this->createMock(Connector::class);
            $connector->expects($this->once())->method('postJson')
                ->with('combined', $this->anything(), $this->callback(
                    fn (ParamBag $params) => $params->get('hl') === ['false'] && $params->get('hl.q') === null
                ))->willReturn('{}');
            $embeddingService = $this->createMock(EmbeddingService::class);
            $embeddingService->method('embed')->willReturn([0.11, 0.22]);
            $queryBuilder = $this->createMock(QueryBuilder::class);
            $queryBuilder->method('build')->willReturn(new ParamBag(['q' => 'hello world']));
            $backend = new Backend($connector, $embeddingService, 'my_vector', 0.7, 60, 10, true, false);
            $backend->setQueryBuilder($queryBuilder);
            $backend->rawJsonSearch(new Query('hello world'), 0, 10, new ParamBag(['hl' => 'true']));
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

            $backend = new Backend($connector, $embeddingService, 'my_vector', 0.7, 60, 10);
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

            $backend = new Backend($connector, $embeddingService, 'my_vector', 0.7, 60, 10);
            $backend->setQueryBuilder($queryBuilder);
            $this->assertEquals('{}', $backend->rawJsonSearch(new Query(''), 0, 10));
        }
    }
}
