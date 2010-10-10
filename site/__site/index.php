<?php

$extensions = array('html', 'rdf', 'ttl');
$manifest = '../__static/__manifest.txt';

$patterns = file($manifest);
$patterns_with_files = array();
$patterns_without_files = array();
foreach ($patterns as $key => $value) {
  $value = trim($value);
  if (!$value) continue;
  list($has_file, $pattern) = explode(' ', $value, 2);
  if ($has_file) {
    $patterns_with_file[] = $pattern;
  } else {
    $patterns_without_file[] = $pattern;
  }
}

preg_match('/^([^?]*)(\?.*)?$/', $_SERVER['REQUEST_URI'], $match);
$filename = $match[1];
while (true) {
  $match = false;
  foreach ($patterns_with_file as $pattern) {
    if (preg_match("*^$pattern$*", $filename)) {
      $match = true;
      break;
    }
  }
  if ($match) {
    if (preg_match('!/$!', $filename)) {
      $filename .= 'index';
    }
    $available_formats = get_available_formats($filename, $extensions);
    if ($available_formats) break;
  } else {
    $match = false;
    foreach ($patterns_without_file as $pattern) {
      if (preg_match("*^$pattern$*", $filename)) {
        $match = true;
        break;
      }
    }
    if ($match && preg_match('!^(.+)/[^/]*$!', $filename, $match)) {
      $filename = $match[1];
      continue;
    }
  }
  include('404.php');
  die();
}
$target = $filename . '.' . best_format($available_formats) . $match[2];
// TODO: Handle https://
$absolute_uri = 'http://' . $_SERVER['SERVER_NAME'];
if ($_SERVER['SERVER_PORT'] != 80) {
  $absolute_uri .= ':' . $_SERVER['SERVER_PORT'];
}
$target = $absolute_uri . $target;
header('Location: ' . $target, true, 303);
header('Content-Type: text/plain');

echo "303 See Other: $target\r\n";

function get_available_formats($filename, $all_formats) {
  $available_formats = array();
  foreach ($all_formats as $ext) {
    if (is_file("../__static/$filename.$ext")) {
      $available_formats[] = $ext;
    }
  }
  return $available_formats;
}

function best_format($available_formats) {
  include_once 'conNeg.inc.php';

  // Stupid browsers that send */* should get HTML, so give
  // highest preference ot that. Among RDF formats, RDF/XML
  // has antecedence over Turtle and N3. We also map
  // application/xml to RDF/XML with low priority, and map
  // text/plain to Turtle so that browsers will get the
  // Turtle when there is no HTML.
  $supported_types = array(
    array('html', 1.00, 'application/xhtml+xml'),
    array('html', 0.99, 'text/html'),
    array('ttl',   0.88, 'text/rdf+n3'),
    array('ttl',   0.89, 'text/turtle'),
    array('ttl',   0.87, 'text/x-turtle'),
    array('ttl',   0.86, 'application/turtle'),
    array('ttl',   0.85, 'application/x-turtle'),
    array('ttl',   0.60, 'text/plain'),
    array('rdf',  0.87, 'application/rdf+xml'),
    array('rdf',  0.20, 'application/xml'),
  );
  $default = 'ttl';

  $struct = array('type' => array(), 'app_preference' => array());
  foreach ($supported_types as $type) {
    if (!in_array($type[0], $available_formats)) continue;
    $struct['type'][] = $type[2];
    $struct['qFactorApp'][] = $type[1];
  }
  $best = conNeg::mimeBest($struct);
  foreach ($supported_types as $type) {
    if ($type[2] == $best) {
      return $type[0];
    }
  }
  return $default;
}
