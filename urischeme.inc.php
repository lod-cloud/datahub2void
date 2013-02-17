<?php

class URIScheme {
  var $_base;
  var $_spec;

  function __construct($base, $spec) {
    $this->_base = preg_match('!/$!', $base) ? substr($base, 0, -1) : $base;
    $this->_spec = $spec;
  }

  function sets() {
    return array_keys($this->_spec);
  }

  function __get($name) {
    if (!isset($this->_spec[$name])) {
      trigger_error("Access to undefined URI '$name'");
    }
    return $this->_uri($name);
  }
  
  function __call($method, $arguments) {
    if (!isset($this->_spec[$method])) {
      trigger_error("Access to undefined URI pattern '$method'");
    }
    return $this->_uri($method, $arguments);
  }

  function _is_absolute($uri) {
    // TODO: Do a proper check
    return !preg_match('!^/!', $uri);
  }

  function _uri($set, $variables = array()) {
    $expanded = $this->_expand($set, $variables);
    if (is_null($expanded)) return null;
    if ($this->_is_absolute($expanded)) {
      return $expanded;
    }
    // TODO: Do proper resolution of relative URIs
    return $this->_base . $expanded;
  }

  function _expand($set, $variables = array()) {
    if (!isset($this->_spec[$set])) return null;
    $result = $this->_spec[$set];
    if ($variables) {
      if (is_array($variables[0])) {
        foreach ($variables[0] as $key => $value) {
          if (empty($value) || is_array($value) || is_object($value)) continue;
          $result = str_replace('{' . $key . '}', $this->_escape($value), $result);
        }
      } else {
        foreach ($variables as $value) {
          if (preg_match('/^[^{]*({[^}]*})/', $result, $match)) {
            $result = str_replace($match[1], $this->_escape($value), $result);
          }
        }
      }
    }
    if (preg_match('/[{}]/', $result)) return null;
    return $result;
  }

  function _escape($str) {
    // TODO actually escape characters not allowed in URIs!
    return $str;
  }
}
