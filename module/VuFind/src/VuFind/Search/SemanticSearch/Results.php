<?php

namespace VuFind\Search\SemanticSearch;

/**
 * Solr Semantic Search Results
 */
class Results extends \VuFind\Search\Solr\Results
{
    /**
     * Search backend identifier.
     *
     * @var string
     */
    protected $backendId = 'SemanticSearch';
}
