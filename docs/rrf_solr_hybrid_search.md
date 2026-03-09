# Reciprocal Rank Fusion (RRF) in Solr Hybrid Search

## Overview

Reciprocal Rank Fusion (RRF) is a ranking aggregation algorithm used in
hybrid search systems. It combines multiple ranked result lists into a
single ranking. In search platforms like Apache Solr, RRF is commonly
used to merge results from different retrieval approaches such as
keyword search and semantic (vector) search.

Hybrid search typically integrates: - Lexical search (BM25 or keyword
matching) - Semantic search (vector similarity using embeddings)

Each approach produces its own ranked list of results. RRF merges these
rankings to produce a final ranking.

------------------------------------------------------------------------

## Why RRF is Used in Hybrid Search

Hybrid search systems often produce scores that are not directly
comparable. For example:

-   BM25 scores may range from 0--10 or higher
-   Vector similarity scores often range from 0--1

Instead of merging scores directly, RRF works with ranking positions.
This avoids the need for score normalization.

Advantages of RRF: - No score normalization required - Robust when
combining heterogeneous search systems - Simple to implement - Strong
performance in information retrieval evaluations

------------------------------------------------------------------------

## RRF Formula

The RRF score for a document is calculated as:

RRF(d) = Σ 1 / (k + rank_r(d))

Where: - d = document - R = set of ranked lists - rank_r(d) = rank of
document d in result list r - k = constant parameter (commonly 60)

------------------------------------------------------------------------

## Example

Suppose two search systems return the following rankings:

  Rank   Keyword Search   Semantic Search
  ------ ---------------- -----------------
  1      Doc A            Doc B
  2      Doc B            Doc C
  3      Doc C            Doc A

Using k = 60:

### Doc A

-   keyword rank = 1 → 1/(60+1)
-   semantic rank = 3 → 1/(60+3)

Score ≈ 0.01639 + 0.01587 = 0.03226

### Doc B

-   keyword rank = 2 → 1/(60+2)
-   semantic rank = 1 → 1/(60+1)

Score ≈ 0.01613 + 0.01639 = 0.03252

Final ranking: 1. Doc B 2. Doc A 3. Doc C

------------------------------------------------------------------------

## Role of the k Parameter

The constant k controls how strongly top-ranked documents influence the
result.

Typical value: k = 60

Effects: - Higher k → smoother ranking differences - Lower k → stronger
influence of top-ranked results

------------------------------------------------------------------------

## RRF in Solr Hybrid Search

A typical Solr hybrid search pipeline works like this:

1.  User submits a query
2.  Keyword search executes using BM25
3.  Vector search executes using embeddings (kNN search)
4.  Each search method returns a ranked list
5.  RRF merges the rankings
6.  The final ranked list is returned to the user

Workflow:

User Query \| \|-- Keyword Search (BM25) \| -\> Ranked Results \| \|--
Vector Search (Embeddings) \| -\> Ranked Results \| ---\> Reciprocal
Rank Fusion -\> Final Ranking

------------------------------------------------------------------------

## Why RRF Works Well

A document does not need to be the top result in any individual system
to rank highly overall. If a document appears consistently across
multiple rankings, RRF can push it toward the top of the final list.

This property makes RRF particularly effective for hybrid search
systems.
