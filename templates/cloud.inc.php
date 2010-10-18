<?php

include_template('metadata', array('topic', $uris->cloud));

about($uris->cloud, 'void:Dataset');
property('dcterms:title', 'The LOD Cloud');
property('dcterms:description', 'A collection of datasets that have been published in Linked Data format by contributors to the Linking Open Data community project and other individuals and organisations. The collection is curated by contributors to the CKAN directory.');
property('dcterms:modified', date('c'), 'xsd:dateTime');
rel('dcterms:contributor', 'http://richard.cyganiak.de/#me');
rel('foaf:homepage', 'http://ckan.net/group/lodcloud');
rel('rdfs:seeAlso', 'http://ckan.net/');
rel('foaf:depiction', 'http://richard.cyganiak.de/2007/10/lod/lod-datasets_2010-09-22.png');

rel('rdfs:seeAlso', $uris->themes);
rel('rdfs:seeAlso', $uris->tags);
rel('rdfs:seeAlso', $uris->licenses);
foreach (array_keys($datasets) as $id) {
  rel('void:subset', $uris->dataset($id));
}

include_template('richard');

about($uris->themes, 'skos:ConceptScheme');
about($uris->tags, 'skos:ConceptScheme');
