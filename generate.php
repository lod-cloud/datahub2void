<?php

//$debug_maxdatasets = 5;
$base = 'http://lod-cloud.net/';
$filename = 'void.ttl';
$fileurl = $base . 'data/' . $filename;

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
    } else if ($format == 'example/rdf+xml') {
      $datasets[$package]->examples[] = array($url, 'application/rdf+xml', $description);
    } else if ($format == 'example/turtle') {
      $datasets[$package]->examples[] = array($url, 'text/turtle', $description);
    } else if ($format == 'example/ntriples') {
      $datasets[$package]->examples[] = array($url, 'application/x-ntriples', $description);
    } else if ($format == 'example/rdfa') {
      $datasets[$package]->examples[] = array($url, 'text/html', $description);
    } else if ($format == 'application/rdf+xml' || $format == 'text/turtle' || $format == 'application/x-ntriples' || $format == 'application/x-nquads') {
      $datasets[$package]->dumps[] = array($url, $format, $description);
    } else {
      $datasets[$package]->other_resources[] = array($url, $format, $description);
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

// Themes
$themes = array(
  'media' => array('Media'),
  'geographic' => array('Geography'),
  'lifesciences' => array('Life sciences'),
  'publications' => array('Publications', 'Including library and museum data'),
  'government' => array('Government'),
  'ecommerce' => array('eCommerce'),
  'socialweb' => array('Social Web', 'People and their activities'),
  'usergeneratedcontent' => array('User-generated content', 'Blog posts, discussions, pictures, etc.'),
  'schemata' => array('Schemata', 'Structural resources, including vocabularies, ontologies, classifications, thesauri'),
  'crossdomain' => array('Cross-domain'),
);

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

// Create RDF metadata about the RDF document itself
$richard = 'http://richard.cyganiak.de/#me';
$out->triple_literal($fileurl, 'dcterms:title', 'The LOD Cloud diagram in RDF');
$out->triple_literal($fileurl, 'dcterms:description', 'This file contains RDF descriptions of all RDF datasets in the LOD Cloud diagram, generated from metadata in the lodcloud group in CKAN, expressed using the voiD vocabulary.');
$out->triple_literal($fileurl, 'dcterms:modified', date('c'), 'xsd:dateTime');
$out->triple_uri($fileurl, 'dcterms:creator', $richard);
$out->triple_uri($fileurl, 'dcterms:license', 'http://creativecommons.org/publicdomain/zero/1.0/');
$out->triple_uri($fileurl, 'dcterms:source', 'http://ckan.net/group/lodcloud');
$out->triple_uri($fileurl, 'rdfs:seeAlso', $base);
$out->triple_uri($fileurl, 'rdfs:seeAlso', 'http://rdfs.org/ns/void-guide');
$out->triple_uri($fileurl, 'foaf:depiction', 'http://richard.cyganiak.de/2007/10/lod/lod-datasets_2010-09-22.png');

// Create RDF information about Richard
$out->triple_qname($richard, 'a', 'foaf:Person');
$out->triple_literal($richard, 'foaf:name', 'Richard Cyganiak');
$out->triple_uri($richard, 'foaf:homepage', 'http://richard.cyganiak.de/');
$out->triple_uri($richard, 'foaf:mbox', 'mailto:richard@cyganiak.de');

// Create RDF information about themes
$scheme_uri = $base . 'themes';
$out->triple_qname($scheme_uri, 'a', 'skos:ConceptScheme');
$out->triple_literal($scheme_uri, 'skos:prefLabel', 'LOD Cloud Themes');
foreach ($themes as $id => $details) {
  $theme_uri = $scheme_uri . '/' . $id;
  $out->triple_qname($theme_uri, 'a', 'skos:Concept');
  $out->triple_literal($theme_uri, 'skos:prefLabel', $details[0]);
  $out->triple_literal($theme_uri, 'skos:scopeNote', @$details[1]);
  $out->triple_uri($theme_uri, 'skos:inScheme', $scheme_uri);
}

// Create RDF information about licenses
foreach ($licenses as $id => $details) {
  $out->triple_literal($base . 'license/' . $id, 'rdfs:label', $details->title);
  $out->triple_literal($base . 'license/' . $id, 'foaf:page', $details->url);
}

// Create RDF information about each dataset
foreach ($datasets as $key => $dataset) {
  $ds = $base . $key;
  $out->triple_qname($ds, 'a', 'void:Dataset');
  $out->triple_literal($ds, 'dcterms:title', $dataset->title);
  if (isset($dataset->extras->shortname)) {
    $out->triple_literal($ds, 'skos:altLabel', $dataset->extras->shortname);
  }
  $out->triple_literal($ds, 'dcterms:description', $dataset->notes);
  $out->triple_uri($ds, 'foaf:homepage', $dataset->url);
  $out->triple_uri($ds, 'foaf:page', $dataset->ckan_url);

  // Licenses ... Work around broken data for RKB datasets
  if ($dataset->license_id && !preg_match('/ /', $dataset->license_id) && $dataset->license_id != 'None') {
    $out->triple_uri($ds, 'dcterms:license', $base . 'license/' . $dataset->license_id);
  }
  if (isset($dataset->extras->license_link)) {
    $out->triple_uri($ds, 'dcterms:license', $dataset->extras->license_link);
  }

  if (isset($dataset->extras->triples)) {
    $out->triple_literal($ds, 'void:triples', $dataset->extras->triples, 'xsd:integer');
  }
  // Resources
  if (isset($dataset->sparql)) {
    $out->triple_uri($ds, 'void:sparqlEndpoint', $dataset->sparql);
  }
  foreach ($dataset->dumps as $dump) {
    $out->triple_uri($ds, 'void:dataDump', $dump[0]);
  }
  foreach ($dataset->examples as $example) {
    $out->triple_uri($ds, 'void:exampleResource', $example[0]);
  }
  foreach ($dataset->other_resources as $resource) {
    $out->triple_uri($ds, 'dcterms:relation', $resource[0]);
  }
  if ($dataset->ratings_count) {
    $out->triple_literal($ds, 'ov:ratings_count', $dataset->ratings_count, 'xsd:integer');
    $out->triple_literal($ds, 'ov:ratings_average', $dataset->ratings_average, 'xsd:decimal');
  }
  // Author and maintainer
  $out->triple_uri($ds, 'dcterms:contributor', $dataset->author ? ($ds . '/author') : null);
  $out->triple_uri($ds, 'dcterms:contributor', $dataset->maintainer ? ($ds . '/maintainer') : null);
  // Linksets
  foreach ($dataset->outlinks as $target => $link_count) {
    $out->triple_uri($ds, 'void:subset', $ds . '/links/' . $target);
  }
  // Themes
  foreach ($dataset->tags as $tag) {
    if (!isset($themes[$tag])) continue;
    $out->triple_uri($ds, 'dcterms:subject', $base . 'themes/' . $tag);
  }
  // Tags
  foreach ($dataset->tags as $tag) {
    $out->triple_uri($ds, 'tag:taggedWithTag', $base . 'tag/' . $tag);
  }
  // Author details
  if ($dataset->author) {
    $out->triple_literal($ds . '/author', 'rdfs:label', $dataset->author);
    if ($dataset->author_email) {
      if (preg_match('!^http://!', $dataset->author_email)) {
        $out->triple_uri($ds . '/author', 'foaf:homepage', $dataset->author_email);
      } else if (preg_match('/^[a-zA-Z][\w\.-]*[a-zA-Z0-9]@[a-zA-Z0-9][\w\.-]*[a-zA-Z0-9]\.[a-zA-Z][a-zA-Z\.]*[a-zA-Z]$/', $dataset->author_email)) {
        $out->triple_uri($ds . '/author', 'foaf:mbox', 'mailto:' . $dataset->author_email);
      }
    }
  }
  // Maintainer details
  if ($dataset->maintainer) {
    $out->triple_literal($ds . '/maintainer', 'rdfs:label', $dataset->maintainer);
    if ($dataset->maintainer_email) {
      if (preg_match('!^http://!', $dataset->maintainer_email)) {
        $out->triple_uri($ds . '/maintainer', 'foaf:homepage', $dataset->maintainer_email);
      } else if (preg_match('/^[a-zA-Z][\w\.-]*[a-zA-Z0-9]@[a-zA-Z0-9][\w\.-]*[a-zA-Z0-9]\.[a-zA-Z][a-zA-Z\.]*[a-zA-Z]$/', $dataset->maintainer_email)) {
        $out->triple_uri($ds . '/maintainer', 'foaf:mbox', 'mailto:' . $dataset->maintainer_email);
      }
    }
  }
  // Linkset details
  foreach ($dataset->outlinks as $target => $link_count) {
    $ls = $ds . '/links/' . $target;
    $out->triple_qname($ls, 'a', 'void:Linkset');
    $out->triple_uri($ls, 'void:target', $ds);
    $out->triple_uri($ls, 'void:target', $base . $target);
    $out->triple_literal($ls, 'void:triples', $link_count, 'xsd:integer');
  }
  // Resource details (same structure for all kinds of resources)
  $resources = array_merge($dataset->dumps, $dataset->examples, $dataset->other_resources);
  foreach ($resources as $details) {
    list($url, $format, $description) = $details;
    $out->triple_literal($url, "dcterms:description", $description);
    $out->triple_literal($url, "dcterms:format", $format);
  }
}

// Write to file
echo "Writing to file $filename ... ";
$out->to_turtle_file($filename);
echo "OK\n";
