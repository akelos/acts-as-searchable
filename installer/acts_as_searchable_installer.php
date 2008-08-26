<?php
define('AK_AASE_PLUGIN_FILES_DIR', AK_APP_PLUGINS_DIR.DS.'acts_as_searchable'.DS.'installer'.DS.'files');

class ActsAsSearchableInstaller extends AkPluginInstaller
{

    var $_newModelMethods = array('&fulltextSearch'=>'
    function &fulltextSearch($query, $options = array()) {
        $matches = array();
        if (isset($this->searchable) && method_exists($this->searchable,"fulltextSearch")) {
            $matches = &$this->searchable->fulltextSearch(&$this,$query, $options);
        }
        return $matches;
    }
    ','friendlySearch'=>'
    function friendlySearch($query, $options = array()) {
        $matches = array();
        if (isset($this->searchable) && method_exists($this->searchable,"friendlySearch")) {
            $matches = &$this->searchable->friendlySearch(&$this, $query, $options);
        }
        return $matches;
    }
    ','clearIndex'=>'
    function clearIndex() {
        if (isset($this->searchable) && method_exists($this->searchable,"clearIndex")) {
            return $this->searchable->clearIndex(&$this);
        }
        return false;
    }
    ','reindex'=>'
    function reindex($options = array()) {
        if (isset($this->searchable) && method_exists($this->searchable,"reindex")) {
            return $this->searchable->reindex(&$this, $options);
        }
        return false;
    }
    ','&estraierIndex'=>'
    function &estraierIndex() {
        $index = array();
        if (isset($this->searchable) && method_exists($this->searchable,"estraierIndex")) {
             $index = &$this->searchable->estraierIndex(&$this);
        }
        return $index;
    }
    ','&estraierDoc'=>'
    function &estraierDoc() {
        $doc = null;
        if (isset($this->searchable) && method_exists($this->searchable,"estraierDoc")) {
             $doc = &$this->searchable->estraierDoc(&$this);
        }
        return $doc;
    }
    ');
    function down_1()
    {
        $this->removeNewMethodsFromSharedModel();
        echo "Uninstalling the acts_as_searchable plugin migration\n";
    }
    
    function up_1()
    {
        if (!AK_PHP5) {
            die("This plugin is only working on PHP5\n");
        }
        $this->files = Ak::dir(AK_AASE_PLUGIN_FILES_DIR, array('recurse'=> true));
        empty($this->options['force']) ? $this->checkForCollisions($this->files) : null;
        $this->copyFiles();
        
        $this->addNewMethodsToSharedModel();
        echo "\n\nInstallation completed\n";
    }
    
    function addNewMethodsToSharedModel()
    {
        foreach ($this->_newModelMethods as $name=>$method) {
            echo "Adding method ActiveRecord::$name method: ";
            $res = $this->addMethodToBaseAR($name,$method);
            echo $res===true?'[OK]':'[FAIL]:'."\n-- ".$res;
            echo "\n";
        }
    }
    function removeNewMethodsFromSharedModel()
    {
        foreach ($this->_newModelMethods as $name=>$method) {
            $this->removeMethodFromBaseAR($name);
        }
    }
    
    function copyFiles()
    {
        $this->_copyFiles($this->files);
    }
    function _copyFiles($directory_structure, $base_path = AK_AASE_PLUGIN_FILES_DIR)
    {
        foreach ($directory_structure as $k=>$node){
            $path = $base_path.DS.$node;
            if(is_dir($path)){
                echo 'Creating dir '.$path."\n";
                $this->_makeDir($path);
            }elseif(is_file($path)){
                echo 'Creating file '.$path."\n";
                $this->_copyFile($path);
            }elseif(is_array($node)){
                foreach ($node as $dir=>$items){
                    $path = $base_path.DS.$dir;
                    if(is_dir($path)){
                        echo 'Creating dir '.$path."\n";
                        $this->_makeDir($path);
                        $this->_copyFiles($items, $path);
                    }
                }
            }
        }
    }

    function _makeDir($path)
    {
        $dir = str_replace(AK_AASE_PLUGIN_FILES_DIR, AK_BASE_DIR,$path);
        if(!is_dir($dir)){
            mkdir($dir);
        }
    }

    function _copyFile($path)
    {
        $destination_file = str_replace(AK_AASE_PLUGIN_FILES_DIR, AK_BASE_DIR,$path);
        copy($path, $destination_file);
        $source_file_mode =  fileperms($path);
        $target_file_mode =  fileperms($destination_file);
        if($source_file_mode != $target_file_mode){
            chmod($destination_file,$source_file_mode);
        }
    }
    function checkForCollisions(&$directory_structure, $base_path = AK_AASE_PLUGIN_FILES_DIR)
    {
        foreach ($directory_structure as $k=>$node){
            if(!empty($this->skip_all)){
                return ;
            }
            $path = str_replace(AK_AASE_PLUGIN_FILES_DIR, AK_BASE_DIR, $base_path.DS.$node);
            if(is_file($path)){
                $message = Ak::t('File %file exists.', array('%file'=>$path));
                $user_response = AkInstaller::promptUserVar($message."\n d (overwrite mine), i (keep mine), a (abort), O (overwrite all), K (keep all)", 'i');
                if($user_response == 'i'){
                    unset($directory_structure[$k]);
                }    elseif($user_response == 'O'){
                    return false;
                }    elseif($user_response == 'K'){
                    $directory_structure = array();
                    return false;
                }elseif($user_response != 'd'){
                    echo "\nAborting\n";
                    exit;
                }
            }elseif(is_array($node)){
                foreach ($node as $dir=>$items){
                    $path = $base_path.DS.$dir;
                    if(is_dir($path)){
                        if($this->checkForCollisions($directory_structure[$k][$dir], $path) === false){
                            $this->skip_all = true;
                            return;
                        }
                    }
                }
            }
        }
    }

}
?>