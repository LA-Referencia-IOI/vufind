<?php

namespace VuFind\Search\HybridSearch;

use VuFind\Search\Solr\Results as SolrResults;

/**
 * HybridSearch Search Results
 *
 * @category VuFind
 * @package  Search
 * @author   Jesiel Viana <jesielviana@gmail.com>
 */
class Results extends SolrResults
{
    /**
     * Search backend identifier.
     *
     * @var string
     */
    protected $backendId = 'HybridSearch';
}
