# Solr 9.11 `solrconfig.xml` Update Analysis

This document analyzes the changes made to `solr/vufind/biblio/conf/solrconfig.xml` to support Apache Solr 9.11. This upgrade enables the following search capabilities:

* **[Hybrid Search with Reciprocal Rank Fusion (RRF)](https://solr.apache.org/guide/solr/9_11/query-guide/json-combined-query-dsl.html)**: Combines lexical and dense-vector searches into one ranked result set.
* **[Multivalued dense vectors](https://solr.apache.org/guide/solr/9_11/query-guide/dense-vector-search.html)**: Supports multiple embeddings per document, useful for chunked or nested Retrieval-Augmented Generation (RAG) documents.

## 1. Updating `luceneMatchVersion`

```xml
<luceneMatchVersion>9.11</luceneMatchVersion>
```
**Analysis:**
This setting instructs the underlying Lucene engine to use version `9.11` specific behaviors, which can include:
- New analyzer, tokenizer, and query parser logic.
- Lucene 9.11 index format behavior.
- **Important:** As indicated in the Solr configuration comments, changing `luceneMatchVersion` often requires a **full re-index** of the data to ensure term tokenization and indexed structures correctly reflect the new Lucene rules. 

## 2. Explicitly Defining the `/select` Request Handler

```xml
+  <requestHandler name="/select" class="solr.SearchHandler">
+      <lst name="defaults">
+        <str name="echoParams">explicit</str>
+        <str name="df">allfields</str>
+      </lst>
+  </requestHandler>
```
**Analysis:**
The `/select` endpoint is the standard search handler for Solr queries. Declaring it explicitly avoids fallback errors or undefined behavior when the core does not otherwise define a default search endpoint.
- **`echoParams: explicit`**: Limits the parameter echo in Solr responses to only the parameters explicitly sent in the request, saving bandwidth.
- **`df: allfields`**: Specifies `allfields` as the default search field if no specific field is targeted by the query string.

## 3. Configuring Solr Request Handler for Hybrid Search

To support Hybrid Search utilizing Reciprocal Rank Fusion (RRF) in Solr 9.11, define the `/combined` request handler and the `combined_query` search component in `solrconfig.xml`. These explicitly tell Solr how to combine lexical and semantic queries.

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

**Analysis:**
- **`/combined` Handler**: Activating `solr.CombinedQuerySearchHandler` provides the endpoint capable of executing multiple underlying queries simultaneously—for instance, a standard keyword query and a `knn_vector` semantic query—and mixing their results.
- **`combined_query` Component**: Registers the necessary infrastructure to manage these hybrid searches. The `maxCombinerQueries=2` restricts it to blending exactly two query branches (lexical and semantic).
