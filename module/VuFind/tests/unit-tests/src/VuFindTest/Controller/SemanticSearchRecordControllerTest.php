<?php

/**
 * SemanticSearchRecordController Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Controller;

use VuFind\Config\Config;
use VuFind\Controller\SemanticSearchRecordController;

/**
 * SemanticSearchRecordController Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class SemanticSearchRecordControllerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test source id configuration.
     *
     * @return void
     */
    public function testSourceId(): void
    {
        $controller = new SemanticSearchRecordController(
            new \VuFindTest\Container\MockContainer($this),
            new Config([])
        );
        $this->assertEquals('SemanticSearch', $this->getProperty($controller, 'sourceId'));
    }
}

