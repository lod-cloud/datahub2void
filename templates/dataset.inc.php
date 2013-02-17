<?php

about($uris->dataset($dataset_id), 'void:Dataset');
property('dcterms:title', $dataset->title);
property('skos:altLabel', @$dataset->extras->shortname);
property('dcterms:description', $dataset->notes_html, 'rdf:HTML');
property('dcterms:modified', $dataset->timestamp, 'xsd:dateTime');
rel('foaf:homepage', $dataset->url);
rel('foaf:page', $dataset->ckan_url);
property('void:triples', @$dataset->extras->triples, 'xsd:integer');
rel('dcterms:license', $uris->license($dataset->license_id));
rel('dcterms:license', @$dataset->extras->license_link);
rel('void:sparqlEndpoint', $dataset->sparql);
foreach ($dataset->dumps as $dump) {
  rel('void:dataDump', $dump['url']);
}
foreach ($dataset->examples as $example) {
  rel('void:exampleResource', $example['url']);
}
foreach ($dataset->other_resources as $resource) {
  rel('dcterms:relation', $resource['url']);
}
foreach ($dataset->contributors as $contributor) {
  rel('dcterms:contributor', ($contributor['role'] == 'author') ? $uris->author($dataset_id) : $uris->maintainer($dataset_id));
}
foreach ($dataset->outlinks as $target => $link_count) {
  rel('void:subset', $uris->linkset($dataset_id, $target));
}
foreach ($dataset->themes as $theme) {
  rel('dcterms:subject', $uris->theme($theme));
}
foreach ($dataset->tags as $tag) {
  rel('tag:taggedWithTag', $uris->tag($tag));
}
rev('void:subset', $uris->cloud);
// Contributor details
foreach ($dataset->contributors as $contributor) {
  about(($contributor['role'] == 'author') ? $uris->author($dataset_id) : $uris->maintainer($dataset_id));
  property('rdfs:label', $contributor['name']);
  if ($contributor['email']) {
    rel('foaf:mbox', 'mailto:' . $contributor['email']);
  }
  rel('foaf:homepage', $contributor['homepage']);
}
// Linkset details
foreach ($dataset->outlinks as $target => $link_count) {
  about($uris->linkset($dataset_id, $target), 'void:Linkset');
  rel('void:target', $uris->dataset($dataset_id));
  rel('void:target', $uris->dataset($target));
  property('void:triples', $link_count, 'xsd:integer');
}
// Resource details (same structure for all kinds of resources)
$resources = array_merge($dataset->dumps, $dataset->examples, $dataset->other_resources);
foreach ($resources as $details) {
  about($details['url']);
  property("dcterms:description", $details['description']);
  property("dcterms:format", $details['format']);
}
