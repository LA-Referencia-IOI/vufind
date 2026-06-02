# Faceting on `/combined` Hybrid Search Can Throw `ArrayIndexOutOfBoundsException`

## Description

Executing field faceting on the `/combined` hybrid search handler can fail with an internal server error (`500`) caused by an `ArrayIndexOutOfBoundsException`.

The exception occurs during facet processing (`facet.field`) when using hybrid search results, apparently involving `BitDocSet` and `DocValuesFacets`.

This makes faceting unusable for some hybrid search queries.

---

## Environment

- Solr nightly `10.1.0-SNAPSHOT`
- `/combined` request handler
- Hybrid search combining:
  - lexical query
  - vector KNN query
  - `combiner=true`
  - `combiner.algorithm=rrf`

---

## Error

```text
org.apache.solr.common.SolrException: Exception during facet.field: topic_facet
```

### Root Cause

```text
Caused by: java.lang.ArrayIndexOutOfBoundsException: Index 33 out of bounds for length 10
    at org.apache.lucene.util.FixedBitSet.nextSetBitInRange(FixedBitSet.java:355)
    at org.apache.lucene.util.FixedBitSet.nextSetBit(FixedBitSet.java:335)
    at org.apache.solr.search.BitDocSet$2.nextDoc(BitDocSet.java:291)
    at org.apache.solr.request.DocValuesFacets.accumMultiGeneric(DocValuesFacets.java:419)
    at org.apache.solr.request.DocValuesFacets.accumMulti(DocValuesFacets.java:402)
    at org.apache.solr.request.DocValuesFacets.getCounts(DocValuesFacets.java:184)
    at org.apache.solr.request.SimpleFacets.getTermCounts(SimpleFacets.java:650)
```

---

## Full Stack Trace

```text
ERROR (qtp998062648-71-null-1088) [ x:biblio t:null-1088] o.a.s.s.HttpSolrCall 500 Exception
org.apache.solr.common.SolrException: Exception during facet.field: topic_facet
...
Caused by: java.lang.ArrayIndexOutOfBoundsException: Index 33 out of bounds for length 10
...
```

(Complete stack trace attached below)

---

## Observed Behavior

- Hybrid search itself returns results correctly.
- Adding faceting causes a `500` server error.
- The exception appears related to:
  - combined result sets
  - BitDocSet iteration
  - DocValues faceting
  - RRF hybrid ranking

---

## Expected Behavior

Faceting should work normally on `/combined` hybrid search responses without throwing exceptions.

---

## Possible Cause

The issue may be related to inconsistent document set sizing after RRF combination, where facet processing iterates beyond the internal `BitDocSet` bounds.

The stack trace suggests a mismatch between:

- combined result document IDs
- internal bitset size
- facet accumulator iteration

---

## Impact

This bug prevents use of:

- facet navigation
- aggregations
- UI filtering

on hybrid search endpoints using `/combined`.

Applications relying on faceted hybrid search cannot function correctly.

---

## Additional Notes

This may be related to other inconsistencies observed with `/combined`, including:

- incorrect `numFound`
- `numFoundExact=false`
- pagination inconsistencies

Potentially indicating issues in combined result set accounting.
