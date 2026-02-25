# How to Enable Semantic Search and Semantic Similar Items

This guide explains the necessary configuration changes to enable Semantic Search and Semantic Similar Items in VuFind.

## Prerequisites

Solr Schema Preparation (`schema.xml`). For Solr to handle dense vectors, we must define:

- New Field Type: `knn_vector`

A field type for _k-Nearest Neighbors_ (k-NN) vector search.

```xml
<fieldType name="knn_vector" class="solr.DenseVectorField"
           vectorDimension="768"
           similarityFunction="dot_product"/>
```

- **`vectorDimension="768"`** — Must match the embedding model dimension.
- **`similarityFunction="dot_product"`** — Defines how similarity between vectors is computed.

- New Field: `vector`

This field stores the document embeddings:

```xml
<field name="vector" type="knn_vector" indexed="true" stored="false"/>
```

- **`indexed="true"`** — Enables search operations.
- **`stored="false"`** — Reduces storage overhead when retrieving results.

> 💡 The vector data is not returned in search results, only used for scoring.

## 1. Enabling Semantic Search

To enable the Semantic Search feature, you will need to update a few configuration files.

### 1.1 `semanticsearch.ini`

Create or edit the `local/config/vufind/semanticsearch.ini` file and fill it with valid values for your environment:

```ini
[SemanticSearch]
embedding_api_url = "http://localhost:8000/v1/embeddings"
vector_field      = "vector"
topK              = 10
default_core      = "biblio"
min_score         = 0.0
model             = "embaas/sentence-transformers-multilingual-e5-large"
encoding_format   = "float"
user              = "user_example"
```

### 1.2 `searchbox.ini`

Add the following to your `local/config/vufind/searchbox.ini` to make Semantic Search available in the VuFind search box dropdown:

```ini
type[] = VuFind
target[] = SemanticSearch
label[] = Semantic
group[] = false
```

### 1.3 `config.ini`

To set Semantic Search as the default search experience, change the `defaultModule` setting in `config.ini`:

```ini
defaultModule   = SemanticSearch
```

### 1.4 `combined.ini`

_(Optional: Combined Search)_
If you prefer to use combined search, set `defaultModule = Combined` in `config.ini`, and add the semantic search block to `local/config/vufind/combined.ini`:

```ini
[SemanticSearch]
label = "Semantic Search"
sublabel = "Vector-based similarity results"
more_link = "More semantic results"
limit = 10
ajax = true
```

> **Note:** Remember to remove any unused data source configurations from `combined.ini` (e.g., `[Summon]` or `[EDS]`) to avoid unnecessary requests.

---

## 2. Enabling Semantic Similar Items

To display semantic similar items on the record detail page, you will need to register the related items module.

### 2.1 `config.ini`

Edit `local/config/vufind/config.ini` and add `related[] = "SemanticSimilar"` under the `[Record]` section. A typical setup with both standard and semantic similar items enabled will look like this:

```ini
[Record]
related[] = "Similar"
related[] = "SemanticSimilar"
```

This will activate the `SemanticSimilar` module, displaying records related by vector embeddings when a user views a specific record detail page.
