<?php

/**
 * SemanticSearchController Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Controller;

use VuFind\Controller\SemanticSearchController;

/**
 * SemanticSearchController Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class SemanticSearchControllerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test constructor sets search class id.
     *
     * @return void
     */
    public function testConstructorSetsSearchClassId(): void
    {
        $controller = new SemanticSearchController(new \VuFindTest\Container\MockContainer($this));
        $this->assertEquals('SemanticSearch', $this->getProperty($controller, 'searchClassId'));
    }
}

