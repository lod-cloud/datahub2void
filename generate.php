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
  $revision = $ckan->get_revision_entity($package->revision_id);
  $package->timestamp = $revision->timestamp . 'Z';
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
  foreach ($dataset->resources as $i => $resource) {
    $url = @trim($resource->url);
    // Strip incorrectly inserted http:// sometimes seen on the Data Hub
    // https://github.com/okfn/ckan/issues/412
    if (preg_match('!^http://[a-zA-Z][a-zA-Z0-9.+-]*://!', $url)) {
      $url = substr($url, 7);
    }
    // Encode non-URL characters occasionally seen in URLs on the Data Hub
    $bad_chars = '{}<> ';
    for ($i = 0; $i < strlen($bad_chars); $i++) {
      $url = str_replace($bad_chars[$i], urlencode($bad_chars[$i]), $url);
    }
    $dataset->resources[$i]->url = $url;
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
$tags = array();
foreach ($datasets as $package => $dataset) {
  $datasets[$package]->notes_html = Markdown($datasets[$package]->notes);
  $datasets[$package]->url = trim($datasets[$package]->url);
  // Remove 1,000 style punctuation
  if (preg_match('/^\d\d?\d?(,\d\d\d)+$/', @$datasets[$package]->extras->triples)) {
    $datasets[$package]->extras->triples = str_replace(',', '', @$datasets[$package]->extras->triples);
  }
  // Remove 1.000 style punctuation
  if (preg_match('/^\d\d?\d?(\.\d\d\d)+$/', @$datasets[$package]->extras->triples)) {
    $datasets[$package]->extras->triples = str_replace('.', '', @$datasets[$package]->extras->triples);
  }
  // Licenses ... Work around broken data for RKB datasets
  if (preg_match('/ /', $dataset->license_id) || $dataset->license_id == 'None') {
    $datasets[$package]->license_id = null;
  }
  // Build list of all tags
  foreach ($dataset->tags as $i => $tag) {
    $tag = str_replace(' ', '-', $tag);
    if (in_array($tag, $tags)) continue;
    $tags[] = $tag;
    $dataset->tags[$i] = $tag;
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
asort($tags);
echo "OK\n";

echo "Fetching license list ... ";
$ckan_licenses = $ckan->get_license_list();
$licenses = array();
foreach ($ckan_licenses as $license) {
  $licenses[$license->id] = $license;
}
echo "OK (" . count($licenses) . " licenses)\n";


include_once('rdftemplating.inc.php');

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

$uris = new URIScheme($base, array(
    'cloud'       => '/',
    'dataset'     => '/{dataset_id}',
    'linkset'     => '/{dataset_id}/links/{target_id}',
    'author'      => '/{dataset_id}/author',
    'maintainer'  => '/{dataset_id}/maintainer',
    'licenses'    => '/licenses',
    'license'     => '/licenses/{license_id}',
    'license_purl' => 'http://purl.org/okfn/licenses/{license_id}',
    'themes'      => '/themes',
    'theme'       => '/themes/{theme_id}',
    'tags'        => '/tags',
    'tag'         => '/tags/{tag_id}',
    'dump'        => '/data/dump',
));

$engine = new TemplateEngine($uris, $namespaces);
$engine->render_template('cloud', array('datasets' => $datasets));
$engine->render_template('themes', array('themes' => $themes, 'datasets' => $datasets));
$engine->render_template('tags', array('tags' => $tags, 'datasets' => $datasets));
$engine->render_template('licenses', $licenses);
foreach ($datasets as $id => $dataset) {
  $engine->render_template('dataset', array('dataset_id' => $id, 'dataset' => $dataset));
}
$engine->render_template('dump', null);
echo "Writing results to $dump_filename ... ";
$engine->write($dump_filename);
echo "OK\n";
