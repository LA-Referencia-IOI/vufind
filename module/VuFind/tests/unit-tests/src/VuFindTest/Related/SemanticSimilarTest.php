<?php

/**
 * SemanticSimilar Related Items Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Related;

use VuFind\Related\SemanticSimilar;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * SemanticSimilar Related Items Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class SemanticSimilarTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test empty results when title is missing.
     *
     * @return void
     */
    public function testGetResultsWithoutTitle(): void
    {
        $driver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->onlyMethods(['getTitle'])
            ->getMock();
        $driver->expects($this->once())->method('getTitle')->willReturn('');

        $backend = $this->getMockBuilder(\VuFindSearch\Backend\SemanticSearch\Backend::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search'])
            ->getMock();
        $backend->expects($this->never())->method('search');

        $related = new SemanticSimilar($backend);
        $related->init('', $driver);
        $this->assertEquals([], $related->getResults());
    }

    /**
     * Test semantic query behavior and self-filter exclusion.
     *
     * @return void
     */
    public function testGetResultsBuildsExpectedSearchCall(): void
    {
        $driver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->onlyMethods(['getTitle', 'getUniqueId'])
            ->getMock();
        $driver->method('getTitle')->willReturn('Example title');
        $driver->method('getUniqueId')->willReturn('abc123');

        $response = $this->createMock(\VuFindSearch\Response\RecordCollectionInterface::class);
        $response->expects($this->once())->method('getRecords')->willReturn(['r1', 'r2']);

        $backend = $this->getMockBuilder(\VuFindSearch\Backend\SemanticSearch\Backend::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search'])
            ->getMock();
        $backend->expects($this->once())->method('search')
            ->with(
                $this->callback(function ($query) {
                    return $query instanceof Query && $query->getString() === 'Example title';
                }),
                0,
                7,
                $this->callback(function ($params) {
                    return $params instanceof ParamBag
                        && $params->get('fq') === ['-id:"abc123"'];
                })
            )
            ->willReturn($response);

        $related = new SemanticSimilar($backend, 'vector', 7);
        $related->init('', $driver);
        $this->assertEquals(['r1', 'r2'], $related->getResults());
    }

    /**
     * Test exception handling in getResults().
     *
     * @return void
     */
    public function testGetResultsHandlesException(): void
    {
        $driver = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->onlyMethods(['getTitle', 'getUniqueId'])
            ->getMock();
        $driver->method('getTitle')->willReturn('Example title');
        $driver->method('getUniqueId')->willReturn('abc123');

        $backend = $this->getMockBuilder(\VuFindSearch\Backend\SemanticSearch\Backend::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search'])
            ->getMock();
        $backend->expects($this->once())->method('search')
            ->willThrowException(new \Exception('backend failure'));

        $related = new SemanticSimilar($backend);
        $related->init('', $driver);
        $this->assertEquals([], $related->getResults());
    }
}

