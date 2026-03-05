# Hybrid Test Classes: Plan and Implementation

## 1. Description of Code Changes

This update adds new unit test coverage for the hybrid search feature set across `VuFind` and `VuFindSearch`.

### New test files created

- `module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/HybridSearchTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/HybridSearchFactoryTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Search/HybridSearch/OptionsTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Search/HybridSearch/ParamsTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Search/HybridSearch/ResultsTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Search/Factory/HybridSearchBackendFactoryTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Controller/HybridSearchControllerTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Controller/HybridSearchRecordControllerTest.php`
- `module/VuFindSearch/tests/unit-tests/src/VuFindTest/Backend/HybridSearch/BackendTest.php`

### Notes

- Tests follow existing VuFind patterns: thin wrappers use strict mocks/callback assertions; backend tests verify parameter construction and behavior branches.

## 2. Proposed Test Classes (Names + Locations)

1. `VuFindTest\RecordDriver\HybridSearchTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/HybridSearchTest.php`
2. `VuFindTest\RecordDriver\HybridSearchFactoryTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/HybridSearchFactoryTest.php`
3. `VuFindTest\Search\HybridSearch\OptionsTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Search/HybridSearch/OptionsTest.php`
4. `VuFindTest\Search\HybridSearch\ParamsTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Search/HybridSearch/ParamsTest.php`
5. `VuFindTest\Search\HybridSearch\ResultsTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Search/HybridSearch/ResultsTest.php`
6. `VuFindTest\Search\Factory\HybridSearchBackendFactoryTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Search/Factory/HybridSearchBackendFactoryTest.php`
7. `VuFindTest\Controller\HybridSearchControllerTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Controller/HybridSearchControllerTest.php`
8. `VuFindTest\Controller\HybridSearchRecordControllerTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Controller/HybridSearchRecordControllerTest.php`
9. `VuFindTest\Backend\HybridSearch\BackendTest`  
   File: `module/VuFindSearch/tests/unit-tests/src/VuFindTest/Backend/HybridSearch/BackendTest.php`

## 3. Per-Class Purpose, Coverage, and Mock Structure

### 3.1 HybridSearchTest (Record Driver)
- Purpose: Verify hybrid record-driver-specific methods.
- Main methods to cover: `getBreadcrumb()`, `getScore()`.
- Example structure:
  - Instantiate driver.
  - `setRawData(['title' => ..., 'score' => ...])`.
  - Assert returned breadcrumb and score defaults.

### 3.2 HybridSearchFactoryTest (Record Driver)
- Purpose: Verify record driver factory behavior and option validation.
- Main methods to cover: `HybridSearchFactory::__invoke()`.
- Example structure:
  - Assert exception when non-empty `$options` passed.
  - Use `MockContainer` + `ConfigManagerInterface` returning `searches` and `config`.
  - Assert resulting instance is `HybridSearch`.

### 3.3 OptionsTest (HybridSearch)
- Purpose: Verify hybrid search routes and class identity.
- Main methods to cover: `getSearchAction()`, `getFacetListAction()`, inherited `getSearchClassId()`.
- Example structure:
  - Build options with mock config manager.
  - Assert route names and class id.

### 3.4 ParamsTest (HybridSearch)
- Purpose: Ensure hybrid params inherit expected Solr behavior.
- Main methods to cover: inherited filter handling + `getSearchClassId()`.
- Example structure:
  - Build `Options` + `Params`.
  - Add filter and validate generated backend params.

### 3.5 ResultsTest (HybridSearch)
- Purpose: Verify hybrid results object binds to hybrid backend id.
- Main methods to cover: protected `$backendId` contract.
- Example structure:
  - Create mock with constructor disabled.
  - Use reflection to assert `$backendId === 'HybridSearch'`.

### 3.6 HybridSearchBackendFactoryTest
- Purpose: Verify record callback from factory uses hybrid record driver plugin.
- Main methods to cover: protected `getCreateRecordCallback()`.
- Example structure:
  - `setup()` with `MockContainer`.
  - Mock `RecordDriver\PluginManager->get('HybridSearch')`.
  - Invoke callback and assert `setRawData(...)` is called.

### 3.7 HybridSearchControllerTest
- Purpose: Verify controller constructor sets hybrid search class id.
- Main methods to cover: constructor behavior.
- Example structure:
  - Instantiate with `MockContainer`.
  - Assert protected `searchClassId` via reflection.

### 3.8 HybridSearchRecordControllerTest
- Purpose: Verify record controller source id is hybrid.
- Main methods to cover: source-id binding.
- Example structure:
  - Instantiate with `MockContainer` and `Config`.
  - Assert protected `sourceId` via reflection.

### 3.9 BackendTest (VuFindSearch HybridSearch)
- Purpose: Verify hybrid backend query construction (lexical + vector) and RRF parameters.
- Main methods to cover: `rawJsonSearch()`.
- Example structure:
  - Mock connector `postJson` and assert:
    - `combined` query structure (DSL);
    - presence of `lexical` (lucene) and `vector` (knn) components;
    - `combiner` settings (rrf, k);
    - fallback to standard search when embedding unavailable.

## 4 Run tests
- Only hybrid tests
```
vendor/bin/phpunit -c module/VuFind/tests/phpunit.xml \
  module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/HybridSearchTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/HybridSearchFactoryTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Search/HybridSearch/OptionsTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Search/HybridSearch/ParamsTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Search/HybridSearch/ResultsTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Search/Factory/HybridSearchBackendFactoryTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Controller/HybridSearchControllerTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Controller/HybridSearchRecordControllerTest.php \ 
```
- Backend test
```
vendor/bin/phpunit -c module/VuFindSearch/tests/phpunit.xml \
  module/VuFindSearch/tests/unit-tests/src/VuFindTest/Backend/HybridSearch/BackendTest.php
```

- All tests
```
vendor/bin/phing phpunit  
```