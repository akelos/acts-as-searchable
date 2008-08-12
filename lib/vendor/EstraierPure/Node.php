<?php
# デフォルト動作時の最大取得件数
define('EST_MAX',2000);
require_once('Condition.php');
require_once('ResultDocument.php');
require_once('NodeResult.php');
require_once('Document.php');

class EstraierPure_Node {
	// setter 由来
	private $_url=null;
	private $_proxy=null;
	private $_timeout=10; // PHP default では max_execution_time=30 なので、30 ではなく 10 に短縮
	private $_authusername=null;
	private $_authpassword=null;
	private $_wwidth;
	private $_hwidth;
	private $_awidth;
	// inform 由来
	private $_name;
	private $_label;
	private $_dnum; // doc num
	private $_wnum; // word num
	private $_size;
	private $_admins;
	private $_users;
	private $_links;
	// 状態保持
	private $_status=-1;
	private $_info_init=false;
	
	function set_url($url){
		$this->_url=$url;
		$this->_info_init=false;
	}
	
	function set_proxy($host, $port){
		$this->_proxy='tcp://'.$host.':'.$port;
		$this->_info_init=false;
	}
	
	function set_timeout($sec){
		$this->_timeout=$sec;
	}
	
	function set_auth($name,$password){
		$this->_authusername=$name;
		$this->_authpassword=$password;
	}
	
	function status(){
		return $this->_status;
	}
	
	function sync(){
		$this->_http_post($this->_url.'/sync', array());
		if($this->_status==200){
			return true;
		}else{
			return false;
		}
	}
	
	function optimize(){
		$this->_http_post($this->_url.'/optimize', array());
		if($this->_status==200){
			return true;
		}else{
			return false;
		}
	}
	
	function put_doc($doc){
		$this->_info_init=false;
		if(!is_object($doc)){
			trigger_error('argument shuold be an instance of EstraierPure_Document.');
		}
		$draft = $doc->dump_draft();

		$this->_http_post($this->_url.'/put_doc', $draft);
		if($this->_status==200){
			return true;
		}else{
			return false;
		}
	}
	
	function out_doc($id){
		$this->_info_init=false;
		$this->_http_post($this->_url.'/out_doc', array('id'=>$id));
		if($this->_status==200){
			return true;
		}else{
			return false;
		}
	}
	
	function out_doc_by_uri($uri){
		$this->_info_init=false;
		$this->_http_post($this->_url.'/out_doc', array('uri'=>$uri));
		if($this->_status==200){
			return true;
		}else{
			return false;
		}
	}
	
	function edit_doc($doc){
		$this->_info_init=false;
		$this->_http_post($this->_url.'/edit_doc', $doc->dump_draft());
		if($this->_status==200){
			return true;
		}else{
			return false;
		}
	}
	
	function get_doc($id){
		// GET に変更
		$r=$this->_http_get($this->_url.'/get_doc', array('id'=>$id));
		if($this->_status==200){
			return new EstraierPure_Document($r['body']);
		}else{
			return null;
		}
	}
	
	function get_doc_by_uri($uri){
		// GET に変更
		$r=$this->_http_get($this->_url.'/get_doc', array('uri'=>$uri));
		if($this->_status==200){
			return new EstraierPure_Document($r['body']);
		}else{
			return null;
		}
	}
	
	function get_doc_attr($id, $name){
		// GET に変更
		$r=$this->_http_get($this->_url.'/get_doc_attr', array('id'=>$id, 'attr'=>$name));
		if($this->_status==200){
			return trim($r['body']);
		}else{
			return null;
		}
		return null;
	}
	
	function get_doc_attr_by_uri($uri, $name){
		// GET に変更
		$r=$this->_http_get($this->_url.'/get_doc_attr', array('uri'=>$uri, 'attr'=>$name));
		if($this->_status==200){
			return trim($r['body']);
		}else{
			return null;
		}
		return null;
	}
	
	function etch_doc($id){
		// GET に変更
		$r=$this->_http_get($this->_url.'/etch_doc', array('id'=>$id));
		if($this->_status==200){
			$kwords=array();
			foreach(explode("\n",$r['body']) as $line){
				$kv=explode("\t",$line);
				if(isset($kv[1])){
					$kwords[$kv[0]]=$kv[1];
				}
			}
			return $kwords;
		}else{
			return null;
		}
	}
	
	/**
	 * @return : キーワードが key, そのキーワードのスコアが value となる HASH です。
	 */
	function etch_doc_by_uri($uri){
		// GET に変更
		$r=$this->_http_get($this->_url.'/etch_doc', array('uri'=>$uri));
		if($this->_status==200){
			$kwords=array();
			foreach(explode("\n",$r['body']) as $line){
				$kv=explode("\t",$line);
				if(isset($kv[1])){
					$kwords[$kv[0]]=$kv[1];
				}
			}
			return $kwords;
		}else{
			return null;
		}
	}
	
	function uri_to_id($uri){
		$r=$this->_http_get($this->_url.'/uri_to_id', array('uri'=>$uri));
		if($this->_status==200){
			return trim($r['body']);
		}else{
			return -1;
		}
	}
	
	function name(){
		if(!$this->_info_init){ $this->_set_info(); }
		return $this->_name;
	}
	
	function label(){
		if(!$this->_info_init){ $this->_set_info(); }
		return $this->_label;
	}
	
	function doc_num(){
		if(!$this->_info_init){ $this->_set_info(); }
		return $this->_dnum;
	}
	
	function word_num(){
		if(!$this->_info_init){ $this->_set_info(); }
		return $this->_wnum;
	}
	
	function size(){
		if(!$this->_info_init){ $this->_set_info(); }
		return $this->_size;
	}
	
	function cache_usage(){
		$rs=$this->_http_get($this->_url.'/cacheusage', array());
		if($this->_status==200){
			return $rs['body'];
		}
		return -1;
	}
	
	function admins(){
		if(!$this->_info_init){ $this->_set_info(); }
		return $this->_admins;
	}
	
	function users(){
		if(!$this->_info_init){ $this->_set_info(); }
		return $this->_users;
	}
	
	function links(){
		if(!$this->_info_init){ $this->_set_info(); }
		return $this->_links;
	}
	
	function search($cond, $depth){
		$q=array();
		$q['phrase']=$cond->phrase();
		$ct=1;
		foreach($cond->attrs() as $attr){
			$q['attr'.$ct]=$attr;
			$ct++;
		}
		if($cond->order()){
			$q['order']=$cond->order();
		}
		if($cond->max()>0){
			$q['max']=$cond->max();
		}else{
			$q['max']=EST_MAX;
		}
		if($cond->options()>0){
			$q['options']=$cond->options();
		}
		$q['auxiliary']=$cond->auxiliary();
		if($cond->distinct()){
			$q['distinct']=$cond['distinct'];
		}
		if($depth > 0){
			$q['depth']=$depth;
		}
		$q['wwidth']=$this->_wwidth;
		$q['hwidth']=$this->_hwidth;
		$q['awidth']=$this->_awidth;
		$q['skip']=$cond->skip();
		$q['mask']=$cond->mask();
		$q['nomask0']='on';
		$q['allmask']='on';
		$phrase = $q['phrase'];
        $parts = split(' ',$phrase);
        foreach($parts as $idx=>$part) {
            $part = preg_replace('/^(.*?)\*$/','[BW] \\1',$part);
            $part = preg_replace('/^\*(.*?)$/','[EW] \\1',$part);
            $parts[$idx]=$part;
        }
        $q['phrase']=implode(' ',$parts);
        //$qs=http_build_query($q);
        //$qs=str_replace('%5B','[',$qs);
        //$qs=str_replace('%5D',']',$qs);*/
		
		$rs=$this->_http_get($this->_url.'/search', $q);

		if($this->_status!=200){ return null; }
		
		$hint=array();
		$docs=array();
		// parse http response body
		$lines=explode("\n",$rs['body']);
		if(!isset($lines[0])){ return null; }
		$separator=$lines[0];
		$parts_str=explode($separator, $rs['body']);
		$ct=0;
		foreach($parts_str as $str){
			if(strpos($str,':END')===0){ break; }
			$str=substr($str,1);
			if($ct==0){
				// 必ず空白文字
			}elseif($ct==1){
				// meta part
				$lines=explode("\n",$str);
				foreach($lines as $line){
					if(!$line){ continue; }
					$kv=explode("\t",$line,2);
					$hint[$kv[0]]=$kv[1];
				}
			}else{
				// doc part
				$ps=explode("\n\n",$str,2);
				
				$meta=array();
				$lines=explode("\n",$ps[0]);
				foreach($lines as $line){
					if(strpos($line,'%')===0){
						// %SCORE or %VECTOR
						$wd=explode("\t", $line, 2);
						if(isset($wd[1])){ $meta[$wd[0]]=$wd[1]; }
					}else{
						// システム属性や擬似属性
						$kv=explode('=', $line, 2);
						if(count($kv)==2){ $meta[$kv[0]]=$kv[1]; }
					}
				}
				
				$snippets=$ps[1];
				
				array_push($docs, new EstraierPure_ResultDocument($meta['@uri'],$meta,$snippets,$meta['%VECTOR']));
			}
			$ct++;
		}
		return new EstraierPure_NodeResult($docs, $hint);
	}
	
	function set_snippet_width($wwidth, $hwidth, $awidth){
		$this->_wwidth=$wwidth;
		if($hwidth>=0){ $this->_hwidth=$hwidth; }
		if($awidth>=0){ $this->_awidth=$awidth; }
	}
	
	function set_user($name, $mode){
		$this->_info_init=false;
		$this->_http_post($this->_url.'/_set_user', array('name'=>$name,'mode'=>$mode));
		if($this->_status==200){
			return true;
		}else{
			return false;
		}
	}
	
	function set_link($url, $label, $credit){
		if($credit>=0){
			$this->_http_post($this->_url.'/_set_link', array('label'=>$label,'credit'=>$credit));
		}else{
			$this->_http_post($this->_url.'/_set_link', array('label'=>$label));
		}
		if($this->_status==200){
			return true;
		}else{
			return false;
		}
	}
	
	private function _set_info(){
		$rs=$this->_http_post($this->_url.'/inform', array());
		$parts=explode("\n\n",$rs['body']);
		if(isset($parts[0])){
			// nodename, label, numdoc, numword, size
			$b=explode("\t",$parts[0]);
			$this->_name=$b[0];
			$this->_label=$b[1];
			$this->_dnum=$b[2];
			$this->_wnum=$b[3];
			$this->_size=$b[4];
		}
		if(isset($parts[1])){
			// list of admins
			if($parts[1]){
				$this->_admins= explode("\n",$parts[1]);
			}else{
				$this->_admins= array();
			}
		}
		if(isset($parts[2])){
			// list of guests
			if($parts[2]){
				$this->_users=explode("\n",$parts[2]);
			}else{
				$this->_users=array();
			}
		}
		if(isset($parts[3])){
			// link information
			if($parts[3]){
				$this->_links=explode("\n",$parts[3]);
			}else{
				$this->_links=array();
			}
		}
		$this->_info_init=true;
	}
	
	private function _http_get($url,$params){
		$this->_status=-1;
		$headers=array();
		
		if($this->_proxy){ $opts['proxy']=$this->_proxy; $opts['request_fulluri']=true; }
		if($this->_timeout){ $opts['timeout']=$this->_timeout; }
		if($this->_authusername && $this->_authpassword){
			array_push($headers, "Authorization: Basic ".md5($this->_authusername.":".$this->_authpassword));
		}
		
		$qs=http_build_query($params);
		$opts['header']=join("\r\n",$headers);
		$opts['method']='GET';
		$opts['protocol_version']='1.1';
		$opts['timeout']=20;
		$opts['user_agent']='Akelos';
		$ctx=stream_context_create(array('http'=>$opts));
		$fp=fopen($url.'?'.$qs, 'r', false, $ctx);
		if($fp){
			$body=stream_get_contents($fp);
			$meta=stream_get_meta_data($fp);
			fclose($fp);
			$this->_status=$this->_http_meta_status($meta['wrapper_data']);
			return array('meta'=>$meta,'body'=>$body);
		}
		$this->_status=-1;
		return array('meta'=>null,'body'=>null);
	}
	
	private function _http_post($url,$params){
		$this->_status=-1;
		$headers=array();
		
		$opts['method']='POST';
		if(is_array($params)){
			array_push($headers,'Content-type: application/x-www-form-urlencoded');
		}else{
			array_push($headers,'Content-type: text/x-estraier-draft');
		}
		if($this->_proxy){ $opts['proxy']=$this->_proxy; $opts['request_fulluri']=true; }
		if($this->_timeout){ $opts['timeout']=$this->_timeout; }
		if($this->_authusername && $this->_authpassword){
			array_push($headers, "Authorization: Basic ".base64_encode($this->_authusername.":".$this->_authpassword));
		}
		
		
		
		$qs=$params;
		
		if(is_array($params)){ $qs=http_build_query($params); }
		if($qs){
			$opts['content']=$qs;
		}
		//$qs = str_replace(array('%5B','%5D+'),array('[',']+'),$qs);
		array_push($headers, "Content-length: ".strlen($qs));
		
		$opts['header']=join("\r\n",$headers);
		$ctx=stream_context_create(array('http'=>$opts));
		$fp=fopen($url, 'rb', false, $ctx);
		if($fp){
			$body=stream_get_contents($fp);
			$meta=stream_get_meta_data($fp);
			fclose($fp);
			$this->_status=$this->_http_meta_status($meta['wrapper_data']);
			return array('meta'=>$meta,'body'=>$body);
		}
		$this->_status=-1;
		return array('meta'=>null,'body'=>null);
	}
	
	/**
	 * http stream のメタデータから HTTP status code を抜き出します。
	 * 値が取得できなかった場合は -1 が返ります。
	 */
	private function _http_meta_status($wrapper_data){
		if(isset($wrapper_data[0])){
			$stat=explode(' ',$wrapper_data[0],3);
			if(isset($stat[1])){
				return $stat[1];
			}
		}
		return -1;
	}
}
