<?php

function add_triple_destination($rdf_writer) {
  global $___rdf_writers;
  if (!isset($___rdf_writers)) $___rdf_writers = array();
  if (in_array($rdf_writer, $___rdf_writers)) return;
  $___rdf_writers[] = $rdf_writer;
}

function remove_triple_destination($rdf_writer) {
  global $___rdf_writers;
  if (!in_array($rdf_writer, $___rdf_writers)) return;
  foreach ($___rdf_writers as $key => $value) {
    if ($___rdf_writers[$key] != $rdf_writer) continue;
    unset($___rdf_writers[$key]);
    return;
  }
}

function about($uri, $type = null) {
  global $___rdf_writers;
  foreach ($___rdf_writers as $w) {
    $w->subject($uri);
  }
  if ($type) {
    foreach ($___rdf_writers as $w) {
      $w->property_qname('a', $type);
    }
  }
}

function property($property, $content, $datatype = null) {
  global $___rdf_writers;
  foreach ($___rdf_writers as $w) {
    $w->property_literal($property, $content, $datatype);
  }
}

function rel($property, $uri) {
  global $___rdf_writers;
  foreach ($___rdf_writers as $w) {
    $w->property_uri($property, $uri);
  }
}

