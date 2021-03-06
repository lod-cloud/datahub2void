<?php

foreach ($data as $id => $license) {
  about($uris->license($id));
  property('rdfs:label', $license->title);
  rel('foaf:page', $license->url);
  rel('owl:sameAs', $uris->license_purl($id));
}
