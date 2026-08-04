<?php

namespace LAReferencia\RecordDriver;

use VuFind\RecordDriver\DefaultRecord as VuFindDefaultRecord;

class DefaultRecord extends VuFindDefaultRecord
{
    /**
     * Get all authors.
     *
     * @return array
     */
    public function getAllAuthors(): array
    {
        return $this->getFieldValues(['author_facet']);
    }

    /**
     * Get the access level.
     *
     * @return string
     */
    public function getAccessLevel(): string
    {
        return $this->getFieldValue('eu_rights_str_mv');
    }

    /**
     * Get the status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->formatCamelCaseValue($this->getFieldValue('status_str'));
    }

    /**
     * Get the country for the record.
     *
     * @return string
     */
    public function getCountry(): string
    {
        return $this->getFieldValue('network_name_str');
    }

    /**
     * Get the institution.
     *
     * @return string
     */
    public function getInstitution(): string
    {
        return $this->getFieldValue('institution');
    }

    /**
     * Get the repository.
     *
     * @return string
     */
    public function getRepository(): string
    {
        return $this->getFieldValue('collection');
    }

    /**
     * Get the OAI identifier.
     *
     * @return string
     */
    public function getIdentifierOAI(): string
    {
        return $this->getFieldValue('oai_identifier_str');
    }

    /**
     * Get the keywords.
     *
     * @return array
     */
    public function getKeywords(): array
    {
        return array_values(array_unique($this->getFieldValues(['topic'])));
    }

    /**
     * Get all authors as one role.
     *
     * @return array
     */
    public function getAllAuthorsOneRole(): array
    {
        $allAuthors = parent::getDeduplicatedAuthors();
        $allAuthors2 = $allAuthors;
        $this->replaceKey($allAuthors2, 'secondary', 'primary');

        return array_merge_recursive($allAuthors, $allAuthors2);
    }

    /**
     * Accept Solr highlight details passed by the Solr record loader.
     *
     * @param array $details Details to add
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setHighlightDetails($details): void
    {
    }

    /**
     * Get a single Solr field value.
     *
     * @param string $field Field name
     *
     * @return string
     */
    protected function getFieldValue(string $field): string
    {
        $values = $this->getFieldValues([$field]);
        return (string)($values[0] ?? '');
    }

    /**
     * Get values from one or more Solr fields.
     *
     * @param array $fields Field names
     *
     * @return array
     */
    protected function getFieldValues(array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $fieldValue = $this->fields[$field] ?? [];
            $fieldValues = is_array($fieldValue) ? $fieldValue : [$fieldValue];
            foreach ($fieldValues as $value) {
                if ($value !== '' && $value !== null) {
                    $values[] = $value;
                }
            }
        }
        return $values;
    }

    /**
     * Replace a key in an array.
     *
     * @param array  $array  Array to update
     * @param string $oldKey Existing key
     * @param string $newKey Replacement key
     *
     * @return void
     */
    protected function replaceKey(array &$array, string $oldKey, string $newKey): void
    {
        if (array_key_exists($oldKey, $array)) {
            $array[$newKey] = $array[$oldKey];
            unset($array[$oldKey]);
        }
    }

    /**
     * Format a camelCase Solr value for display.
     *
     * @param string $value Value to format
     *
     * @return string
     */
    protected function formatCamelCaseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $mappedValues = [
            'openAccess' => 'Open access',
            'publishedVersion' => 'Published version',
        ];
        if (isset($mappedValues[$value])) {
            return $mappedValues[$value];
        }

        $value = preg_replace('/(?<!^)[A-Z]/', ' $0', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return ucfirst(strtolower(trim($value)));
    }
}
