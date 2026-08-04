<?php

namespace LAReferencia\RecordDataFormatter\Specs;

use VuFind\RecordDataFormatter\Specs\DefaultRecord as VuFindDefaultRecord;

class DefaultRecord extends VuFindDefaultRecord
{
    /**
     * Get the callback function for processing authors.
     *
     * @return callable
     */
    public function getAuthorFunction(): callable
    {
        return function ($data, $options) {
            $authors = [];
            foreach ($this->authorOrder as $type => $order) {
                foreach ($data[$type] ?? [] as $author => $dataFields) {
                    $authors[$author] ??= $dataFields;
                }
            }

            return empty($authors) ? [] : [
                [
                    'label' => count($authors) === 1 ? 'Author' : 'Authors',
                    'values' => ['primary' => $authors],
                    'options' => [
                        'pos' => $options['pos'] + 1,
                        'renderType' => 'RecordDriverTemplate',
                        'template' => 'data-authors.phtml',
                        'context' => [
                            'type' => 'primary',
                            'schemaLabel' => 'author',
                        ],
                    ],
                ],
            ];
        };
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     */
    protected function getDefaultCoreSpecs(): array
    {
        $spec = parent::getDefaultCoreSpecs();
        unset(
            $spec['Published in'],
            $spec['New Title'],
            $spec['Previous Title'],
            $spec['Published'],
            $spec['Edition'],
            $spec['Series'],
            $spec['Subjects'],
            $spec['Citations'],
            $spec['child_records'],
            $spec['Related Items'],
            $spec['Tags'],
        );

        $spec['Authors']['pos'] = 100;
        $spec['Format']['pos'] = 200;
        $spec['Status'] = [
            'dataMethod' => 'getStatus',
            'renderType' => null,
            'pos' => 300,
        ];
        $spec['Publication date'] = [
            'dataMethod' => 'getPublicationDates',
            'renderType' => null,
            'pos' => 400,
        ];
        $spec['Country'] = [
            'dataMethod' => 'getCountry',
            'renderType' => null,
            'pos' => 500,
        ];
        $spec['Institution'] = [
            'dataMethod' => 'getInstitution',
            'renderType' => null,
            'pos' => 600,
        ];
        $spec['Repository'] = [
            'dataMethod' => 'getRepository',
            'renderType' => null,
            'pos' => 700,
        ];
        $spec['Language']['pos'] = 800;
        $spec['OAI Identifier'] = [
            'dataMethod' => 'getIdentifierOAI',
            'renderType' => null,
            'pos' => 900,
        ];
        $spec['Online Access']['pos'] = 1000;
        $spec['Access Level'] = [
            'dataMethod' => 'getAccessLevel',
            'renderType' => null,
            'translate' => true,
            'pos' => 1100,
        ];
        $spec['Keyword'] = [
            'dataMethod' => 'getKeywords',
            'renderType' => null,
            'pos' => 1200,
        ];
        return $spec;
    }
}
