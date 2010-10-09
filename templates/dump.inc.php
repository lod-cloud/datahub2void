<?php

include_template('metadata', array('source' => 'http://ckan.net/group/lodcloud'));

about('');
property('dcterms:title', 'The LOD Cloud diagram in RDF');
property('dcterms:description', 'This file contains RDF descriptions of all RDF datasets in the LOD Cloud diagram, generated from metadata in the lodcloud group in CKAN, expressed using the voiD vocabulary.');
rel('dcterms:publisher', 'http://richard.cyganiak.de/#me');
rel('rdfs:seeAlso', $uris->cloud);
rel('rdfs:seeAlso', 'http://rdfs.org/ns/void-guide');
rel('foaf:depiction', 'http://richard.cyganiak.de/2007/10/lod/lod-datasets_2010-09-22.png');

include_template('richard');
