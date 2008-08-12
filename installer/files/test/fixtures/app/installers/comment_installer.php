<?php
class CommentInstaller extends AkInstaller
{
    function up_1()
    {
        $this->createTable('comments','id,article_id,body');
    }
    
    function down_1()
    {
        $this->dropTable('comments');
    }
}