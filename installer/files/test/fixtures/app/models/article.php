<?php

class Article extends ActiveRecord
{

    var $acts_as = array('searchable'=>array('searchable_fields'=>array('title','body'),
                                             'attributes'=>array('title',
                                                                 'custom'=>'tags',
                                                                 'cdate'=>'created_at',
                                                                 'comments_count')));
    var $hasMany = 'comments';

}


?>