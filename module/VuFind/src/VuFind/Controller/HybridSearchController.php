<?php

namespace VuFind\Controller;

/**
 * HybridSearch Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Jesiel Viana <jesielviana@gmail.com>
 */
class HybridSearchController extends AbstractSearch
{
    /**
     * Constructor
     *
     * @param \Psr\Container\ContainerInterface $container Service manager
     */
    public function __construct(\Psr\Container\ContainerInterface $container)
    {
        parent::__construct($container);
        $this->searchClassId = 'HybridSearch';
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        $config = $this->getConfig();
        return isset($config->Record->next_prev_results)
            && $config->Record->next_prev_results;
    }
}
