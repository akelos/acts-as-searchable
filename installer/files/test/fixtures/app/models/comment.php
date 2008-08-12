<?php

class Comment extends ActiveRecord
{

    var $acts_as = array('searchable'=>array('if_changed'=>array('article_id')));
    
    var $belongsTo = 'article';
    
}


?>