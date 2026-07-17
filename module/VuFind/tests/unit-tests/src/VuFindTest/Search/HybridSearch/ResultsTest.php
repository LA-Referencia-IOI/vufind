<?php

/**
 * HybridSearch Results Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Search\HybridSearch;

use VuFind\Search\HybridSearch\Results;

/**
 * HybridSearch Results Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class ResultsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test backend identifier.
     *
     * @return void
     */
    public function testBackendId(): void
    {
        $results = $this->getMockBuilder(Results::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->assertEquals('HybridSearch', $this->getProperty($results, 'backendId'));
    }
}
