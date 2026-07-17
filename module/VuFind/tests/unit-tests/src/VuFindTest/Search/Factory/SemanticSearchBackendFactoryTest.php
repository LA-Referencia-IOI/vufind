<?php

/**
 * SemanticSearchBackendFactory Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Search\Factory;

use VuFind\Search\Factory\SemanticSearchBackendFactory;

/**
 * SemanticSearchBackendFactory Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class SemanticSearchBackendFactoryTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test create-record callback behavior.
     *
     * @return void
     */
    public function testCreateRecordCallbackUsesSemanticDriver(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $driver = $this->getMockBuilder(\VuFind\RecordDriver\SemanticSearch::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setRawData'])
            ->getMock();
        $driver->expects($this->once())->method('setRawData')
            ->with($this->equalTo(['id' => '123']));

        $pluginManager = $container->get(\VuFind\RecordDriver\PluginManager::class);
        $pluginManager->expects($this->once())->method('get')
            ->with($this->equalTo('SemanticSearch'))
            ->willReturn($driver);

        $factory = new SemanticSearchBackendFactory();
        $factory->setup($container);

        $callback = $this->callMethod($factory, 'getCreateRecordCallback');
        $result = $callback(['id' => '123']);
        $this->assertSame($driver, $result);
    }
}

