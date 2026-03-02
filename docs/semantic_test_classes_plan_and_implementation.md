# Semantic Test Classes: Plan and Implementation

## 1. Description of Code Changes

This update added new unit test coverage for the semantic search/similarity feature set across `VuFind` and `VuFindSearch`.

### New test files created

- `module/VuFind/tests/unit-tests/src/VuFindTest/Related/SemanticSimilarTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Related/SemanticSimilarFactoryTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/SemanticSearchTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/SemanticSearchFactoryTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Search/SemanticSearch/OptionsTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Search/SemanticSearch/ParamsTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Search/SemanticSearch/ResultsTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Search/Factory/SemanticSearchBackendFactoryTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Service/SemanticSearch/EmbeddingServiceTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Service/SemanticSearch/EmbeddingServiceFactoryTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Controller/SemanticSearchControllerTest.php`
- `module/VuFind/tests/unit-tests/src/VuFindTest/Controller/SemanticSearchRecordControllerTest.php`
- `module/VuFindSearch/tests/unit-tests/src/VuFindTest/Backend/SemanticSearch/BackendTest.php`

### Notes

- Tests follow existing VuFind patterns: thin wrappers use strict mocks/callback assertions; backend tests verify parameter construction and behavior branches.
- A test-local compatibility shim for `VuFind\Log\LoggerAwareInterface` was included in embedding service tests because that interface is missing in this branch.

## 2. Proposed Test Classes (Names + Locations)

1. `VuFindTest\Related\SemanticSimilarTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Related/SemanticSimilarTest.php`
2. `VuFindTest\Related\SemanticSimilarFactoryTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Related/SemanticSimilarFactoryTest.php`
3. `VuFindTest\RecordDriver\SemanticSearchTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/SemanticSearchTest.php`
4. `VuFindTest\RecordDriver\SemanticSearchFactoryTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/SemanticSearchFactoryTest.php`
5. `VuFindTest\Search\SemanticSearch\OptionsTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Search/SemanticSearch/OptionsTest.php`
6. `VuFindTest\Search\SemanticSearch\ParamsTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Search/SemanticSearch/ParamsTest.php`
7. `VuFindTest\Search\SemanticSearch\ResultsTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Search/SemanticSearch/ResultsTest.php`
8. `VuFindTest\Search\Factory\SemanticSearchBackendFactoryTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Search/Factory/SemanticSearchBackendFactoryTest.php`
9. `VuFindTest\Service\SemanticSearch\EmbeddingServiceTest`  
   File: `module/VuFind/tests/unit-tests/src/VuFindTest/Service/SemanticSearch/EmbeddingServiceTest.php`
10. `VuFindTest\Service\SemanticSearch\EmbeddingServiceFactoryTest`  
    File: `module/VuFind/tests/unit-tests/src/VuFindTest/Service/SemanticSearch/EmbeddingServiceFactoryTest.php`
11. `VuFindTest\Controller\SemanticSearchControllerTest`  
    File: `module/VuFind/tests/unit-tests/src/VuFindTest/Controller/SemanticSearchControllerTest.php`
12. `VuFindTest\Controller\SemanticSearchRecordControllerTest`  
    File: `module/VuFind/tests/unit-tests/src/VuFindTest/Controller/SemanticSearchRecordControllerTest.php`
13. `VuFindTest\Backend\SemanticSearch\BackendTest`  
    File: `module/VuFindSearch/tests/unit-tests/src/VuFindTest/Backend/SemanticSearch/BackendTest.php`

## 3. Per-Class Purpose, Coverage, and Mock Structure

### 3.1 SemanticSimilarTest
- Purpose: Verify related-items semantic retrieval behavior.
- Main methods to cover: `init()`, `getResults()`.
- Example structure:
  - Mock `SolrDefault` driver (`getTitle`, `getUniqueId`).
  - Mock semantic backend `search(...)`.
  - Assert:
    - empty title returns `[]`;
    - query and `fq=-id:"<id>"` are passed correctly;
    - backend exceptions are handled and return `[]`.
- Required mock dependencies:
  - `VuFind\RecordDriver\SolrDefault`
  - `VuFindSearch\Backend\SemanticSearch\Backend`
  - `VuFindSearch\Response\RecordCollectionInterface`

### 3.2 SemanticSimilarFactoryTest
- Purpose: Verify factory wiring and config defaulting.
- Main methods to cover: `SemanticSimilarFactory::__invoke()`.
- Example structure:
  - Use `MockContainer`.
  - Mock `BackendManager->get('SemanticSearch')`.
  - Mock `Config\PluginManager->get('semanticsearch')`.
  - Assert constructed object internals (`backend`, `vectorField`, `topK`) with reflection.
- Required mock dependencies:
  - `VuFind\Search\BackendManager`
  - `VuFind\Config\PluginManager`
  - `VuFindSearch\Backend\SemanticSearch\Backend`

### 3.3 SemanticSearchTest
- Purpose: Verify semantic record-driver-specific methods.
- Main methods to cover: `getBreadcrumb()`, `getScore()`.
- Example structure:
  - Instantiate driver.
  - `setRawData(['title' => ..., 'score' => ...])`.
  - Assert returned breadcrumb and score defaults.
- Required mock dependencies:
  - None (plain instance is enough).

### 3.4 SemanticSearchFactoryTest
- Purpose: Verify record driver factory behavior and option validation.
- Main methods to cover: `SemanticSearchFactory::__invoke()`.
- Example structure:
  - Assert exception when non-empty `$options` passed.
  - Use `MockContainer` + `ConfigManagerInterface` returning `searches` and `config`.
  - Assert resulting instance is `SemanticSearch`.
- Required mock dependencies:
  - `VuFind\Config\ConfigManagerInterface`

### 3.5 OptionsTest (SemanticSearch)
- Purpose: Verify semantic search routes and class identity.
- Main methods to cover: `getSearchAction()`, `getFacetListAction()`, inherited `getSearchClassId()`.
- Example structure:
  - Build options with mock config manager.
  - Assert route names and class id.
- Required mock dependencies:
  - `VuFind\Config\ConfigManagerInterface`

### 3.6 ParamsTest (SemanticSearch)
- Purpose: Ensure semantic params inherit expected Solr behavior.
- Main methods to cover: inherited filter handling + `getSearchClassId()`.
- Example structure:
  - Build `Options` + `Params`.
  - Add filter and validate generated backend params.
- Required mock dependencies:
  - `VuFind\Config\ConfigManagerInterface`

### 3.7 ResultsTest (SemanticSearch)
- Purpose: Verify semantic results object binds to semantic backend id.
- Main methods to cover: protected `$backendId` contract.
- Example structure:
  - Create mock with constructor disabled.
  - Use reflection to assert `$backendId === 'SemanticSearch'`.
- Required mock dependencies:
  - None beyond PHPUnit mock builder.

### 3.8 SemanticSearchBackendFactoryTest
- Purpose: Verify record callback from factory uses semantic record driver plugin.
- Main methods to cover: protected `getCreateRecordCallback()`.
- Example structure:
  - `setup()` with `MockContainer`.
  - Mock `RecordDriver\PluginManager->get('SemanticSearch')`.
  - Invoke callback and assert `setRawData(...)` is called.
- Required mock dependencies:
  - `VuFind\RecordDriver\PluginManager`
  - `VuFind\RecordDriver\SemanticSearch`

### 3.9 EmbeddingServiceTest
- Purpose: Verify embedding API request/response/error handling.
- Main methods to cover: `embed(string $text): ?array`.
- Example structure:
  - Mock `HttpClient` + `Response`.
  - Assert URI/method/body/headers and parsed embedding array.
  - Assert `null` on failed response and thrown exception.
- Required mock dependencies:
  - `Laminas\Http\Client`
  - `Laminas\Http\Response`
  - Test-local `VuFind\Log\LoggerAwareInterface` shim (branch compatibility).

### 3.10 EmbeddingServiceFactoryTest
- Purpose: Verify embedding service factory wiring + defaults.
- Main methods to cover: `EmbeddingServiceFactory::__invoke()`.
- Example structure:
  - Mock config object with explicit semantic settings.
  - Mock `VuFindHttp\HttpService->createClient()`.
  - Assert service internals via reflection.
  - Repeat with empty config and assert default values.
- Required mock dependencies:
  - `VuFind\Config\ConfigManagerInterface`
  - `VuFindHttp\HttpService`
  - `Laminas\Http\Client`
  - Test-local `VuFind\Log\LoggerAwareInterface` shim (branch compatibility).

### 3.11 SemanticSearchControllerTest
- Purpose: Verify controller constructor sets semantic search class id.
- Main methods to cover: constructor behavior.
- Example structure:
  - Instantiate with `MockContainer`.
  - Assert protected `searchClassId` via reflection.
- Required mock dependencies:
  - `VuFindTest\Container\MockContainer`

### 3.12 SemanticSearchRecordControllerTest
- Purpose: Verify record controller source id is semantic.
- Main methods to cover: source-id binding.
- Example structure:
  - Instantiate with `MockContainer` and `Config`.
  - Assert protected `sourceId` via reflection.
- Required mock dependencies:
  - `VuFindTest\Container\MockContainer`
  - `VuFind\Config\Config`

### 3.13 BackendTest (VuFindSearch SemanticSearch)
- Purpose: Verify semantic backend query rewriting and embedding fallback behavior.
- Main methods to cover: `rawJsonSearch()`, `getEmbedding()`.
- Example structure:
  - Mock connector `search(ParamBag)` and assert:
    - semantic `q` built with vector similarity when embedding exists;
    - `qf/qt/mm` removed in semantic mode;
    - score present in `fl`;
    - normal query path retained when embedding unavailable.
  - Mock HTTP client responses for success/failure/exception.
- Required mock dependencies:
  - `VuFindSearch\Backend\Solr\Connector`
  - `Laminas\Http\Client`
  - `Laminas\Http\Response`
  - `VuFindSearch\Backend\Solr\QueryBuilder`

### 4 Run tests
- Only semantic tests
```
vendor/bin/phpunit -c module/VuFind/tests/phpunit.xml \
  module/VuFind/tests/unit-tests/src/VuFindTest/Related/SemanticSimilarTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Related/SemanticSimilarFactoryTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/SemanticSearchTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/RecordDriver/SemanticSearchFactoryTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Search/SemanticSearch/OptionsTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Search/SemanticSearch/ParamsTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Search/SemanticSearch/ResultsTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Search/Factory/SemanticSearchBackendFactoryTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Service/SemanticSearch/EmbeddingServiceTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Service/SemanticSearch/EmbeddingServiceFactoryTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Controller/SemanticSearchControllerTest.php \
  module/VuFind/tests/unit-tests/src/VuFindTest/Controller/SemanticSearchRecordControllerTest.php
```
- All tests
```
vendor/bin/phing phpunit  
```