<?php

//$debug_maxdatasets = 5;
$base = 'http://lod-cloud.net/';
$dump_filename = 'void.ttl';

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
include_once('rdfwriter.inc.php');
$out = new RDFWriter($namespaces);

$uris = new LOD_Cloud_URI_Scheme($base, $dump_filename);

// Create RDF metadata about the RDF document itself
$richard = 'http://richard.cyganiak.de/#me';
$out->subject($uris->dump());
$out->property_literal('dcterms:title', 'The LOD Cloud diagram in RDF');
$out->property_literal('dcterms:description', 'This file contains RDF descriptions of all RDF datasets in the LOD Cloud diagram, generated from metadata in the lodcloud group in CKAN, expressed using the voiD vocabulary.');
$out->property_literal('dcterms:modified', date('c'), 'xsd:dateTime');
$out->property_uri('dcterms:creator', $richard);
$out->property_uri('dcterms:license', 'http://creativecommons.org/publicdomain/zero/1.0/');
$out->property_uri('dcterms:source', 'http://ckan.net/group/lodcloud');
$out->property_uri('rdfs:seeAlso', $uris->home());
$out->property_uri('rdfs:seeAlso', 'http://rdfs.org/ns/void-guide');
$out->property_uri('foaf:depiction', 'http://richard.cyganiak.de/2007/10/lod/lod-datasets_2010-09-22.png');

// Create RDF information about Richard
$out->subject($richard);
$out->property_qname('a', 'foaf:Person');
$out->property_literal('foaf:name', 'Richard Cyganiak');
$out->property_uri('foaf:homepage', 'http://richard.cyganiak.de/');
$out->property_uri('foaf:mbox', 'mailto:richard@cyganiak.de');

// Create RDF information about themes
$out->subject($uris->themes());
$out->property_qname('a', 'skos:ConceptScheme');
$out->property_literal('skos:prefLabel', 'LOD Cloud Themes');
foreach ($themes as $id => $details) {
  $out->subject($uris->theme($id));
  $out->property_qname('a', 'skos:Concept');
  $out->property_literal('skos:prefLabel', $details['label']);
  $out->property_literal('skos:scopeNote', @$details['note']);
  $out->property_uri('skos:inScheme', $uris->themes());
}

// Create RDF information about licenses
foreach ($licenses as $id => $details) {
  $out->subject($uris->license($id));
  $out->property_literal('rdfs:label', $details->title);
  $out->property_uri('foaf:page', $details->url);
}

// Create RDF information about each dataset
foreach ($datasets as $key => $dataset) {
  $out->subject($uris->dataset($key));

  $out->property_qname('a', 'void:Dataset');
  $out->property_literal('dcterms:title', $dataset->title);
  $out->property_literal('skos:altLabel', @$dataset->extras->shortname);
  $out->property_literal('dcterms:description', $dataset->notes);
  $out->property_uri('foaf:homepage', $dataset->url);
  $out->property_uri('foaf:page', $dataset->ckan_url);
  $out->property_literal('void:triples', @$dataset->extras->triples, 'xsd:integer');

  // Licenses
  $out->property_uri('dcterms:license', $uris->license($dataset->license_id));
  $out->property_uri('dcterms:license', @$dataset->extras->license_link);

  // Resources
  $out->property_uri('void:sparqlEndpoint', $dataset->sparql);
  foreach ($dataset->dumps as $dump) {
    $out->property_uri('void:dataDump', $dump['url']);
  }
  foreach ($dataset->examples as $example) {
    $out->property_uri('void:exampleResource', $example['url']);
  }
  foreach ($dataset->other_resources as $resource) {
    $out->property_uri('dcterms:relation', $resource['url']);
  }
  // Ratings
  if ($dataset->ratings_count) {
    $out->property_literal('ov:ratings_count', $dataset->ratings_count, 'xsd:integer');
    $out->property_literal('ov:ratings_average', $dataset->ratings_average, 'xsd:decimal');
  }
  // Author and maintainer
  foreach ($dataset->contributors as $contributor) {
    $out->property_uri('dcterms:contributor', $uris->contributor($key, $contributor['role']));
  }
  // Linksets
  foreach ($dataset->outlinks as $target => $link_count) {
    $out->property_uri('void:subset', $uris->linkset($key, $target));
  }
  // Themes
  foreach ($dataset->themes as $theme) {
    $out->property_uri('dcterms:subject', $uris->theme($theme));
  }
  // Tags
  foreach ($dataset->tags as $tag) {
    $out->property_uri('tag:taggedWithTag', $uris->tag($tag));
  }
  // Contributor details
  foreach ($dataset->contributors as $contributor) {
    $out->subject($uris->contributor($key, $contributor['role']));
    $out->property_literal('rdfs:label', $contributor['name']);
    if ($contributor['email']) {
      $out->property_uri('foaf:mbox', 'mailto:' . $contributor['email']);
    }
    $out->property_uri('foaf:homepage', $contributor['homepage']);
  }
  // Linkset details
  foreach ($dataset->outlinks as $target => $link_count) {
    $out->subject($uris->linkset($key, $target));
    $out->property_qname('a', 'void:Linkset');
    $out->property_uri('void:target', $uris->dataset($key));
    $out->property_uri('void:target', $uris->dataset($target));
    $out->property_literal('void:triples', $link_count, 'xsd:integer');
  }
  // Resource details (same structure for all kinds of resources)
  $resources = array_merge($dataset->dumps, $dataset->examples, $dataset->other_resources);
  foreach ($resources as $details) {
    $out->subject($details['url']);
    $out->property_literal("dcterms:description", $details['description']);
    $out->property_literal("dcterms:format", $details['format']);
  }
}

// Write to file
echo "Writing to file $dump_filename ... ";
$out->to_turtle_file($dump_filename);
echo "OK\n";


class LOD_Cloud_URI_Scheme {
  var $_base;
  var $_dump_filename;

  function __construct($base_uri, $dump_filename) {
    $this->_base = $base_uri;
    $this->_dump_filename = $dump_filename;
  }

  function home() { return $this->_base; }
  function dump() { return $this->_base . 'data/' . $this->_dump_filename; }
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
