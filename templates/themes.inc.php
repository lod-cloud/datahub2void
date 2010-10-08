<?php

about($uris->themes(), 'skos:ConceptScheme');
property('skos:prefLabel', 'LOD Cloud Themes');
foreach ($themes as $id => $theme) {
  about($uris->theme($id), 'skos:Concept');
  property('skos:prefLabel', $theme['label']);
  property('skos:scopeNote', @$theme['note']);
  rel('skos:inScheme', $uris->themes());
}
foreach ($datasets as $id => $dataset) {
  about($uris->dataset($id));
  foreach ($dataset->themes as $theme) {
    rel('dcterms:subject', $uris->theme($theme));
  }
}
