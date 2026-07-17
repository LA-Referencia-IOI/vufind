<?php

/**
 * HybridSearch Record Driver Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\RecordDriver;

use VuFind\RecordDriver\HybridSearch;

/**
 * HybridSearch Record Driver Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class HybridSearchTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test breadcrumb behavior.
     *
     * @return void
     */
    public function testGetBreadcrumb(): void
    {
        $driver = new HybridSearch();
        $driver->setRawData(['title' => 'Hybrid Title']);
        $this->assertEquals('Hybrid Title', $driver->getBreadcrumb());
    }

    /**
     * Test score getter behavior.
     *
     * @return void
     */
    public function testGetScore(): void
    {
        $driver = new HybridSearch();
        $driver->setRawData(['score' => 0.888]);
        $this->assertEquals(0.888, $driver->getScore());
        $driver->setRawData([]);
        $this->assertEquals('', $driver->getScore());
    }
}
