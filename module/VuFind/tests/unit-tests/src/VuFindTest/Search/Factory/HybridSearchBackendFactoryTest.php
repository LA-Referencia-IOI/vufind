<?php

/**
 * HybridSearchBackendFactory Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Search\Factory;

use VuFind\Search\Factory\HybridSearchBackendFactory;

/**
 * HybridSearchBackendFactory Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class HybridSearchBackendFactoryTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test create-record callback behavior.
     *
     * @return void
     */
    public function testCreateRecordCallbackUsesHybridDriver(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $driver = $this->getMockBuilder(\VuFind\RecordDriver\HybridSearch::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setRawData'])
            ->getMock();
        $driver->expects($this->once())->method('setRawData')
            ->with($this->equalTo(['id' => '123']));

        $pluginManager = $container->get(\VuFind\RecordDriver\PluginManager::class);
        $pluginManager->expects($this->once())->method('get')
            ->with($this->equalTo('HybridSearch'))
            ->willReturn($driver);

        $factory = new HybridSearchBackendFactory();
        $factory->setup($container);

        $callback = $this->callMethod($factory, 'getCreateRecordCallback');
        $result = $callback(['id' => '123']);
        $this->assertSame($driver, $result);
    }
}
