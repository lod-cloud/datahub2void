<?php

include_once('arc/ARC2.php');
ARC2::inc('TurtleSerializer');
ARC2::inc('RDFXMLSerializer');

/**
 * A writer for RDF statements. Encapsulates RAP storage and serialisation,
 * has its own namespace management, and provides a convenient API for
 * generating RDF statements.
 */
class RDFWriter {
  var $_namespaces = array();
  var $_triples = array();
  var $_current_subject = null;

  function __construct($namespaces = array()) {
    foreach ($namespaces as $prefix => $uri) {
      $this->register_namespace($prefix, $uri);
    }
  }

  /**
   * Registers a namespace mapping that will be added to the written
   * RDF file.
   *
   * @param $prefix
   *   A namespace prefix, such as 'foaf'
   * @param $uri
   *   A namespace URI, such as 'http://xmlns.com/foaf/0.1/'
   */
  function register_namespace($prefix, $uri) {
    if (in_array($uri, $this->_namespaces)) return;
    $this->_namespaces[$prefix] = $uri;
  }

  function subject($uri) {
    $this->_current_subject = $uri;
  }

  function get_subject() {
    return $this->_current_subject;
  }

  function property_literal($p, $o, $type = null) {
    $this->triple_literal($this->_current_subject, $p, $o, $type);
  }

  function property_uri($p, $o) {
    $this->triple_uri($this->_current_subject, $p, $o);
  }

  function property_qname($p, $o) {
    $this->triple_qname($this->_current_subject, $p, $o);
  }

  function triple_literal($s, $p, $o, $type = null) {
    $this->_triple($s, $p, $o, 'literal', $this->_expand_qname($type));
  }

  function triple_uri($s, $p, $o) {
    $this->_triple($s, $p, $o, 'uri');
  }

  function triple_qname($s, $p, $o) {
    $this->_triple($s, $p, $this->_expand_qname($o), 'uri');
  }

  function triples_qname($s, $p, $os) {
    if (empty($os)) return;
    if (!is_array($os)) {
      throw new Exception("Not an array: '$os'");
    }
    foreach ($os as $qname) {
      $this->triple_qname($s, $p, $qname);
    }
  }

  function _triple($s, $p, $o, $o_type, $datatype = null) {
    if (empty($s) || empty($p) || empty($o)) return;
    $p = ($p == 'a') ? 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' : $this->_expand_qname($p);
    if (!$p) return;
    $this->_triples[] = array('s' => $s, 's_type' => 'uri', 'p' => $p, 'o' => $o, 'o_type' => $o_type, 'o_datatype' => $datatype);
  }

  function _expand_qname($qname) {
    if ($qname == null) return null;
    list($prefix, $local) = explode(':', $qname);
    if (!isset($this->_namespaces[$prefix])) return null;
    return $this->_namespaces[$prefix] . $local;
  }

  function get_rdfxml() {
    $ser = ARC2::getRDFXMLSerializer(array('ns' => $this->_namespaces, 'serializer_type_nodes' => true));
    return $ser->getSerializedTriples($this->_triples);
  }

  function get_turtle() {
    $ser = new BetterTurtleSerializer($this->_namespaces);
    return $ser->getSerializedTriples($this->_triples);
  }

  function _write_file($filename, $content) {
    $file = fopen($filename, 'w');
    fputs($file, $content);
    fclose($file);
  }

  function to_turtle_file($filename) {
    $this->_write_file($filename, $this->get_turtle());
  }

  function to_rdfxml_file($filename) {
    $this->_write_file($filename, $this->get_rdfxml());
  }
}

/**
 * A customized Turtle serializer, based on the one inculded in ARC2.
 * It uses slightly different rules for compressing URIs into QNames,
 * to make more pleasing output; and fixes some issues with literal
 * escaping.
 */
class BetterTurtleSerializer extends ARC2_TurtleSerializer {

  function __construct($namespaces) {
    parent::__construct(array('ns' => $namespaces), new stdClass());
  }

  function getTerm($v, $term = '', $qualifier = '') {
    if ($v === 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' && $term === 'p') return 'a';
    if (!is_array($v) && ($term === 's' || $term === 'o' || $term === 'dt')) {
      foreach ($this->ns as $prefix => $uri) {
        if (strpos($v, $uri) === 0) {
          $local = substr($v, strlen($uri));
          if (preg_match('/^[a-z0-9_][a-z0-9_-]*$/i', $local)) {
            if (!in_array($uri, $this->used_ns)) $this->used_ns[] = $uri;
            return $prefix . ':' . $local;
          }
        }
      }
    }
    if (is_array($v) && @$v['type'] == 'literal') {
      if (isset($v['datatype'])
        && $v['datatype'] == 'http://www.w3.org/2001/XMLSchema#integer'
        && (is_int($v['value']) || preg_match('/^[-+0-9]+$/', $v['value']))) return (string)$v['value'];
      if (isset($v['datatype'])
        && $v['datatype'] == 'http://www.w3.org/2001/XMLSchema#decimal'
        && (is_numeric($v['value']) || preg_match('/^[-+e0-9.]+$/', $v['value']))) return (string)$v['value'];
      $suffix = isset($v['lang']) && $v['lang'] ? '@' . $v['lang'] : '';
      $suffix = isset($v['datatype']) && $v['datatype'] ? '^^' . $this->getTerm($v['datatype'], 'dt') : $suffix;
      return BetterTurtleSerializer::quote($v['value']) . $suffix;
    }
    return parent::getTerm($v, $term, $qualifier);
  }
  
  function getSerializedIndex($index, $raw = 0) {
    $r = '';
    $nl = "\n";
    foreach ($index as $s => $ps) {
      $r .= $r ? ' .' . $nl . $nl : '';
      $s = $this->getTerm($s, 's');
      $r .= $s;
      $first_p = 1;
      foreach ($ps as $p => $os) {
        if (!$os) continue;
        $p = $this->getTerm($p, 'p');
        $r .= $p === 'a' ? ' ' : ($first_p ? '' : ';') . $nl . '    ';
        $r .= $p;
        $first_o = 1;
        if (!is_array($os)) {/* single literal o */
          $os = array(array('value' => $os, 'type' => 'literal'));
        }
        if (count($os) == 1) {
          $r .= ' ' . $this->getTerm($os[0], 'o', $p);
        } else {
          foreach ($os as $o) {
            $r .= $p === 'a' ? ($first_o ? ' ' : ', ') : ($first_o ? '' : ',') . $nl . '        ';
            $o = $this->getTerm($o, 'o', $p);
            $r .= $o;
            $first_o = 0;
          }
        }
        $first_p = 0;
      }
    }
    $r .= $r ? ' .' . $nl : '';
    if ($raw) {
      return $r;
    }
    return $r ? $this->getHead() . $nl . $nl . $r : '';
  }

  private static function quote($str) {
    if (preg_match("/[\\x0A\\x0D]/", $str)) {
      return '"""' . preg_replace_callback(
          "/[\\x00-\\x09\\x0B\\x0C\\x0E-\\x1F\\x22\\x5C\\x7F]|[\\x80-\\xBF]|[\\xC0-\\xFF][\\x80-\\xBF]*/",
          array('BetterTurtleSerializer', 'escape_callback'),
          $str) . '"""';
    } else {
      return '"' . BetterTurtleSerializer::escape($str) . '"';
    }
  }

  const error_character = '\\uFFFD';

  // Input is an UTF-8 encoded string. Output is the string in N-Triples encoding.
  // Checks for invalid UTF-8 byte sequences and replaces them with \uFFFD (white
  // question mark inside black diamond character)
  //
  // Sources:
  // http://www.w3.org/TR/rdf-testcases/#ntrip_strings
  // http://en.wikipedia.org/wiki/UTF-8
  // http://www.cl.cam.ac.uk/~mgk25/ucs/examples/UTF-8-test.txt
  private static function escape($str) {
    // Replaces all byte sequences that need escaping. Characters that can
    // remain unencoded in N-Triples are not touched by the regex. The
    // replaced sequences are:
    //
    // 0x00-0x1F   non-printable characters
    // 0x22        double quote (")
    // 0x5C        backslash (\)
    // 0x7F        non-printable character (Control)
    // 0x80-0xBF   unexpected continuation byte, 
    // 0xC0-0xFF   first byte of multi-byte character,
    //             followed by one or more continuation byte (0x80-0xBF)
    //
    // The regex accepts multi-byte sequences that don't have the correct
    // number of continuation bytes (0x80-0xBF). This is handled by the
    // callback.
    return preg_replace_callback(
        "/[\\x00-\\x1F\\x22\\x5C\\x7F]|[\\x80-\\xBF]|[\\xC0-\\xFF][\\x80-\\xBF]*/",
        array('BetterTurtleSerializer', 'escape_callback'),
        $str);
  }

  private static function escape_callback($matches) {
    $encoded_character = $matches[0];
    $byte = ord($encoded_character[0]);
    // Single-byte characters (0xxxxxxx, hex 00-7E)
    if ($byte == 0x09) return "\\t";
    if ($byte == 0x0A) return "\\n";
    if ($byte == 0x0D) return "\\r";
    if ($byte == 0x22) return "\\\"";
    if ($byte == 0x5C) return "\\\\";
    if ($byte < 0x20 || $byte == 0x7F) {
      // encode as \u00XX
      return "\\u00" . sprintf("%02X", $byte);
    }
    // Multi-byte characters
    if ($byte < 0xC0) {
      // Continuation bytes (0x80-0xBF) are not allowed to appear as first byte
      return BetterTurtleSerializer::error_character;
    }
    if ($byte < 0xE0) { // 110xxxxx, hex C0-DF
      $bytes = 2;
      $codepoint = $byte & 0x1F;
    } else if ($byte < 0xF0) { // 1110xxxx, hex E0-EF
      $bytes = 3;
      $codepoint = $byte & 0x0F;
    } else if ($byte < 0xF8) { // 11110xxx, hex F0-F7
      $bytes = 4;
      $codepoint = $byte & 0x07;
    } else if ($byte < 0xFC) { // 111110xx, hex F8-FB
      $bytes = 5;
      $codepoint = $byte & 0x03;
    } else if ($byte < 0xFE) { // 1111110x, hex FC-FD
      $bytes = 6;
      $codepoint = $byte & 0x01;
    } else { // 11111110 and 11111111, hex FE-FF, are not allowed
      return BetterTurtleSerializer::error_character;
    }
    // Verify correct number of continuation bytes (0x80 to 0xBF)
    $length = strlen($encoded_character);
    if ($length < $bytes) { // not enough continuation bytes
      return BetterTurtleSerializer::error_character;
    }
    if ($length > $bytes) { // Too many continuation bytes -- show each as one error
      $rest = str_repeat(BetterTurtleSerializer::error_character, $length - $bytes);
    } else {
      $rest = '';
    }
    // Calculate Unicode codepoints from the bytes
    for ($i = 1; $i < $bytes; $i++) {
      // Loop over the additional bytes (0x80-0xBF, 10xxxxxx)
      // Add their lowest six bits to the end of the codepoint
      $byte = ord($encoded_character[$i]);
      $codepoint = ($codepoint << 6) | ($byte & 0x3F);
    }
    // Check for overlong encoding (character is encoded as more bytes than
    // necessary, this must be rejected by a safe UTF-8 decoder)
    if (($bytes == 2 && $codepoint <= 0x7F) ||
      ($bytes == 3 && $codepoint <= 0x7FF) ||
      ($bytes == 4 && $codepoint <= 0xFFFF) ||
      ($bytes == 5 && $codepoint <= 0x1FFFFF) ||
      ($bytes == 6 && $codepoint <= 0x3FFFFF)) {
      return BetterTurtleSerializer::error_character . $rest;
    }
    // Check for UTF-16 surrogates, which must not be used in UTF-8
    if ($codepoint >= 0xD800 && $codepoint <= 0xDFFF) {
      return BetterTurtleSerializer::error_character . $rest;
    }
    // Misc. illegal code positions
    if ($codepoint == 0xFFFE || $codepoint == 0xFFFF) {
      return BetterTurtleSerializer::error_character . $rest;
    }
    if ($codepoint <= 0xFFFF) {
      // 0x0100-0xFFFF, encode as \uXXXX
//      return "\\u" . sprintf("%04X", $codepoint) . $rest;
// Modified for Turtle
      return substr($encoded_character, 0, $bytes) . $rest;
    }
    if ($codepoint <= 0x10FFFF) {
      // 0x10000-0x10FFFF, encode as \UXXXXXXXX
//      return "\\U" . sprintf("%08X", $codepoint) . $rest;
// Modified for Turtle
      return substr($encoded_character, 0, $bytes) . $rest;
    }
    // Unicode codepoint above 0x10FFFF, no characters have been assigned
    // to those codepoints
    return BetterTurtleSerializer::error_character . $rest;
  }
}
