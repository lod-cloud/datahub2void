# LOD Cloud VoID Generator

This project is about generating a VoID description of all the datasets
in the LOD Cloud diagram. It currently generates an RDF dump containing
these descriptions, available online here:

http://lod-cloud.net/data/void.ttl


## Background

The [LOD Cloud diagram](http://lod-cloud.net/) is a pictorial of datasets
published in [linked data format](http://linkeddata.org/) on the Web.

Metadata for these datasets is recorded in the [Data Hub](http://datahub.io),
an open directory of datasets. The
[`lodcloud` group](http://datahub.io/group/lodcloud) contains metadata
for all the datasets in the LOD Cloud diagram.

[VoID](http://www.w3.org/TR/void/) is an RDF vocabulary for expressing
metadata about such datasets in RDF format.


## Running it

Clone the repository and fetch required dependencies:

````
git clone https://github.com/lod-cloud/datahub2void.git
cd datahub2void
git submodule update --init
````

Run the code:

````
php generate.php
````

This takes a few minutes. It creates a file `void.ttl` in the current
directory.


## Details

This uses Ckan_Client-PHP for accessing the CKAN API.

This uses some code taken from Neologism and DBpedia to serialize
Turtle via ARC2. This code is found in rdfwriter.inc.php. The class
offers an API that's a bit nicer than ARC2's triple representation,
it fixes some bugs related to literal serialization in ARC2's
TurtleSerializer, and tweaks the layout of the produced Turtle to
provide (subjectively) nicer-looking Turtle output.


## To Do

* Automatically publish the VoID file to lod-cloud.net
* Fetch all the data with a single API call instead of one per dataset
* Add a VoID description for this dataset itself
* Better validation for URIs, triple numbers, etc
* Better/other vocabulary for tags?
* Interpret some more of the tags?
* Do something with sparql_named_graph custom field
* Do something with other custom fields
* Do something with version field
* Do something with the ratings
* Better consolidation of authors/maintainers
* consolidate the fixed TurtleWriter and contribute back to ARC


## History

At some point, this project was intended to produce not just an RDF dump,
but also RDF and HTML descriptions of each entity described in the dump.
That effort stalled, and is removed from the current codebase, but can still
be found in the `feature-html` branch.


## Acknowledgements

Originally created by Richard Cyganiak (richard@cyganiak.de).

Thanks to Michael Hausenblas for feedback and comments.

Thanks to the LOD community for publishing all these datasets,
and thanks to OKFN for hosting the metadata!
