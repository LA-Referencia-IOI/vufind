<?php

/**
 * SemanticSearch Options Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Search\SemanticSearch;

use VuFind\Config\ConfigManagerInterface;
use VuFind\Search\SemanticSearch\Options;

/**
 * SemanticSearch Options Test Class
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
    public function testSemanticActionsAndClassId(): void
    {
        $options = $this->getOptions();
        $this->assertEquals('SemanticSearch', $options->getSearchClassId());
        $this->assertEquals('semanticsearch-results', $options->getSearchAction());
        $this->assertEquals('semanticsearch-facetlist', $options->getFacetListAction());
    }
}

