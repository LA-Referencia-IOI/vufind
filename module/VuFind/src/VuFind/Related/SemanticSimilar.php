<?php

/**
 * Related Records: Solr vector-based semantic similarity
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */

namespace VuFind\Related;

use VuFindSearch\Backend\SemanticSearch\Backend;
use VuFindSearch\ParamBag;

/**
 * Related Records: Solr vector-based semantic similarity
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
class SemanticSimilar implements RelatedInterface
{
    /**
     * Search backend
     *
     * @var Backend
     */
    protected $backend;

    /**
     * Vector field name
     *
     * @var string
     */
    protected $vectorField;

    /**
     * Number of results to display
     *
     * @var int
     */
    protected $topK;

    /**
     * Record driver
     *
     * @var \VuFind\RecordDriver\SolrDefault
     */
    protected $driver;

    /**
     * Constructor
     *
     * @param Backend $backend     Search backend
     * @param string  $vectorField Vector field name
     * @param int     $topK        Number of results to display
     */
    public function __construct(Backend $backend, string $vectorField = 'vector', int $topK = 5)
    {
        $this->backend = $backend;
        $this->vectorField = $vectorField;
        $this->topK = $topK;
    }

    /**
     * Establish a relationship with a record driver.
     *
     * @param string                            $settings Settings (optional)
     * @param \VuFind\RecordDriver\AbstractBase $driver   Record driver
     *
     * @return void
     */
    public function init($settings, $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Get a list of related records.
     *
     * @return array
     */
    public function getResults()
    {
        // 1. Get the title from the current record
        $title = $this->driver->getTitle();
        if (empty($title)) {
            return [];
        }

        // 2. Perform search using rawJsonSearch which handles embedding and semantic query construction
        try {
            // We pass the title as a simple query string.
            // rawJsonSearch will detect it's not a KNN query and call the embedding API.
            $queryObject = new \VuFindSearch\Query\Query($title);

            // Set params to exclude self
            $params = new ParamBag([
                'fq' => '-id:"' . $this->driver->getUniqueId() . '"'
            ]);

            // rawJsonSearch signature: (AbstractQuery $query, $offset, $limit, ?ParamBag $params = null)
            $json = $this->backend->rawJsonSearch($queryObject, 0, $this->topK, $params);
            $response = json_decode($json, true);
            $collection = $this->backend->getRecordCollectionFactory()->factory($response);
            return $collection->getRecords();
        } catch (\Exception $e) {
            // Log error?
            return [];
        }
    }
}
