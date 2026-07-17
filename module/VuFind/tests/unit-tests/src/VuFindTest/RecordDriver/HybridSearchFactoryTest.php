<?php

/**
 * HybridSearchFactory Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\RecordDriver;

use VuFind\Config\Config;
use VuFind\Config\ConfigManagerInterface;
use VuFind\RecordDriver\HybridSearch;
use VuFind\RecordDriver\HybridSearchFactory;

/**
 * HybridSearchFactory Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class HybridSearchFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test option validation.
     *
     * @return void
     */
    public function testFactoryRejectsUnexpectedOptions(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $factory = new HybridSearchFactory();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unexpected options passed to factory.');
        $factory($container, HybridSearch::class, ['unexpected']);
    }

    /**
     * Test successful driver creation.
     *
     * @return void
     */
    public function testFactoryCreatesDriver(): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $configManager = $container->get(ConfigManagerInterface::class);
        $configManager->expects($this->exactly(2))->method('getConfigObject')
            ->willReturnCallback(function ($name) {
                if ('searches' === $name) {
                    return new Config(['General' => ['snippets' => true]]);
                }
                if ('config' === $name) {
                    return new Config([]);
                }
                throw new \Exception('Unexpected config requested: ' . $name);
            });

        $factory = new HybridSearchFactory();
        $result = $factory($container, HybridSearch::class);
        $this->assertInstanceOf(HybridSearch::class, $result);
    }
}
