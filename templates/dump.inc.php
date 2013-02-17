<?php

about('');
property('dcterms:title', 'The LOD Cloud diagram in RDF');
property('dcterms:description', 'This file contains RDF descriptions of all RDF datasets in the LOD Cloud diagram, generated from metadata in the lodcloud group in the Data Hub, expressed using the VoID vocabulary.');
property('dcterms:modified', $modified, 'xsd:dateTime');
rel('dcterms:publisher', 'http://richard.cyganiak.de/#me');
rel('foaf:primaryTopic', $uris->cloud);
rel('dcterms:license', 'http://creativecommons.org/publicdomain/zero/1.0/');
rel('dcterms:source', 'http://datahub.io/group/lodcloud');
