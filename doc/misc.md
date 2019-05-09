To start two nodes: `docker-compose up`.

Data are stored here: `/usr/share/elasticsearch/data`.

Mapping for phrase suggester:

```bash
# to change an exisitng index. need to close it before and open after.
curl -X PUT "localhost:9200/bank/_settings?pretty" -H 'Content-Type: application/json' -d'
{   
    "settings": {
        "index": {
          "analysis": {
            "analyzer": {
              "trigram": {
                "type": "custom",
                "tokenizer": "standard",
                "filter": ["shingle"]
              },
              "reverse": {
                "type": "custom",
                "tokenizer": "standard",
                "filter": ["reverse"]
              }
            },
            "filter": {
              "shingle": {
                "type": "shingle",
                "min_shingle_size": 2,
                "max_shingle_size": 3
              }
            }
          }
        }
    }
}
'
  
# to specify the mapping using the custom analyzer
curl -X PUT "localhost:9200/bank/_mapping?pretty" -H 'Content-Type: application/json' -d'
{
    "properties": {
      "address": {
        "type": "text",
        "fields": {
          "trigram": {
            "type": "text",
            "analyzer": "trigram"
          },
          "reverse": {
            "type": "text",
            "analyzer": "reverse"
          }
        }
      }
    }
}'
```

To test an analyzer:

```bash
curl "localhost:9200/bank/_analyze?pretty" -H 'Content-Type: application/json' -d'
{
  "analyzer": "trigram",
  "text": "Some city New-York"
}'
```

To suggest phrases:

```bash
curl -X POST "localhost:9200/bank/_search?pretty" -H 'Content-Type: application/json' -d'
{
  "suggest": {
    "text": "880 Holmes Lane",
    "simple_phrase": {
      "phrase": {
        "field": "address.trigram",
        "size": 1,
        "gram_size": 3,
        "direct_generator": [ {
          "field": "address.trigram",
          "suggest_mode": "always"
        } ],
        "highlight": {
          "pre_tag": "<em>",
          "post_tag": "</em>"
        }
      }
    }
  }
}
'

```

```bash
curl -X POST "localhost:9200/test/_search?pretty" -H 'Content-Type: application/json' -d'
{
  "suggest": {
    "text": "noble prize",
    "simple_phrase": {
      "phrase": {
        "field": "title.trigram",
        "size": 1,
        "gram_size": 3,
        "direct_generator": [ {
          "field": "title.trigram",
          "suggest_mode": "always"
        } ],
        "highlight": {
          "pre_tag": "<em>",
          "post_tag": "</em>"
        }
      }
    }
  }
}
'
```
