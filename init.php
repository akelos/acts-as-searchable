<?php

class ActsAsSearchablePlugin extends AkPlugin
{
    function load()
    {
        require_once($this->getPath().DS.'lib'.DS.'ActsAsSearchable.php');
    }
}

?>