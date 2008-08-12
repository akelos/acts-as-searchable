<?php
define('ESTRAIERPURE_AGITO', 8);
define('ESTRAIERPURE_FAST' , 4);
define('ESTRAIERPURE_ISECT', 65536);
define('ESTRAIERPURE_NOIDF', 16);
define('ESTRAIERPURE_ROUGH', 2048);
define('ESTRAIERPURE_SIMPLE',1024);
define('ESTRAIERPURE_SURE',  1);
define('ESTRAIERPURE_UNION', 32768);
define('ESTRAIERPURE_USUAL', 2);

class EstraierPure_Condition {
	function __construct(){
		$this->_phrase='';
		$this->_attrs=array();
		$this->_order='';
		$this->_max=-1;
		$this->_skip=0;
		$this->_options=0;
		$this->_auxiliary=32;
		$this->_distinct='';
		$this->_mask=0;
	}
	
	function set_phrase($phrase){
		$this->_phrase=$phrase;
	}
	function add_attr($expr){
		array_push($this->_attrs,$expr);
	}
	function set_order($expr){
		$this->_order=$expr;
	}
	function set_max($max){
		$this->_max=0+$max;
	}
	function set_skip($skip){
		if($skip>=0){ $this->_skip=$skip; }
	}
	function set_options($options){
		$this->options |= $options;
	}
	function set_auxiliary($min){
		$this->_auxiliary=$min;
	}
	function set_distinct($name){
		$this->_distinct=$name;
	}
	function set_mask($mask){
		$this->_mask=$mask & 0x7fffffff;
	}
	
	function phrase(){    return $this->_phrase; }
	function attrs(){     return $this->_attrs; }
	function order(){     return $this->_order; }
	function max(){       return $this->_max; }
	function skip(){      return $this->_skip; }
	function options(){   return $this->_options; }
	function auxiliary(){ return $this->_auxiliary; }
	function distinct(){  return $this->_distinct; }
	function mask(){      return $this->_mask; }
}
