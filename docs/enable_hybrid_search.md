# How to Enable Hybrid Search with Reciprocal Rank Fusion (RRF)

This guide explains the necessary configuration changes to enable Hybrid Search in VuFind, which combines lexical (keyword) and semantic (vector) search results using Solr 9.11+ native RRF.

## Prerequisites

### 1. Solr Schema Preparation (`schema.xml`)

Hybrid Search requires a vector field to store document embeddings.

- **Field Type**: `knn_vector`

```xml
<fieldType name="knn_vector" class="solr.DenseVectorField"
           vectorDimension="768"
           similarityFunction="dot_product"/>
```

- **Field**: `vector`

```xml
<field name="vector" type="knn_vector" indexed="true" stored="false"/>
```

### 2. Solr Request Handler (`solrconfig.xml`)

You must define the `/combined` handler and the `combined_query` component in your `biblio` core configuration.

```xml
<requestHandler name="/combined" class="solr.CombinedQuerySearchHandler">
    <lst name="defaults">
        <str name="df">allfields</str>
        <str name="echoParams">explicit</str>
    </lst>
</requestHandler>

<searchComponent class="solr.CombinedQueryComponent" name="combined_query">
    <int name="maxCombinerQueries">2</int>
</searchComponent>
```

---

## 1. Configuring Hybrid Search

### 1.1 `embedding.ini`

Create or edit `local/config/vufind/embedding.ini`. This shared file controls the embedding provider settings used by both Semantic Search and Hybrid Search.

```ini
[Embedding]
embedding_api_url = "https://your-openai-compatible-provider/v1/embeddings"
embedding_api_key = ""
embedding_site_url = ""
embedding_app_name = ""

vector_field      = "vector"
topK_vector       = 10
rrf_k             = 60
min_score         = 0.7
model             = "text-embedding-3-small"
encoding_format   = "float"
```

- **`embedding_api_url`**: Required. Full URL of an OpenAI-compatible embeddings endpoint.
- **`model`**: Required. Embedding model identifier supported by your provider.
- **`embedding_api_key`**: Optional in file if you prefer using the `EMBEDDING_API_KEY` environment variable.
- **`embedding_site_url`**: Optional. Useful for providers that accept `HTTP-Referer`.
- **`embedding_app_name`**: Optional. Useful for providers that accept `X-Title`. It can also be provided through `EMBEDDING_APP_NAME`.
- **`topK_vector`**: Number of results to retrieve from the vector sub-query.
- **`rrf_k`**: The constant `k` in the RRF formula (default is 60). Higher values reduce the weight of lower-ranked items.

> The embedding request payload now follows a provider-agnostic OpenAI-compatible contract and does not include a `user` field.

### 1.2 `searchbox.ini`

To make Hybrid Search available in the search box dropdown, add the following to `local/config/vufind/searchbox.ini`:

```ini
type[] = VuFind
target[] = HybridSearch
label[] = Hybrid
```

### 1.3 `config.ini`

To set Hybrid Search as the default search experience:

```ini
[Site]
defaultModule = HybridSearch
```

### 1.4 `combined.ini` (Optional)

If you use Combined Search, you can add Hybrid results as a section:

```ini
[HybridSearch]
label = "Hybrid Results"
sublabel = "Combined Keyword & Semantic results"
more_link = "More hybrid results"
limit = 10
ajax = true
```

---

## 2. Validation

1. **Restart Solr**: To reload the `solrconfig.xml` changes.
2. **Verify VuFind config**: Ensure `embedding_api_url` and `model` are explicitly set in `local/config/vufind/embedding.ini`.
3. **Reindex with Vectors**: Ensure your documents have embeddings in the `vector` field.
4. **Verify in UI**: Navigate to `/HybridSearch/Results` and confirm that rank scores are displayed and results reflect both keyword matches and semantic similarity.
