<?php

include_once('rdfwriter.inc.php');
include_once('urischeme.inc.php');

function about($uri, $type = null) {
  global $___template_engine;
  $___template_engine->get_writer()->subject($uri);
  if ($type) {
    $___template_engine->get_writer()->property_qname('a', $type);
  }
}

function property($property, $content, $datatype = null) {
  global $___template_engine;
  $___template_engine->get_writer()->property_literal($property, $content, $datatype);
}

function rel($property, $uri) {
  global $___template_engine;
  $___template_engine->get_writer()->property_uri($property, $uri);
}

function rev($property, $uri) {
  global $___template_engine;
  $___template_engine->get_writer()->triple_uri($uri, $property,
      $___template_engine->get_writer()->get_subject());
}

function include_template($template, $data = array()) {
  global $___template_engine;
  $___template_engine->include_template($template, $data);
}

class TemplateEngine {
  var $_namespaces;
  var $_uri_scheme;
  var $_writer;
  var $_template_data_stack = array();

  function __construct($uri_scheme, $namespaces) {
    $this->_namespaces = $namespaces;
    $this->_uri_scheme = $uri_scheme;
    $this->_writer = new RDFWriter($this->_namespaces);
    global $___template_engine;
    $___template_engine = $this;
    array_push($this->_template_data_stack, array('modified' => date('c')));
  }

  function get_writer() {
    return $this->_writer;
  }

  function write($filename) {
    return $this->_writer->to_turtle_file($filename);
  }

  function render_template($template, $data = null) {
    $this->_run_template($template, $data);
  }

  function include_template($template, $data = null) {
    $this->_run_template($template, $data);
  }

  function _run_template($template, $data = array()) {
    $merged_data = array_merge($this->_template_data_stack[count($this->_template_data_stack) - 1], (array) $data);
    foreach ($merged_data as $key => $value) {
      $$key = $value;
    }
    $uris = $this->_uri_scheme;
    array_push($this->_template_data_stack, $merged_data);
    include "templates/$template.inc.php";
    array_pop($this->_template_data_stack);
  }
}
