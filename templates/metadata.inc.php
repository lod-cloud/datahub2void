<?php

about('');
property('dcterms:modified', $modified, 'xsd:dateTime');
rel('foaf:primaryTopic', @$topic);
rel('dcterms:license', 'http://creativecommons.org/publicdomain/zero/1.0/');
rel('dcterms:source', @$source);
