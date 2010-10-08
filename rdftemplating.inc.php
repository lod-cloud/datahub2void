<?php

include_once('rdfwriter.inc.php');

function about($uri, $type = null) {
  global $___template_engine;
  foreach ($___template_engine->get_contexts() as $w) {
    $w->subject($uri);
  }
  if ($type) {
    foreach ($___template_engine->get_contexts() as $w) {
      $w->property_qname('a', $type);
    }
  }
}

function property($property, $content, $datatype = null) {
  global $___template_engine;
  foreach ($___template_engine->get_contexts() as $w) {
    $w->property_literal($property, $content, $datatype);
  }
}

function rel($property, $uri) {
  global $___template_engine;
  foreach ($___template_engine->get_contexts() as $w) {
    $w->property_uri($property, $uri);
  }
}

class TemplateEngine {
  var $_contexts = array();
  var $_namespaces;

  function __construct($namespaces) {
    $this->_namespaces = $namespaces;
    global $___template_engine;
    $___template_engine = $this;
  }

  function start_context($context) {
    $this->_contexts[$context] = new RDFWriter($this->_namespaces);
  }

  function write_context_to_file($context, $filename, $format = 'turtle') {
    if (!is_dir(dirname($filename))) {
      echo "Creating directory " . dirname($filename) . " ... ";
      mkdir(dirname($filename), 0777, true);
      echo "OK\n";
    }
    echo "Writing $filename ... ";
    $format = trim(strtolower($format));
    if ($format == 'rdfxml') {
      $this->_contexts[$context]->to_rdfxml_file($filename);
    } else {
      $this->_contexts[$context]->to_turtle_file($filename);
    }
    unset($this->_contexts[$context]);
    echo "OK\n";
  }

  function get_contexts() {
    return $this->_contexts;
  }
}
