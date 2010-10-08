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
  var $_uri_scheme;

  function __construct($uri_scheme, $namespaces) {
    $this->_namespaces = $namespaces;
    $this->_uri_scheme = $uri_scheme;
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
    echo "OK\n";
  }

  function end_context($context) {
    unset($this->_contexts[$context]);
  }

  function get_contexts() {
    return $this->_contexts;
  }

  function template($name, $data = null, $filename = null) {
    if ($filename) {
      $this->start_context($name);
    }
    if (is_array($data)) {
      foreach ($data as $key => $value) {
        $$key = $value;
      }
    }
    $uris = $this->_uri_scheme;
    include "templates/$name.inc.php";
    if ($filename) {
      $this->write_context_to_file($name, $filename);
      $this->end_context($name);
    }
  }
}
