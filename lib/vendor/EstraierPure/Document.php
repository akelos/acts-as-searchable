<?php
class EstraierPure_Document {
	var $_id=-1;
	var $_attrs=array();
	var $_dtexts=array();
	var $_htexts=array();
	var $_kwords=array();
	var $_score=-1;
	
	function __construct($draft=''){
		if($draft){
			$hb=explode("\n\n",$draft,2);
			foreach(explode("\n",$hb[0]) as $line){
				if(strpos($line,'%')===0){
					if(strpos($line,'%VECTOR')===0){
						$kv=explode("\t",$line);
						for($i=0;$i<(count($kv)-1)/2;$i++){
							$this->_kwords[$kv[2*$i+1]]=$kv[2*$i+2];
						}
					}
					if(strpos($line,'%SCORE')===0){
						$kv=explode("\t",$line);
						$this->_score=$kv[1];
					}
					continue;
				}
				if($line){
					$kv=explode('=',$line,2);
					$this->_attrs[$kv[0]]=$kv[1];
					if($kv[0]=='@id'){ $this->_id=$kv[1]; }
				}
			}
			foreach(explode("\n",$hb[1]) as $line){
				if(!$line){ continue; }
				if(strpos($line,"\t")===0){
					$this->add_hidden_text(substr($line,1));
				}else{
					$this->add_text($line);
				}
			}
		}
	}
	
	function add_attr($name,$value){
		$this->_attrs[$name]=trim($value);
	}
	
	function add_text($text){
		array_push($this->_dtexts,trim($text));
	}
	
	function add_hidden_text($text){
		array_push($this->_htexts,trim($text));
	}
	
	function set_keywords($kwords){
		$this->_kwords=$kwords;
	}
	
	function set_score($score){
		$this->_score=$score;
	}
	
	function id(){
		return $this->_id;
	}
	
	function attr_names(){
		return array_keys($this->_attrs);
	}
	
	function attr($name){
		return $this->_attrs[$name];
	}
	
	function texts(){
		return $this->_dtexts;
	}
	
	function cat_texts(){
		return implode(' ',$this->_dtexts);
	}
	
	function keywords(){
		return $this->_kwords;
	}
	
	function score(){
		if($this->_score<0){ return -1; }
		return $this->_score;
	}
	
	function dump_draft(){
		$lines=array();
		foreach($this->_attrs as $k=>$v){
			array_push($lines, $k.'='.$v);
		}
		if(!empty($this->_kwords)){
			$kws=array('%VECTOR');
			foreach($this->_kwords as $k=>$v){
				array_push($kws, $k);
				array_push($kws, $v);
			}
			array_push($lines, join("\t",$kws));
		}
		if($this->_score>0){
			array_push($lines, "%SCORE\t".$this->_score);
		}
		
		$body=array();
		foreach($this->_dtexts as $txt){
			array_push($body, $txt);
		}
		foreach($this->_htexts as $txt){
			array_push($body, "\t".$txt);
		}
		return join("\n",$lines)."\n\n".join("\n",$body);
	}
}
