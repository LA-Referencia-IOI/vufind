# Hybrid Search `/combined` Returns Incorrect `numFound` and Ignores `minExactCount`

## Description

When using Solr hybrid search with the `/combined` handler and the `rrf` combiner, the response returns a `numFound` value that does not match the actual number of accessible results.

Additionally, setting `minExactCount` does not change the behavior: `numFoundExact` remains `false`, indicating the count is still estimated.

This causes pagination inconsistencies in applications consuming the API.

## Environment

- Solr nightly `10.1.0-SNAPSHOT`
- `/combined` request handler
- Hybrid search using:
  - lexical query
  - vector KNN query
  - `combiner=true`
  - `combiner.algorithm=rrf`

---

## Reproduction

### Request with limit=5

```bash
curl -X POST "http://localhost:8983/solr/biblio/combined" \
  -H "Content-Type: application/json" \
  -d '{
    "queries": {
      "lexical": {
        "lucene": {
          "query": "remote"
        }
      },
      "vector": {
        "knn": {
          "f": "vector",
          "topK": 10,
          "query": "[ ... ]"
        }
      }
    },
    "limit": 5,
    "fields": ["id", "text", "score"],
    "params": {
      "combiner": true,
      "combiner.query": ["lexical", "vector"],
      "combiner.algorithm": "rrf",
      "combiner.rrf.k": 60
    }
  }'
```

### Response

```json
"response":{
  "numFound":12,
  "start":0,
  "maxScore":0.032786883,
  "numFoundExact":false,
  "docs":[
    {
      "id":"Deposita_a49459c17b467e4644495ef17743413f",
      "score":0.032786883
    },
    {
      "id":"Deposita_f38467af78bea565d49e46e7a78bc020",
      "score":0.016129032
    },
    {
      "id":"Deposita_40b28c8aa65045ccf22ec5514609cf41",
      "score":0.016129032
    },
    {
      "id":"Deposita_bcfbe2121e96c16cd7648170e6f581a1",
      "score":0.015873017
    },
    {
      "id":"Deposita_b02ae19e94561bcdf0442c9bd5932d71",
      "score":0.015873017
    }
  ]
}
```

---

### Request with limit=10

```bash
curl -X POST "http://localhost:8983/solr/biblio/combined" \
  -H "Content-Type: application/json" \
  -d '{
    "queries": {
      "lexical": {
        "lucene": {
          "query": "remote"
        }
      },
      "vector": {
        "knn": {
          "f": "vector",
          "topK": 10,
          "query": "[ ... ]"
        }
      }
    },
    "limit": 10,
    "fields": ["id", "text", "score"],
    "params": {
      "combiner": true,
      "combiner.query": ["lexical", "vector"],
      "combiner.algorithm": "rrf",
      "combiner.rrf.k": 60
    }
  }'
```

### Response

```json
"response":{
  "numFound":12,
  "start":0,
  "maxScore":0.032786883,
  "numFoundExact":false,
  "docs":[
    { "id":"Deposita_a49459c17b467e4644495ef17743413f" },
    { "id":"Deposita_bcfbe2121e96c16cd7648170e6f581a1" },
    { "id":"Deposita_f38467af78bea565d49e46e7a78bc020" },
    { "id":"Deposita_40b28c8aa65045ccf22ec5514609cf41" },
    { "id":"Deposita_b02ae19e94561bcdf0442c9bd5932d71" },
    { "id":"Deposita_6e3dbb471db7b6d1364095e1410dc8d6" },
    { "id":"Deposita_351041e6853db20b3ef4754a5d4dc18b" },
    { "id":"Deposita_8f1e0370a5ec12698289a186fabf9bdf" },
    { "id":"Deposita_06cdd28fb957103e19328d5cc3b061d7" },
    { "id":"Deposita_859ceaeaa2a0a1d2472cd15562dfe0cd" }
  ]
}
```

---

## Observed Behavior

Although `numFound=12`, pagination allows retrieving more results.

Example:

- Page 1 returns 10 results
- Page 2 returns 7 additional results
- Total accessible results = 17

This means `numFound` is lower than the actual result set.

---

## Additional Test with `minExactCount`

According to the documentation for `minExactCount`, setting a sufficiently high value should force Solr to compute an exact hit count.

Reference:
[Solr minExactCount Parameter Documentation](https://solr.apache.org/guide/solr/latest/query-guide/common-query-parameters.html?utm_source=chatgpt.com#minexactcount-parameter)

### Request

```bash
curl -X POST "http://localhost:8983/solr/biblio/combined?minExactCount=1000000" \
  -H "Content-Type: application/json" \
  -d '{
    "queries": {
      "lexical": {
        "lucene": {
          "query": "remote"
        }
      },
      "vector": {
        "knn": {
          "f": "vector",
          "topK": 10,
          "query": "[ ... ]"
        }
      }
    },
    "limit": 10,
    "fields": ["id", "text", "score"],
    "params": {
      "combiner": true,
      "combiner.query": ["lexical", "vector"],
      "combiner.algorithm": "rrf",
      "combiner.rrf.k": 60
    }
  }'
```

### Response

```json
"numFoundExact": false
```

---

## Expected Behavior

- `numFound` should match the actual number of retrievable combined results.
- OR the response should clearly document that the combined result count is approximate.
- `minExactCount` should either:
  - work correctly with `/combined` hybrid queries, or
  - be documented as unsupported for combined/RRF searches.

---

## Impact

This causes pagination bugs in clients consuming Solr hybrid search APIs:

- UI shows incorrect total results
- Users can navigate beyond the reported number of hits
- Pagination components become inconsistent
- Search frameworks relying on `numFound` break expected behavior

## References

- https://kandasearch.com/en/blogs/0476a007-8cd2-411b-9709-a15a926b5c7f
- https://solr.apache.org/guide/solr/latest/query-guide/common-query-parameters.html
