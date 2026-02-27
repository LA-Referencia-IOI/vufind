<?php

/**
 * SemanticSimilarFactory Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Related;

use VuFind\Config\Config;
use VuFind\Related\SemanticSimilar;
use VuFind\Related\SemanticSimilarFactory;

/**
 * SemanticSimilarFactory Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class SemanticSimilarFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test factory with explicit config values.
     *
     * @return void
     */
    public function testFactoryWithConfiguredValues(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\SemanticSearch\Backend::class)
            ->disableOriginalConstructor()
            ->getMock();

        $backendManager = $container->get(\VuFind\Search\BackendManager::class);
        $backendManager->expects($this->once())->method('get')
            ->with($this->equalTo('SemanticSearch'))
            ->willReturn($backend);

        $configManager = $container->get(\VuFind\Config\PluginManager::class);
        $configManager->expects($this->once())->method('get')
            ->with($this->equalTo('semanticsearch'))
            ->willReturn(
                new Config(
                    [
                        'SemanticSearch' => [
                            'vector_field' => 'my_vector',
                            'topK' => 9,
                        ],
                    ]
                )
            );

        $factory = new SemanticSimilarFactory();
        $result = $factory($container, SemanticSimilar::class);

        $this->assertInstanceOf(SemanticSimilar::class, $result);
        $this->assertSame($backend, $this->getProperty($result, 'backend'));
        $this->assertEquals('my_vector', $this->getProperty($result, 'vectorField'));
        $this->assertEquals(9, $this->getProperty($result, 'topK'));
    }

    /**
     * Test default values in the absence of settings.
     *
     * @return void
     */
    public function testFactoryWithDefaultValues(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $backend = $this->getMockBuilder(\VuFindSearch\Backend\SemanticSearch\Backend::class)
            ->disableOriginalConstructor()
            ->getMock();

        $container->get(\VuFind\Search\BackendManager::class)
            ->method('get')
            ->willReturn($backend);
        $container->get(\VuFind\Config\PluginManager::class)
            ->method('get')
            ->willReturn(new Config([]));

        $factory = new SemanticSimilarFactory();
        $result = $factory($container, SemanticSimilar::class);

        $this->assertEquals('vector', $this->getProperty($result, 'vectorField'));
        $this->assertEquals(5, $this->getProperty($result, 'topK'));
    }

    use \VuFindTest\Feature\ReflectionTrait;
}

