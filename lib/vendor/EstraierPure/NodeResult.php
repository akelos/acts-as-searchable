<?php
class EstraierPure_NodeResult {
	var $_docs;
	var $_hint;
	
	function __construct($docs, $hint){
		$this->_docs=$docs;
		$this->_hint=$hint;
	}
	
	function doc_num(){
		return count($this->_docs);
	}
	
	function get_doc($index){
		if(isset($this->_docs[$index])){
			return $this->_docs[$index];
		}else{
			return null;
		}
	}
	
	function hint($key){
		if(isset($this->_hint[$key])){
			return $this->_hint[$key];
		}else{
			return null;
		}
	}
}
