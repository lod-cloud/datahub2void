<?php

about($uris->tags, 'skos:ConceptScheme');
property('skos:prefLabel', 'Tags used on LOD Cloud datasets');
foreach ($tags as $tag) {
  about($uris->tag($tag), 'tag:Tag');
  property('tag:name', $tag);
  rel('skos:inScheme', $uris->tags);
}
foreach ($datasets as $id => $dataset) {
  about($uris->dataset($id));
  foreach ($dataset->tags as $tag) {
    rel('tag:taggedWithTag', $uris->tag($tag));
  }
}
