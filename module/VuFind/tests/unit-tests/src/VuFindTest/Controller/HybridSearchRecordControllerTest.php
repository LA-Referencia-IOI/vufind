<?php

/**
 * HybridSearchRecordController Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Controller;

use VuFind\Config\Config;
use VuFind\Controller\HybridSearchRecordController;

/**
 * HybridSearchRecordController Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class HybridSearchRecordControllerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test source id configuration.
     *
     * @return void
     */
    public function testSourceId(): void
    {
        $controller = new HybridSearchRecordController(
            new \VuFindTest\Container\MockContainer($this),
            new Config([])
        );
        $this->assertEquals('HybridSearch', $this->getProperty($controller, 'sourceId'));
    }
}
