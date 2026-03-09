<?php

/**
 * SemanticSearch Record Driver Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\RecordDriver;

use VuFind\RecordDriver\SemanticSearch;

/**
 * SemanticSearch Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class SemanticSearchTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test breadcrumb behavior.
     *
     * @return void
     */
    public function testGetBreadcrumb(): void
    {
        $driver = new SemanticSearch();
        $driver->setRawData(['title' => 'Semantic Title']);
        $this->assertEquals('Semantic Title', $driver->getBreadcrumb());
    }

    /**
     * Test score getter behavior.
     *
     * @return void
     */
    public function testGetScore(): void
    {
        $driver = new SemanticSearch();
        $driver->setRawData(['score' => 0.987]);
        $this->assertEquals(0.987, $driver->getScore());
        $driver->setRawData([]);
        $this->assertEquals('', $driver->getScore());
    }
}

