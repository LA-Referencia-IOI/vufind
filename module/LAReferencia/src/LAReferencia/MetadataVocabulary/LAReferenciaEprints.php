<?php

namespace LAReferencia\MetadataVocabulary;

use VuFind\MetadataVocabulary\Eprints as VuFindEprints;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

class LAReferenciaEprints extends VuFindEprints
{
    /**
     * Map LAReferencia record data to Eprints metatags.
     *
     * @param RecordDriver $driver Record driver
     *
     * @return array
     */
    public function getMappedData(RecordDriver $driver)
    {
        $mappedData = parent::getMappedData($driver);
        $eprintId = $driver->tryMethod('getIdentifierOAI');
        if ($eprintId) {
            $mappedData['eprints.eprintid'] = [$eprintId];
        }
        return $mappedData;
    }
}
