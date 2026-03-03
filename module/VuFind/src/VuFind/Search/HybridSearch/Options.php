<?php

namespace VuFind\Search\HybridSearch;

use VuFind\Search\Solr\Options as SolrOptions;

/**
 * HybridSearch Search Options
 *
 * @category VuFind
 * @package  Search
 * @author   Jesiel Viana <jesielviana@gmail.com>
 */
class Options extends SolrOptions
{
    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'hybridsearch-results';
    }

    /**
     * Return the route name for the facet list action.
     *
     * @return string
     */
    public function getFacetListAction()
    {
        return 'hybridsearch-facetlist';
    }
}
