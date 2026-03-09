<?php

/**
 * HybridSearchController Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Controller;

use VuFind\Controller\HybridSearchController;

/**
 * HybridSearchController Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class HybridSearchControllerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test constructor sets search class id.
     *
     * @return void
     */
    public function testConstructorSetsSearchClassId(): void
    {
        $controller = new HybridSearchController(new \VuFindTest\Container\MockContainer($this));
        $this->assertEquals('HybridSearch', $this->getProperty($controller, 'searchClassId'));
    }
}
