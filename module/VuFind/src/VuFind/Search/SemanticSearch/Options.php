<?php

namespace VuFind\Search\SemanticSearch;

use VuFind\Config\ConfigManagerInterface;

/**
 * Solr Semantic Search Options
 */
class Options extends \VuFind\Search\Solr\Options
{
    /**
     * Constructor
     *
     * @param ConfigManagerInterface $configManager Config manager
     */
    public function __construct(ConfigManagerInterface $configManager)
    {
        parent::__construct($configManager);
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'semanticsearch-results';
    }

    /**
     * Return the route name for the facet list action.
     *
     * @return string
     */
    public function getFacetListAction()
    {
        return 'semanticsearch-facetlist';
    }
}
