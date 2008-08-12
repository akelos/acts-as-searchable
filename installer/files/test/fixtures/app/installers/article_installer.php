<?php
class ArticleInstaller extends AkInstaller
{
    function up_1()
    {
        $this->createTable('articles','id,title,body,tags,comments_count,created_at');
    }
    
    function down_1()
    {
        $this->dropTable('articles');
    }
}