# Current Hybrid Search Architecture and Implementation

## 1. Purpose and Scope

This document describes the current hybrid search implementation in this VuFind workspace, based on the active code paths and configuration.

It focuses on how lexical retrieval, vector retrieval, Solr native Reciprocal Rank Fusion (RRF), and multi-vector indexing currently work together.

The target audience is developers and architects maintaining or extending the search platform.

## 2. Current State of the Search Architecture

The current search stack exposes three related search modes:

- Standard Solr search for lexical retrieval.
- SemanticSearch for pure vector retrieval.
- HybridSearch for combined lexical and vector retrieval fused by Solr RRF.

At the application layer, HybridSearch is registered as an independent VuFind search source and appears in the search box and combined-search sections.

At the backend layer, the main components are:

- `VuFind\Search\Factory\HybridSearchBackendFactory`
- `VuFindSearch\Backend\HybridSearch\Backend`
- `VuFind\Service\SemanticSearch\EmbeddingService`
- Solr `/combined` handled by `solr.CombinedQuerySearchHandler`
- Solr `CombinedQueryComponent`

The execution model is:

1. VuFind receives a user query through the HybridSearch source.
2. The HybridSearch backend generates an embedding for the user text.
3. The backend builds one lexical subquery and one vector subquery.
4. The backend submits both subqueries in a single JSON request to Solr `/combined`.
5. Solr executes both branches and fuses the ranked lists with RRF.
6. VuFind renders the merged result list using the HybridSearch record driver.

This implementation is already aligned with Solr's native combined-query flow. It is not a client-side merge done in PHP.

## 3. Main Runtime Components

### 3.1 VuFind Search Registration

Hybrid search is exposed as a first-class source in the UI:

- `config/vufind/searchbox.ini` registers `HybridSearch` in the search dropdown.
- `config/vufind/combined.ini` defines the label and display block for combined results.
- `module/VuFind/src/VuFind/Search/BackendRegistry.php` maps `HybridSearch` to its backend factory.

This makes hybrid retrieval a separate route and backend identity rather than a mode bolted onto the default Solr backend.

### 3.2 Hybrid Backend Factory

`VuFind\Search\Factory\HybridSearchBackendFactory` loads shared vector-search settings from `embedding.ini` and wires them into the backend.

The relevant configuration currently includes:

- `vector_field`
- `rrf_k`
- `topK_vector`
- `min_score`
- `default_core`

The same EmbeddingService used by SemanticSearch is reused by HybridSearch.

### 3.3 Embedding Service

`VuFind\Service\SemanticSearch\EmbeddingService` sends an OpenAI-compatible embedding request:

```json
{
  "input": "quantum information retrieval",
  "model": "qwen3-embedding:4b",
  "encoding_format": "float"
}
```

Expected response shape:

```json
{
  "data": [
    {
      "embedding": [0.0123, -0.0311, 0.4421]
    }
  ]
}
```

Optional headers may also be sent:

- `Authorization: Bearer ...`
- `HTTP-Referer`
- `X-Title`

If the embedding API call fails or returns an unexpected body, the service returns `null`.

### 3.4 Solr Combined Query Handler

The biblio core defines:

```xml
<requestHandler name="/combined" class="solr.CombinedQuerySearchHandler">
  <lst name="defaults">
    <str name="df">allfields</str>
    <str name="echoParams">explicit</str>
  </lst>
  <arr name="last-components">
    <str>highlight</str>
  </arr>
</requestHandler>

<searchComponent class="solr.CombinedQueryComponent" name="combined_query">
  <int name="maxCombinerQueries">2</int>
</searchComponent>
```

This handler is the key integration point for native RRF. VuFind posts a JSON body directly to `/combined` instead of expressing the whole hybrid query through traditional URL parameters.

## 4. How the Hybrid Search Feature Works

HybridSearch combines two retrieval strategies built from the same user query.

### 4.1 Lexical Branch

The lexical subquery is generated through the normal Solr `QueryBuilder`, which means it inherits the existing query parser behavior, search specs, field boosts, and filters used by the standard VuFind Solr backend.

In practice, the backend extracts the Lucene query string produced by the QueryBuilder and inserts it into the combined-query JSON under:

```json
"queries": {
  "lexical": {
    "lucene": {
      "query": "title:quantum information retrieval"
    }
  }
}
```

The exact lexical query varies with the configured search specs and user request.

### 4.2 Vector Branch

The vector branch uses the embedding returned by the EmbeddingService and submits a kNN query to Solr.

In the current code, the vector branch is always built as a nested-aware parent query:

```json
"vector": {
  "parent": {
    "which": "*:* -_nest_path_:*",
    "score": "max",
    "query": {
      "knn": {
        "f": "vector_multivalued",
        "topK": 10,
        "query": "[0.0123,-0.0311,0.4421]",
        "childrenOf": "*:* -_nest_path_:*"
      }
    }
  }
}
```

This is important for multi-vector retrieval:

- the kNN search runs against vector-bearing child fragments,
- Solr converts child matches back to their parent bibliographic record,
- the parent score is the maximum child match score.

### 4.3 Combined Solr Request

A simplified request body built by the current backend looks like this:

```json
{
  "queries": {
    "lexical": {
      "lucene": {
        "query": "title:quantum information retrieval"
      }
    },
    "vector": {
      "parent": {
        "which": "*:* -_nest_path_:*",
        "score": "max",
        "query": {
          "knn": {
            "f": "vector_multivalued",
            "topK": 10,
            "query": "[0.0123,-0.0311,0.4421]",
            "childrenOf": "*:* -_nest_path_:*"
          }
        }
      }
    }
  },
  "limit": 10,
  "offset": 0,
  "fields": "*,score",
  "params": {
    "combiner": true,
    "combiner.query": ["lexical", "vector"],
    "combiner.algorithm": "rrf",
    "combiner.rrf.k": 60
  }
}
```

If standard filter queries are present, the backend adds them as a top-level `filter` section.

## 5. Reciprocal Rank Fusion Implementation

The current implementation uses Solr native RRF through the combined-query handler.

The backend explicitly sets:

- `combiner = true`
- `combiner.query = ["lexical", "vector"]`
- `combiner.algorithm = "rrf"`
- `combiner.rrf.k = <configured value>`

### 5.1 What RRF Merges

RRF does not merge raw BM25 scores and vector similarity scores directly.

Instead, it merges result positions from:

- the lexical ranked list,
- the vector ranked list.

For a document `d`, the fused score is conceptually:

$$
RRF(d) = \sum_r \frac{1}{k + rank_r(d)}
$$

Where:

- `r` is a ranked list,
- `rank_r(d)` is the 1-based position of document `d` in that list,
- `k` is the smoothing constant.

### 5.2 Why This Matters Here

This choice is appropriate because lexical and vector scores are not on the same numeric scale.

- lexical ranking is driven by text relevance,
- vector ranking is driven by nearest-neighbor similarity,
- RRF avoids fragile score normalization rules.

### 5.3 Practical Effect

The merged ranking favors records that appear near the top of both branches.

Typical behavior is:

- exact lexical matches remain strong,
- semantically related items can still surface even with weaker term overlap,
- records that perform well in both branches are promoted the most.

## 6. Multiple Vectors per Indexed Item

### 6.1 Current Support Model

The current schema defines both:

```xml
<field name="vector" type="knn_vector" indexed="true" stored="true" />
<field name="vector_multivalued" type="knn_vector" indexed="true" stored="true" multiValued="true" />
```

The local runtime configuration currently points search to the multi-valued field:

```ini
[Embedding]
vector_field = "vector_multivalued"
```

This means the active environment is using the multi-vector retrieval path.

### 6.2 How Solr Stores Multiple Embeddings

With recent Solr dense-vector support, a multi-valued vector field is internally represented through nested child documents. For validation work on multi-vector fields, the recommended approach is to use a Solr beta build in a test environment first, especially when verifying nested-document behavior, indexing compatibility, and kNN retrieval over `vector_multivalued`. That is why the schema now includes:

```xml
<field name="_root_" type="string" indexed="true" stored="false" docValues="false" />
<field name="_nest_path_" type="_nest_path_" indexed="true" stored="true" />
```

Conceptually, a bibliographic record with multiple embeddings behaves like this:

```json
{
  "id": "rec-123",
  "title": "Example title",
  "vector_multivalued": [
    [0.11, -0.03, 0.22],
    [0.09, 0.14, -0.08],
    [0.41, -0.1, 0.07]
  ]
}
```

Internally, Solr can expose that layout as parent and child fragments when queried with `[child]`.

Example inspection request:

```http
GET /solr/biblio/select?q=id:rec-123&fl=id,vector_multivalued,[child]
```

Simplified response shape:

```json
{
  "response": {
    "docs": [
      {
        "id": "rec-123",
        "vector_multivalued": [
          [0.11, -0.03, 0.22],
          [0.09, 0.14, -0.08],
          [0.41, -0.1, 0.07]
        ],
        "_childDocuments_": [
          { "_nest_path_": "/vector_multivalued" },
          { "_nest_path_": "/vector_multivalued" },
          { "_nest_path_": "/vector_multivalued" }
        ]
      }
    ]
  }
}
```

The exact child payload can vary, but the important point is that multiple embeddings are resolved through nested-document mechanics.

### 6.3 How Retrieval Works with Multiple Embeddings

When a query embedding is searched against `vector_multivalued`:

1. Solr compares the query vector against each vector fragment under the record.
2. The `knn` query ranks the best-matching child fragments.
3. The surrounding `parent` query returns the bibliographic parent record.
4. `score = max` keeps the best child similarity as the vector branch score for that parent.

This means a record can match semantically through any one of its embeddings.

That is the core mechanism behind multiple embeddings per item in the current system.

## 7. Query Flow and Ranking Strategy

The current end-to-end flow is:

1. User submits a query to HybridSearch.
2. The backend reads the text from the `Query` object.
3. The EmbeddingService calls the embedding API.
4. The QueryBuilder creates the lexical query string.
5. The backend creates a nested-aware kNN vector query.
6. The backend posts a combined JSON request to Solr `/combined`.
7. Solr executes both branches.
8. Solr fuses both rankings with RRF.
9. VuFind renders the final parent records.

### 7.1 Ranking Strategy

The ranking strategy is rank-based, not score-based:

- lexical ordering comes from the standard text query,
- vector ordering comes from the kNN nearest neighbors,
- the final order comes from RRF over both rank lists.

### 7.2 Highlighting Strategy

The current backend enables highlighting and sets `hl.q` to the lexical query string.

This means highlight snippets are still lexical in nature even when the final rank was strongly influenced by vector retrieval.

That is a practical and understandable choice, but it also means the UI does not explain semantic matches directly.

## 8. Indexing Structure and Vector Organization

### 8.1 Dense Vector Field Type

The current biblio schema declares:

```xml
<fieldType name="knn_vector" class="solr.DenseVectorField"
           vectorDimension="2560"
           similarityFunction="cosine" />
```

Important implications:

- vectors must have dimension 2560,
- the embedding model used at index and query time must match that dimension exactly,
- similarity is currently configured as cosine.

### 8.2 Current Local Runtime Settings

The active local override is:

```ini
[Embedding]
embedding_api_url = "http://172.16.115.105:11434/v1/embeddings"
vector_field      = "vector_multivalued"
topK              = 20
default_core      = "biblio"
min_score         = 0.0
model             = "qwen3-embedding:4b"
rrf_k             = 60
encoding_format   = "float"
query_parser      = "knn"
```

This confirms the current environment is configured around:

- a remote Ollama-compatible embeddings endpoint,
- a multi-valued vector field,
- cosine similarity,
- RRF with `k = 60`.

### 8.3 Parent and Child Visibility

The standard Solr backend currently injects:

```text
fq=-_nest_path_:*
```

This protects standard lexical search, counts, and facets from accidentally returning internal child fragments.

For vector retrieval, HybridSearch and SemanticSearch intentionally switch to parent-query constructs so the result set remains at the bibliographic-record level.

## 9. Required Configuration and Example Settings

### 9.1 Solr Schema

Minimum current requirements:

```xml
<fieldType name="knn_vector" class="solr.DenseVectorField"
           vectorDimension="2560"
           similarityFunction="cosine" />

<fieldType name="_nest_path_" class="solr.NestPathField" />

<field name="_root_" type="string" indexed="true" stored="false" docValues="false" />
<field name="_nest_path_" type="_nest_path_" indexed="true" stored="true" />

<field name="vector" type="knn_vector" indexed="true" stored="true" />
<field name="vector_multivalued" type="knn_vector" indexed="true" stored="true" multiValued="true" />
```

### 9.2 Solr Handler

```xml
<requestHandler name="/combined" class="solr.CombinedQuerySearchHandler">
  <lst name="defaults">
    <str name="df">allfields</str>
    <str name="echoParams">explicit</str>
  </lst>
  <arr name="last-components">
    <str>highlight</str>
  </arr>
</requestHandler>

<searchComponent class="solr.CombinedQueryComponent" name="combined_query">
  <int name="maxCombinerQueries">2</int>
</searchComponent>
```

### 9.3 Recommended `embedding.ini`

For a clear and explicit hybrid setup, use:

```ini
[Embedding]
embedding_api_url = "http://your-embedding-service/v1/embeddings"
embedding_api_key = ""
embedding_site_url = ""
embedding_app_name = ""

model = "your-embedding-model"
encoding_format = "float"

default_core = "biblio"
vector_field = "vector_multivalued"

topK = 20
topK_vector = 20
rrf_k = 60
min_score = 0.0
query_parser = "knn"
```

Notes:

- `topK` is used by SemanticSearch.
- `topK_vector` is used by HybridSearch.
- `query_parser` is ignored by HybridSearch in the current code because the hybrid vector branch always uses `knn`.

### 9.4 Search Source Registration

```ini
type[] = VuFind
target[] = HybridSearch
label[] = Hybrid
group[] = false
```

### 9.5 Combined Results Block

```ini
[HybridSearch]
label = "Hybrid Search"
sublabel = "Reciprocal Rank Fusion results"
more_link = "More hybrid results"
limit = 10
ajax = true
```

## 10. Practical Implementation Notes

### 10.1 Empty Query Behavior

If the incoming query string is empty, HybridSearch does not attempt embedding generation. It falls back to the parent Solr backend behavior.

### 10.2 Embedding Failure Behavior

For non-empty queries, the current HybridSearch backend does not fall back to lexical-only retrieval when embedding generation fails.

Instead, it throws a backend exception.

This is an important operational characteristic.

### 10.3 Filters and Facets

The backend forwards filter queries into the combined JSON request. In practice, this lets facet selections and other filters continue to constrain the merged result set.

### 10.4 Returned Fields

If no explicit field list is provided, the backend requests:

```text
*,score
```

Because the current schema stores `vector` and `vector_multivalued`, this can increase response size and may expose raw vectors unless downstream field handling trims them.

That is a maintainability and performance consideration.

## 11. Current Limitations, Considerations, and Trade-offs

### 11.1 `min_score` Is Not Enforced in HybridSearch

`min_score` is loaded into the HybridSearch backend, but the current hybrid implementation does not apply it to the vector branch.

Implication:

- hybrid vector candidates are limited by `topK_vector`,
- they are not currently pruned by a minimum similarity threshold in the backend.

### 11.2 `topK_vector` Must Be Set Explicitly

The local configuration currently sets `topK = 20`, but HybridSearch reads `topK_vector` instead.

If `topK_vector` is omitted, HybridSearch falls back to its default value of `10`.

Implication:

- SemanticSearch and HybridSearch can silently use different vector candidate limits.

### 11.3 Query Parser Setting Does Not Affect HybridSearch

Although `query_parser` exists in shared configuration, HybridSearch currently always builds a `knn` vector branch.

Implication:

- `vectorSimilarity` can be used in SemanticSearch,
- it is not selectable for HybridSearch at the moment.

### 11.4 Stored Vectors Increase Cost

The current schema stores both `vector` and `vector_multivalued`.

Trade-off:

- easier inspection and debugging,
- larger stored documents,
- potentially larger response payloads when `fields = *,score`.

### 11.5 Hybrid Lexical Branch Does Not Add an Explicit Child-Doc Exclusion Filter

The standard Solr backend globally excludes `-_nest_path_:*`, but HybridSearch constructs its own combined request and does not currently inject that exclusion as a top-level global filter.

In practice, correctness currently relies on:

- the vector branch using a parent query,
- vector child fragments not behaving like normal lexical records.

This works, but the assumption should be documented.

### 11.6 UI Explanations Remain Lexical

Highlighting is driven by the lexical query, not by the vector branch.

Trade-off:

- users still get familiar snippets,
- the UI does not explain why a semantically matched record was retrieved.

### 11.7 Documentation and Tests Need Alignment

Some older guides and at least part of the unit-test expectation set still reflect an earlier, flatter vector-query structure.

The current runtime code uses a nested parent-wrapped `knn` structure for multi-vector support.

This is a maintenance risk because future changes may be evaluated against outdated assumptions.

### 11.8 Beta-Version Validation for Multi-Vector Support

Multi-vector field support should be validated against a Solr beta version in a non-production environment before being treated as a stable deployment baseline.

Trade-off:

- access to the latest dense-vector and nested-document behavior needed for testing `vector_multivalued`,
- higher risk of behavior changes between beta and final releases,
- additional regression testing required before production rollout.

## 12. Future Improvement Opportunities

The current implementation is functional and structurally sound, but the following improvements would reduce operational ambiguity:

1. Apply `min_score` in HybridSearch so vector recall can be bounded by similarity as well as `topK_vector`.
2. Align configuration semantics by documenting or consolidating `topK` versus `topK_vector`.
3. Decide whether vectors really need to be `stored="true"`; if not, change them to `stored="false"` to reduce payload size.
4. Add an explicit global child-document exclusion filter to the hybrid combined request for defensive consistency.
5. Expose branch-level diagnostics, such as lexical rank and vector rank, to make fused results easier to debug.
6. Add UI affordances explaining semantic contribution, especially when a record ranks highly with weak lexical overlap.
7. Update older hybrid documentation and tests so they match the current nested multi-vector implementation.

## 13. Final Summary

The current hybrid search architecture is a server-side fusion pipeline built on Solr native combined queries and RRF.

Its defining characteristics are:

- one lexical branch generated by the normal QueryBuilder,
- one vector branch generated from an embedding API call,
- Solr-native RRF for result fusion,
- active support for multiple embeddings per record through `vector_multivalued` and parent/child query mechanics.

Multiple embeddings are handled by searching vector-bearing child fragments and promoting the parent bibliographic record with the maximum child score before RRF is applied.

Keyword and vector results are therefore merged in two stages:

1. multi-vector child matches are collapsed to the parent record on the vector side,
2. lexical and vector parent rankings are fused through RRF.

This is a strong foundation for maintainable hybrid retrieval, but the current code would benefit from clearer configuration boundaries, explicit threshold behavior, and tighter alignment between implementation, tests, and documentation.
