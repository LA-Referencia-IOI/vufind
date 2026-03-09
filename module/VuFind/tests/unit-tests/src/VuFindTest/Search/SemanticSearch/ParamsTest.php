<?php

/**
 * SemanticSearch Params Test Class
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Tests
 */

namespace VuFindTest\Search\SemanticSearch;

use VuFind\Config\ConfigManagerInterface;
use VuFind\Search\SemanticSearch\Options;
use VuFind\Search\SemanticSearch\Params;

/**
 * SemanticSearch Params Test Class
 *
 * @category VuFind
 * @package  Tests
 */
class ParamsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigRelatedServicesTrait;

    /**
     * Test getSearchClassId().
     *
     * @return void
     */
    public function testGetSearchClassId(): void
    {
        $configManager = $this->getMockConfigManager();
        $options = new Options($configManager);
        $params = new Params($options, $configManager);
        $this->assertEquals('SemanticSearch', $params->getSearchClassId());
    }

    /**
     * Test inherited filter functionality.
     *
     * @return void
     */
    public function testFiltersStillWork(): void
    {
        $configManager = $this->getMockConfigManager();
        $options = new Options($configManager);
        $params = new Params($options, $configManager);
        $params->addFilter('format:Book');
        $this->assertEquals(['format:"Book"'], $params->getBackendParameters()->get('fq'));
    }
}

