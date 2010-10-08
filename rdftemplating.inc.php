<?php

function triple_destination($rdf_writer) {
  global $___rdf_writer;
  $___rdf_writer = $rdf_writer;
}

function about($uri, $type = null) {
  global $___rdf_writer;
  $___rdf_writer->subject($uri);
  if ($type) {
    $___rdf_writer->property_qname('a', $type);
  }
}

function property($property, $content, $datatype = null) {
  global $___rdf_writer;
  $___rdf_writer->property_literal($property, $content, $datatype);
}

function rel($property, $uri) {
  global $___rdf_writer;
  $___rdf_writer->property_uri($property, $uri);
}

