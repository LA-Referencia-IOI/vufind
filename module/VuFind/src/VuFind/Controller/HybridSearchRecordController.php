<?php

namespace VuFind\Controller;

/**
 * Hybrid Search Record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Jesiel Viana <jesielviana@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HybridSearchRecordController extends RecordController
{
    /**
     * Type of record to display
     *
     * @var string
     */
    protected $sourceId = 'HybridSearch';
}
