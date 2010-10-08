<?php

//$debug_maxdatasets = 5;
$base = 'http://lod-cloud.net/';
$dir = 'output';
if (is_dir($dir)) {
  echo "Must delete output directory first: $dir\n";
  die();
}

@ini_set('error_reporting', E_ALL);

include_once('Ckan_client-PHP/Ckan_client.php');

// Fetching datasets from CKAN API
$ckan = new Ckan_client();
echo "Fetching dataset list ... ";
$group_description = $ckan->get_group_entity('lodcloud');
echo "OK (" . count($group_description->packages) . " datasets)\n";
$datasets = array();
foreach ($group_description->packages as $package_id) {
  if (@$debug_maxdatasets && count($datasets) == $debug_maxdatasets) break;
  echo "Fetching dataset $package_id ... ";
  $package = $ckan->get_package_entity($package_id);
  echo $package->name . "\n";
  $datasets[$package->name] = $package;
}

// Themes
$themes = array(
  'media' => array('label' => 'Media'),
  'geographic' => array('label' => 'Geography'),
  'lifesciences' => array('label' => 'Life sciences'),
  'publications' => array('label' => 'Publications',
      'note' => 'Including library and museum data'),
  'government' => array('label' => 'Government'),
  'ecommerce' => array('label' => 'eCommerce'),
  'socialweb' => array('label' => 'Social Web',
      'note' => 'People and their activities'),
  'usergeneratedcontent' => array('label' => 'User-generated content',
      'note' => 'Blog posts, discussions, pictures, etc.'),
  'schemata' => array('label' => 'Schemata',
      'note' => 'Structural resources, including vocabularies, ontologies, classifications, thesauri'),
  'crossdomain' => array('label' => 'Cross-domain'),
);

// Extracting linkset info from custom fields
echo "Calculating linksets ... ";
$linkset_count = 0;
foreach ($datasets as $package => $details) {
  $datasets[$package]->inlinks = array();
}
foreach ($datasets as $package => $details) {
  $datasets[$package]->outlinks = array();
  $extras = get_object_vars($details->extras);
  foreach ($extras as $key => $value) {
    if (!preg_match('/^links:(.*)/', $key, $match)) continue;
    $target = $match[1];
    if (!array_key_exists($target, $datasets)) continue;
    $datasets[$package]->outlinks[$target] = (int) $value;
    $datasets[$target]->inlinks[$package] = (int) $value;
    $linkset_count++;
  }
  ksort($datasets[$package]->outlinks);
}
foreach (array_keys($datasets) as $package) {
  ksort($datasets[$package]->inlinks);
}
echo "OK ($linkset_count linksets)\n";

// Inspect resources to identify SPARQL endpoints, dumps, examples etc
echo "Categorising resources ... ";
foreach ($datasets as $package => $dataset) {
  $datasets[$package]->sparql = null;
  $datasets[$package]->examples = array();
  $datasets[$package]->dumps = array();
  $datasets[$package]->other_resources = array();
  foreach ($dataset->resources as $resource) {
    $url = @trim($resource->url);
    if (!preg_match('!^(https?|ftp):\/\/[^<> ]*$!', $url)) continue; 
    $format = strtolower(trim($resource->format));
    $description = @$resource->description;
    if ($format == 'api/sparql') {
      $datasets[$package]->sparql = $url;
      continue;
    }
    $resource = array('url' => $url, 'format' => $format, 'description' => $description);
    if ($format == 'example/rdf+xml') {
      $resource['format'] = 'application/rdf+xml';
      $datasets[$package]->examples[] = $resource;
    } else if ($format == 'example/turtle') {
      $resource['format'] = 'text/turtle';
      $datasets[$package]->examples[] = $resource;
    } else if ($format == 'example/ntriples') {
      $resource['format'] = 'application/x-ntriples';
      $datasets[$package]->examples[] = $resource;
    } else if ($format == 'example/rdfa') {
      $resource['format'] = 'application/text/html';
      $datasets[$package]->examples[] = $resource;
    } else if ($format == 'application/rdf+xml' || $format == 'text/turtle' || $format == 'application/x-ntriples' || $format == 'application/x-nquads') {
      $datasets[$package]->dumps[] = $resource;
    } else {
      $datasets[$package]->other_resources[] = $resource;
    }
  }
}
echo "OK\n";

echo "Misc. dataset cleanup ... ";
foreach ($datasets as $package => $dataset) {
  // Licenses ... Work around broken data for RKB datasets
  if (preg_match('/ /', $dataset->license_id) || $dataset->license_id == 'None') {
    $datasets[$package]->license_id = null;
  }
  // Contributors (author, maintainer)
  $datasets[$package]->contributors = array();
  foreach (array('author', 'maintainer') as $role) {
    $name = @$dataset->$role;
    $email_var = $role . '_email';
    $email = @$dataset->$email_var;
    $homepage = null;
    // homepage in email field?
    if (preg_match('!^http://!', $email)) {
      $homepage = $email;
      $email = null;
    }
    // invalid email?
    if (!preg_match('/^[a-zA-Z][\w\.-]*[a-zA-Z0-9]@[a-zA-Z0-9][\w\.-]*[a-zA-Z0-9]\.[a-zA-Z][a-zA-Z\.]*[a-zA-Z]$/', $email)) {
      $email = null;
    }
    if ($name || $email || $homepage) {
      $datasets[$package]->contributors[] = array('role' => $role, 'name' => $name, 'email' => $email, 'homepage' => $homepage);
    }
  }
  // Identify tags that are themes
  $datasets[$package]->themes = array();
  foreach ($dataset->tags as $tag) {
    if (isset($themes[$tag])) {
      $datasets[$package]->themes[] = $tag;
    }
  }
}
echo "OK\n";

echo "Fetching license list ... ";
$ckan_licenses = $ckan->get_license_list();
$licenses = array();
foreach ($ckan_licenses as $license) {
  $licenses[$license->id] = $license;
}
echo "OK (" . count($licenses) . " licenses)\n";

$uris = new LOD_Cloud_URI_Scheme($base);

// Prepare RDF writer
$namespaces = array(
    'void' => 'http://rdfs.org/ns/void#',
    'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
    'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
    'owl' => 'http://www.w3.org/2002/07/owl#',
    'xsd' => 'http://www.w3.org/2001/XMLSchema#',
    'foaf' => 'http://xmlns.com/foaf/0.1/',
    'dcterms' => 'http://purl.org/dc/terms/',
    'dbp' => 'http://dbpedia.org/property/',
    'void' => 'http://rdfs.org/ns/void#',
    'tag' => 'http://www.holygoat.co.uk/owl/redwood/0.1/tags/',
    'skos' => 'http://www.w3.org/2004/02/skos/core#',
    'ov' => 'http://open.vocab.org/terms/',
);
include_once('rdftemplating.inc.php');
$engine = new TemplateEngine($namespaces);
$engine->start_context('dump');

// Create RDF metadata about the RDF document itself
about($uris->dump());
property('dcterms:title', 'The LOD Cloud diagram in RDF');
property('dcterms:description', 'This file contains RDF descriptions of all RDF datasets in the LOD Cloud diagram, generated from metadata in the lodcloud group in CKAN, expressed using the voiD vocabulary.');
property('dcterms:modified', date('c'), 'xsd:dateTime');
rel('dcterms:publisher', $richard = 'http://richard.cyganiak.de/#me');
rel('dcterms:license', 'http://creativecommons.org/publicdomain/zero/1.0/');
rel('dcterms:source', 'http://ckan.net/group/lodcloud');
rel('rdfs:seeAlso', $uris->home());
rel('rdfs:seeAlso', 'http://rdfs.org/ns/void-guide');
rel('foaf:depiction', 'http://richard.cyganiak.de/2007/10/lod/lod-datasets_2010-09-22.png');

// Create RDF information about Richard
about($richard, 'foaf:Person');
property('foaf:name', 'Richard Cyganiak');
rel('foaf:homepage', 'http://richard.cyganiak.de/');
rel('foaf:mbox', 'mailto:richard@cyganiak.de');

// Create RDF information about themes
$engine->start_context('themes');
about($uris->themes(), 'skos:ConceptScheme');
property('skos:prefLabel', 'LOD Cloud Themes');
foreach ($themes as $id => $theme) {
  about($uris->theme($id), 'skos:Concept');
  property('skos:prefLabel', $theme['label']);
  property('skos:scopeNote', @$theme['note']);
  rel('skos:inScheme', $uris->themes());
}
$engine->write_context_to_file('themes', "$dir/themes/index.ttl");

// Create RDF information about licenses
$engine->start_context('licenses');
foreach ($licenses as $id => $license) {
  about($uris->license($id));
  property('rdfs:label', $license->title);
  rel('foaf:page', $license->url);
}
$engine->write_context_to_file('licenses', "$dir/licenses/index.ttl");

// Create RDF information about each dataset
foreach ($datasets as $id => $dataset) {
  $engine->start_context('dataset');

  about($uris->dataset($id), 'void:Dataset');
  property('dcterms:title', $dataset->title);
  property('skos:altLabel', @$dataset->extras->shortname);
  property('dcterms:description', $dataset->notes);
  rel('foaf:homepage', $dataset->url);
  rel('foaf:page', $dataset->ckan_url);
  property('void:triples', @$dataset->extras->triples, 'xsd:integer');
  rel('dcterms:license', $uris->license($dataset->license_id));
  rel('dcterms:license', @$dataset->extras->license_link);
  rel('void:sparqlEndpoint', $dataset->sparql);
  foreach ($dataset->dumps as $dump) {
    rel('void:dataDump', $dump['url']);
  }
  foreach ($dataset->examples as $example) {
    rel('void:exampleResource', $example['url']);
  }
  foreach ($dataset->other_resources as $resource) {
    rel('dcterms:relation', $resource['url']);
  }
  if ($dataset->ratings_count) {
    property('ov:ratings_count', $dataset->ratings_count, 'xsd:integer');
    property('ov:ratings_average', $dataset->ratings_average, 'xsd:decimal');
  }
  foreach ($dataset->contributors as $contributor) {
    rel('dcterms:contributor', $uris->contributor($id, $contributor['role']));
  }
  foreach ($dataset->outlinks as $target => $link_count) {
    rel('void:subset', $uris->linkset($id, $target));
  }
  foreach ($dataset->themes as $theme) {
    rel('dcterms:subject', $uris->theme($theme));
  }
  foreach ($dataset->tags as $tag) {
    rel('tag:taggedWithTag', $uris->tag($tag));
  }
  // Contributor details
  foreach ($dataset->contributors as $contributor) {
    about($uris->contributor($id, $contributor['role']));
    property('rdfs:label', $contributor['name']);
    if ($contributor['email']) {
      rel('foaf:mbox', 'mailto:' . $contributor['email']);
    }
    rel('foaf:homepage', $contributor['homepage']);
  }
  // Linkset details
  foreach ($dataset->outlinks as $target => $link_count) {
    about($uris->linkset($id, $target), 'void:Linkset');
    rel('void:target', $uris->dataset($id));
    rel('void:target', $uris->dataset($target));
    property('void:triples', $link_count, 'xsd:integer');
  }
  // Resource details (same structure for all kinds of resources)
  $resources = array_merge($dataset->dumps, $dataset->examples, $dataset->other_resources);
  foreach ($resources as $details) {
    about($details['url']);
    property("dcterms:description", $details['description']);
    property("dcterms:format", $details['format']);
  }

  $engine->write_context_to_file('dataset', "$dir/$id.ttl");
}

// Write dump
$engine->write_context_to_file('dump', "$dir/data/void.ttl");


class LOD_Cloud_URI_Scheme {
  var $_base;

  function __construct($base_uri) {
    $this->_base = $base_uri;
  }

  function home() { return $this->_base; }
  function dump() { return $this->_base . 'data/void.ttl'; }
  function dataset($id) { return $this->_base . $this->_escape($id); }
  function linkset($source_id, $target_id) { return $this->dataset($source_id) . '/links/' . $this->_escape($target_id); }
  function contributor($dataset_id, $role) { return $this->dataset($dataset_id) . '/' . $role; }
  function licenses() { return $this->_base . 'licenses'; }
  function license($id) { if (!$id) return null; return $this->licenses() . '/' . $this->_escape($id); }
  function themes() { return $this->_base . 'themes'; }
  function theme($id) { return $this->themes() . '/' . $this->_escape($id); }
  function tags() { return $this->_base . 'tags'; }
  function tag($id) { return $this->tags() . '/' . $this->_escape($id); }

  function _escape($str) {
    // TODO actually escape characters not allowed in URIs!
    return $str;
  }
}
