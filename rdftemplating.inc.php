<?php

include_once('rdfwriter.inc.php');
include_once('urischeme.inc.php');

function about($uri, $type = null) {
  global $___template_engine;
  foreach ($___template_engine->get_active_contexts() as $w) {
    $w->subject($uri);
  }
  if ($type) {
    foreach ($___template_engine->get_active_contexts() as $w) {
      $w->property_qname('a', $type);
    }
  }
}

function property($property, $content, $datatype = null) {
  global $___template_engine;
  foreach ($___template_engine->get_active_contexts() as $w) {
    $w->property_literal($property, $content, $datatype);
  }
}

function rel($property, $uri) {
  global $___template_engine;
  foreach ($___template_engine->get_active_contexts() as $w) {
    $w->property_uri($property, $uri);
  }
}

function rev($property, $uri) {
  global $___template_engine;
  foreach ($___template_engine->get_active_contexts() as $w) {
    $w->triple_uri($uri, $property, $w->get_subject());
  }
}

function include_template($template, $data = array()) {
  global $___template_engine;
  $___template_engine->include_template($template, $data);
}

class TemplateEngine {
  var $_output_dir;
  var $_contexts = array();
  var $_namespaces;
  var $_uri_scheme;
  var $_template_forwards = array();
  var $_active_template = null;
  var $_active_context = null;
  var $_template_data_stack = array();
  var $_rendered_templates = array();

  function __construct($output_dir, $uri_scheme, $namespaces) {
    $this->_output_dir = $output_dir;
    $this->_namespaces = $namespaces;
    $this->_uri_scheme = $uri_scheme;
    global $___template_engine;
    $___template_engine = $this;
    array_push($this->_template_data_stack, array('modified' => date('c')));
  }

  function _write_context_to_file($context, $filename) {
    if (preg_match('!/$!', $filename)) $filename .= 'index';
    if (!is_dir($this->_output_dir)) {
      echo "Creating directory $this->_output_dir ... ";
      mkdir($this->_output_dir, 0777, true);
      echo "OK\n";
    }
    $filename = $this->_output_dir . $filename;
    if (!is_dir(dirname($filename))) {
      echo "Creating directory " . dirname($filename) . " ... ";
      mkdir(dirname($filename), 0777, true);
      echo "OK\n";
    }
    echo "Writing $filename.{rdf|ttl} ... ";
    $this->_contexts[$context]->to_rdfxml_file($filename . '.rdf');
    $this->_contexts[$context]->to_turtle_file($filename . '.ttl');
    echo "OK\n";
  }

  function _context($context) {
    if ($context == null) return null;
    if (!isset($this->_contexts[$context])) {
      $this->_contexts[$context] = new RDFWriter($this->_namespaces);
    }
    return $this->_contexts[$context];
  }

  function _reset_context($context) {
    unset($this->_contexts[$context]);
  }

  function get_active_contexts() {
    $result = array();
    if ($this->_active_context) {
      $result[] = $this->_active_context;
    }
    if ($this->_active_template) {
      $excluded_names = array();
      if (isset($this->_template_forward_excludes['*'])) {
        $excluded_names = array_merge($excluded_names, $this->_template_forward_excludes['*']);
      }
      if (isset($this->_template_forward_excludes[$this->_active_template])) {
        $excluded_names = array_merge($excluded_names, $this->_template_forward_excludes[$this->_active_template]);
      }
      if (isset($this->_template_forwards['*'])) {
        foreach ($this->_template_forwards['*'] as $context) {
          if (in_array($context, $excluded_names)) continue;
          $c = $this->_context($context);
          if (in_array($c, $result, true)) continue;
          $result[] = $c;
        }
      }
      if (isset($this->_template_forwards[$this->_active_template])) {
        foreach ($this->_template_forwards[$this->_active_template] as $context) {
          if (in_array($context, $excluded_names)) continue;
          $c = $this->_context($context);
          if (in_array($c, $result, true)) continue;
          $result[] = $c;
        }
      }
    }
    return $result;
  }

  function template_forward($template, $context) {
    $this->_context($context);  // init a context
    $this->_template_forwards[$template][] = $context;
  }

  function template_forward_exclude($template, $context) {
    $this->_context($context);  // init a context
    $this->_template_forward_excludes[$template][] = $context;
  }

  function render_template($template, $data = null, $filename = null) {
    if (!$filename) {
      $filename = $this->_uri_scheme->filename($template, $data);
    }
    $this->_active_context = $this->_context($template);
    $this->_run_template($template, $data);
    if ($filename) {
      $this->_write_context_to_file($template, $filename);
      $this->_reset_context($template);
    }
    $this->_active_context = null;
    if (!in_array($template, $this->_rendered_templates)) {
      $this->_rendered_templates[] = $template;
    }
  }

  function include_template($template, $data = null) {
    $this->_run_template($template, $data);
  }

  function _run_template($template, $data = array()) {
    $template_backup = $this->_active_template;
    $this->_active_template = $template;
    $merged_data = array_merge($this->_template_data_stack[count($this->_template_data_stack) - 1], (array) $data);
    foreach ($merged_data as $key => $value) {
      $$key = $value;
    }
    $uris = $this->_uri_scheme;
    array_push($this->_template_data_stack, $merged_data);
    include "templates/$template.inc.php";
    array_pop($this->_template_data_stack);
    $this->_active_template = $template_backup;
  }

  function write_manifest() {
    $filename = $this->_output_dir . '/__manifest.txt';
    echo "Writing $filename ... ";
    $file = fopen($filename, 'w');
    foreach ($this->_uri_scheme->sets() as $set) {
      $rendered = in_array($set, $this->_rendered_templates);
      $regex = $this->_uri_scheme->uri_regex($set);
      fwrite($file, ($rendered ? '1' : '0') . ' ' . $regex . "\n");
    }
    fclose($file);
    echo "OK\n";
  }
}
