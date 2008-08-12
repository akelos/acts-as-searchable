<?php
class EstraierPure_ResultDocument {
	function __construct($uri, $attrs, $snippet, $keywords){
		$this->_uri=$uri;
		$this->_attrs=$attrs;
		$this->_snippet=$snippet;
		$this->_keywords=$keywords;
	}
	
	function uri(){
		return $this->_uri;
	}
	
	function attr_names(){
		return array_keys($this->_attrs);
	}
	
	function attr($name){
		if(isset($this->_attrs[$name])){
			return $this->_attrs[$name];
		}
		return null;
	}
	
	function snippet(){
		return $this->_snippet;
	}
	
	function keywords(){
		return $this->_keywords;
	}
}
