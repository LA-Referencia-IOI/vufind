<?php

namespace VuFind\RecordDriver;

/**
 * HybridSearch Record Driver
 *
 * @category VuFind
 * @package  RecordDriver
 * @author   Jesiel Viana <jesielviana@gmail.com>
 */
class HybridSearch extends SolrDefault
{
    /**
     * Get text that can be displayed to represent this record in
     * breadcrumbs.
     *
     * @return string Breadcrumb text to represent this record.
     */
    public function getBreadcrumb()
    {
        $short = $this->getShortTitle();
        return $short ? $short : $this->getTitle();
    }

    /**
     * Get the score of the record.
     *
     * @return string
     */
    public function getScore()
    {
        return $this->fields['score'] ?? '';
    }
}
