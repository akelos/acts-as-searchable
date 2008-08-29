<?php

class ActsAsSearchableInstaller extends AkPluginInstaller
{

    var $auto_install_files = true;
    var $auto_install_extensions = true;
    var $auto_remove_extensions = true;
    
    var $php_min_version = 5.0;
    
    function down_1()
    {
        echo "Uninstalling the acts_as_searchable plugin migration\n";
    }

    
    function up_1()
    {
        echo "\n\nInstallation completed\n";
    }
    
}
?>