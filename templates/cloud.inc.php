<?php

about($uris->cloud, 'void:Dataset');
property('dcterms:title', 'The LOD Cloud');
property('dcterms:description', 'A collection of datasets that have been published in Linked Data format by contributors to the Linking Open Data community project and other individuals and organisations. The collection is curated by contributors to the Data Hub, an open registry of datasets.');
property('dcterms:modified', date('c'), 'xsd:dateTime');
rel('foaf:homepage', 'http://lod-cloud.net/');
rel('rdfs:seeAlso', 'http://datahub.io/');
rel('foaf:depiction', 'http://lod-cloud.net/versions/2011-09-19/lod-cloud.png');

rel('rdfs:seeAlso', $uris->themes);
rel('rdfs:seeAlso', $uris->tags);
rel('rdfs:seeAlso', $uris->licenses);
foreach (array_keys($datasets) as $id) {
  rel('void:subset', $uris->dataset($id));
}

// Create RDF information about Richard
about('http://richard.cyganiak.de/#me', 'foaf:Person');
property('foaf:name', 'Richard Cyganiak');
rel('foaf:homepage', 'http://richard.cyganiak.de/');
rel('foaf:mbox', 'mailto:richard@cyganiak.de');
