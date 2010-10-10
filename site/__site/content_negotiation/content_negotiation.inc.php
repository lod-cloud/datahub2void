<?php
/****************************************************************************
	*																		*
	*	Version: content_negotiation.inc.php v1.3.0 2008-11-01				*
	*	Copyright: (c) 2006-2008 ptlis										*
	*	Licence: GNU Lesser General Public License v2.1						*
	*	The current version of this library can be sourced from:			*
	*		http://ptlis.net/source/php-content-negotiation/				*
	*	Contact the author of this library at:								*
	*		ptlis@ptlis.net													*
	*																		*
	*	This class requires PHP 5.x (it has been tested on 5.0.x - 5.2		*
	*	with error reporting set to E_ALL | E_STRICT without problems)		*
	*	but should be trivially modifiable to work on PHP 4.x				*
	*																		*
	***************************************************************************/

define('WILDCARD_DEFAULT',  -1);
define('WILDCARD_TYPE', 0);
define('WILDCARD_SUBTYPE', 1);
define('WILDCARD_NONE', 2);

class content_negotiation {
	// generic private function called by all other public ones
	static private function generic_negotiation($header, $return_type, array $app_types, $mime_negotiation=false) {
		$header		= strtolower($header);
		$matches	= null;


		// First perform initial parse of header

		// Mime types are a special case due to the possibility of subtypes
		if($mime_negotiation) {
			if(!preg_match_all('/([a-z\-\+\*]+)\/([a-z0-9\-\+\*]+)\s*;?\s*q?=?(0\.\d{1,5}|1\.0|[01])?,*/i', $header, $matches)) {
				return false;
			}

			// Normalise generated data structure
			for($i = 0; $i < count($matches[0]); $i++) {
				$header_types['type'][$i]	= $matches[1][$i] . '/' . $matches[2][$i];
				if($matches[3][$i] != null) {
					$header_types['q_value'][$i]	= $matches[3][$i];
				}
				else {
					$header_types['q_value'][$i]	= 1;
				}
				$header_types['mime_type'][$i]		= $matches[1][$i];
				$header_types['mime_subtype'][$i]	= $matches[2][$i];
			}
		}

		// Charset, Language and Encoding can be handled together
		else {
			if(!preg_match_all('/([a-z\-0-9\*]+)\s*;?\s*q?=?(0\.\d{1,5}|1\.0|[01])?,*/i', $header, $matches)) {
				return false;
			}

			// Normalise generated data structure
			for($i = 0; $i < count($matches[0]); $i++) {
				$header_types['type'][$i]	= $matches[1][$i];
				if($matches[2][$i] != null) {
					$header_types['q_value'][$i]	= $matches[2][$i];
				}
				else {
					$header_types['q_value'][$i]	= 1;
				}
			}
		}


		// Normalise application provided data structure
		if(is_array($app_types) && count($app_types) > 0) {
			$app_vals_provided		= true;
			for($i = 0; $i < count($app_types['type']); $i++) {			// Set default values (and make all lower case)
				$app_types['type'][$i]			= strtolower($app_types['type'][$i]);
				$app_types['q_value'][$i]		= 0;
				$app_types['specificness'][$i]	= WILDCARD_DEFAULT;
				if($mime_negotiation) {
					$type_parts	= explode('/', $app_types['type'][$i]);
					$app_types['mime_type'][$i]		= $type_parts[0];
					$app_types['mime_subtype'][$i]	= $type_parts[1];
				}
			}
		}
		else {
			$app_vals_provided			= false;
			$app_types['type']			= array();
			$app_types['q_value']		= array();
		}



		/*	Iterate through the types found in the header applying the
			appropriate q values. */
		for($i = 0; $i < count($header_types['type']); $i++) {
			/*	If the application provided no types datastructure then simply
				move the data for non wildcard types from the header
				datastructure to the app types datastructure. */
			if(!$app_vals_provided) {
				// Do not copy if we find wildcard values
				if($mime_negotiation && ($header_types['mime_type'][$i] == '*' || ($header_types['mime_subtype'][$i] == '*'))
						|| !$mime_negotiation && $header_types['type'][$i] == '*') {
					continue;
				}

				$app_types['type'][$i]		= $header_types['type'][$i];
				$app_types['q_value'][$i]	= $header_types['q_value'][$i];
			}
			
			/*	If the application provided an $app_types value then we need
				To iterate through the $header_types array setting the q values
				as appropriate depending on rules of specificness (exact matches
				of types override more general matches of wildcards). */
			else {
				// Attempt to find a match for the current type in the
				// $app_types array
				$key	= array_search($header_types['type'][$i], $app_types['type']);

				// The search returned a key
				if($key !== false) {
					$app_types['specificness'][$key]	= WILDCARD_NONE;
					$app_types['q_value'][$key]			= $header_types['q_value'][$i];
				}
				
				/*	If mime negotiation is being performed and both the type &
				 	subtype are wildcards then iterate through $app_types
					updating the q values of all types with a specificness of
					WILDCARD_DEFAULT, and updating their specificness to
					WILDCARD_TYPE. */
				else if($mime_negotiation && $header_types['mime_type'][$i] == '*' && $header_types['mime_subtype'][$i] == '*'
						|| (!$mime_negotiation && $header_types['type'][$i] == '*')) {
					for($j = 0; $j < count($app_types['type']); $j++) {
						if($app_types['specificness'][$j] == WILDCARD_DEFAULT) {
							$app_types['specificness'][$j]	= WILDCARD_TYPE;
							$app_types['q_value'][$j]		= $header_types['q_value'][$i];
						}
					}
				}
				
				/*	If mime negotiation is being performed and the subtype is a
					wildcard then iterate through $app_types updating the q
					values of all types with a specificness of less or equal to
					WILDCARD_TYPE and updating their specificness. */
				else if($mime_negotiation && $header_types['mime_subtype'] == '*') {
					for($j = 0; $j < count($app_types['type']); $j++) {
						if($app_types['specificness'][$j] <= WILDCARD_TYPE) {
							$app_types['specificness'][$j]	= WILDCARD_SUBTYPE;
							$app_types['q_value'][$j]		= $header_types['q_value'][$i];
						}
					}
				}
			}
		}

		if($app_vals_provided) {
      for ($i = 0; $i < count($app_types['q_value']); $i++) {
        $app_types['factor'][$i] = $app_types['q_value'][$i] * $app_types['app_preference'][$i];
      }
			array_multisort(
          $app_types['factor'], SORT_DESC, SORT_NUMERIC,
          $app_types['q_value'], SORT_DESC, SORT_NUMERIC,
					$app_types['app_preference'], SORT_DESC, SORT_NUMERIC,
					$app_types['type'],
					$app_types['specificness']);
		}
		else {
			array_multisort($app_types['q_value'], SORT_DESC, SORT_NUMERIC,
					$app_types['type']);
		}

		switch($return_type) {
			case 'all':
				return $app_types;
				break;
			case 'best':
				return $app_types['type'][0];
				break;
			default:
				return false;
				break;
		}
	}


	// return only the preferred mime-type
	static public function mime_best_negotiation(array $app_types=array()) {
		if(isset($_SERVER['HTTP_ACCEPT'])) {
			return content_negotiation::generic_negotiation($_SERVER['HTTP_ACCEPT'], 'best', $app_types, true);
		}
		else {
			return false;
		}
	}


	// return the whole array of mime-types
	static public function mime_all_negotiation(array $app_types=array()) {
		if(isset($_SERVER['HTTP_ACCEPT'])) {
			return content_negotiation::generic_negotiation($_SERVER['HTTP_ACCEPT'], 'all', $app_types, true);
		}
		else {
			return false;
		}
	}


	// return only the preferred charset
	static public function charset_best_negotiation(array $app_types=array()) {
		if(isset($_SERVER['HTTP_ACCEPT_CHARSET'])) {
			return content_negotiation::generic_negotiation($_SERVER['HTTP_ACCEPT_CHARSET'], 'best', $app_types);
		}
		else {
			return false;
		}
	}


	// return the whole array of charsets
	static public function charset_all_negotiation(array $app_types=array()) {
		if(isset($_SERVER['HTTP_ACCEPT_CHARSET'])) {
			return content_negotiation::generic_negotiation($_SERVER['HTTP_ACCEPT_CHARSET'], 'all', $app_types);
		}
		else {
			return false;
		}
	}


	// return only the preferred encoding-type
	static public function encoding_best_negotiation(array $app_types=array()) {
		if(isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
			return content_negotiation::generic_negotiation($_SERVER['HTTP_ACCEPT_ENCODING'], 'best', $app_types);
		}
		else {
			return false;
		}
	}


	// return the whole array of encoding-types
	static public function encoding_all_negotiation(array $app_types=array()) {
		if(isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
			return content_negotiation::generic_negotiation($_SERVER['HTTP_ACCEPT_ENCODING'], 'all', $app_types);
		}
		else {
			return false;
		}
	}


	// return only the preferred language
	static public function language_best_negotiation(array $app_types=array()) {
		if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			return content_negotiation::generic_negotiation($_SERVER['HTTP_ACCEPT_LANGUAGE'], 'best', $app_types);
		}
		else {
			return false;
		}
	}


	// return the whole array of language
	static public function language_all_negotiation(array $app_types=array()) {
		if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			return content_negotiation::generic_negotiation($_SERVER['HTTP_ACCEPT_LANGUAGE'], 'all', $app_types);
		}
		else {
			return false;
		}
	}
}

?>
