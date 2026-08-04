<?php

namespace LAReferencia\RecordDriver;

class LAReferenciaHybridSearch extends LAReferenciaSolrDefault
{
    /**
     * Get text that can be displayed to represent this record in breadcrumbs.
     *
     * @return string Breadcrumb text to represent this record.
     */
    public function getBreadcrumb()
    {
        $short = $this->getShortTitle();
        return $short ? $short : $this->getTitle();
    }

    /**
     * Get the score for the current record.
     *
     * @return string
     */
    public function getScore()
    {
        return $this->fields['score'] ?? '';
    }
}
