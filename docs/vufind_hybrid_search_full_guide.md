# Walkthrough: Hybrid Search Implementation with RRF

I have successfully implemented a new **HybridSearch** data source in VuFind that leverages Apache Solr 9.11+ native Reciprocal Rank Fusion (RRF). This implementation merges traditional lexical (keyword) search with semantic (vector) search.

## Overview of Changes

The implementation involved creating a new independent data source, configuring Solr, enhancing core components, and integrating everything into the VuFind framework.

### 1. New Files Created

| File Path | Description |
|-----------|-------------|
| [Backend.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFindSearch/src/VuFindSearch/Backend/HybridSearch/Backend.php) | Core hybrid search logic, constructing JSON Combined Query DSL for RRF. |
| [HybridSearchBackendFactory.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/Search/Factory/HybridSearchBackendFactory.php) | Factory to initialize the HybridSearch backend with custom configs. |
| [HybridSearchController.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/Controller/HybridSearchController.php) | Main controller for handling hybrid search requests. |
| [HybridSearchRecordController.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/Controller/HybridSearchRecordController.php) | Controller for individual record views within the HybridSearch data source. |
| [HybridSearch.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/RecordDriver/HybridSearch.php) | Record driver specifically for hybrid results, supporting breadcrumbs and RRF scores. |
| [HybridSearchFactory.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/RecordDriver/HybridSearchFactory.php) | Factory for the HybridSearch record driver. |
| [Options.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/Search/HybridSearch/Options.php) | Search options configuration (routes, handlers). |
| [Params.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/Search/HybridSearch/Params.php) | Search parameters class. |
| [Results.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/Search/HybridSearch/Results.php) | Search results set class. |
| [hybridsearch.ini](file:///home/jesielviana/Dev/ioi/vufind/local/config/vufind/hybridsearch.ini) | Primary configuration for embeddings API, vector fields, and RRF parameters. |
| [results.phtml](file:///home/jesielviana/Dev/ioi/vufind/themes/bootstrap5/templates/hybridsearch/results.phtml) | UI template for displaying hybrid search results. |
| [facet-list.phtml](file:///home/jesielviana/Dev/ioi/vufind/themes/bootstrap5/templates/hybridsearch/facet-list.phtml) | UI template for displaying facet lists. |

### 2. Files Modified

| File Path | Description |
|-----------|-------------|
| [solrconfig.xml](file:///home/jesielviana/Dev/ioi/vufind/solr/vufind/biblio/conf/solrconfig.xml) | Defined `/combined` handler and `combined_query` component. Added `df` support for RRF queries. |
| [Connector.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFindSearch/src/VuFindSearch/Backend/Solr/Connector.php) | Added `postJson()` method to support sending raw JSON bodies to Solr. |
| [module.config.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/config/module.config.php) | Registered controllers, record drivers, routes, and template maps. |
| [BackendRegistry.php](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/Search/BackendRegistry.php) | Registered `HybridSearch` backend for discovery by the Search Service. |
| [PluginManager.php (RecordDriver)](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/RecordDriver/PluginManager.php) | Registered `HybridSearch` record driver and its factory. |
| [PluginManager.php (Options)](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/Search/Options/PluginManager.php) | Registered `HybridSearch` options. |
| [PluginManager.php (Params)](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/Search/Params/PluginManager.php) | Registered `HybridSearch` parameters. |
| [PluginManager.php (Results)](file:///home/jesielviana/Dev/ioi/vufind/module/VuFind/src/VuFind/Search/Results/PluginManager.php) | Registered `HybridSearch` results. |

## Key Features & Fixes

- **Reciprocal Rank Fusion (RRF)**: Native Solr 9.11+ integration for fusing keyword and vector scores.
- **Independent Data Source**: `HybridSearch` is isolated from `SemanticSearch`, ensuring modularity.
- **Score Visibility**: Scores are correctly requested via the `fields` key in the JSON DSL and displayed in the UI.
- **Robustness**: 
    - Fixed **400 error** by adding default field (`df`) support in Solr.
    - Fixed **500 error** by disabling highlighting when RRF is active (handling current Solr limitations).
- **Full Integration**: Works with VuFind's core plugin system, including breadcrumbs and facets.
- **Performance Logging**: Measures and logs the duration of embedding API requests and Solr combined query requests (visible in VuFind debug logs).

## Verification
1. Navigate to `/HybridSearch/Results?lookfor=your+query`.
2. Confirm both lexical and vector-influenced results appear.
3. Confirm the calculated rank score is visible.
4. Click through to a record to verify the `HybridSearchRecordController` and breadcrumbs.

## Documentation

- **Post reference**: [https://sease.io/2026/03/hybrid-search-with-reciprocal-rank-fusion-in-apache-solr.html](https://sease.io/2026/03/hybrid-search-with-reciprocal-rank-fusion-in-apache-solr.html)
- **Implementation Analysis**: [lexical_and_semantic_search_implementation_analysis.md](lexical_and_semantic_search_implementation_analysis.md)
- **Enablement Guide**: [enable_hybrid_search.md](enable_hybrid_search.md)
- **Full Guide**: [vufind_hybrid_search_full_guide.md](vufind_hybrid_search_full_guide.md) (This file)
