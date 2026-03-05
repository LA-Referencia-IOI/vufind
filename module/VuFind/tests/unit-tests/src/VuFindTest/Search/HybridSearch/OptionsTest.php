<?php

/**
 * HybridSearch Options Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Search\HybridSearch;

use VuFind\Config\ConfigManagerInterface;
use VuFind\Search\HybridSearch\Options;

/**
 * HybridSearch Options Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class OptionsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigRelatedServicesTrait;

    /**
     * Get options object.
     *
     * @param ?ConfigManagerInterface $configManager Config manager
     *
     * @return Options
     */
    protected function getOptions(?ConfigManagerInterface $configManager = null): Options
    {
        return new Options($configManager ?? $this->getMockConfigManager());
    }

    /**
     * Test actions and search class id.
     *
     * @return void
     */
    public function testHybridActionsAndClassId(): void
    {
        $options = $this->getOptions();
        $this->assertEquals('HybridSearch', $options->getSearchClassId());
        $this->assertEquals('hybridsearch-results', $options->getSearchAction());
        $this->assertEquals('hybridsearch-facetlist', $options->getFacetListAction());
    }
}
