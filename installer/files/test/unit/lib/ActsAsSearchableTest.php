<?php
require_once(AK_BASE_DIR.DS.'app'.DS.'vendor'.DS.'plugins'.DS.'acts_as_searchable'.DS.'lib'.DS.'ActsAsSearchable.php');

class ActsAsSearchabelTest extends AkUnitTest
{
    var $_articles_count = 10;
    
    function setUp()
    {
        $this->installAndIncludeModels('Article');
        $this->installAndIncludeModels('Comment');
        $this->searchable = new ActsAsSearchable(&$this->Article);
        $this->create_articles();
    }
    
    function test_defaults()
    {
        $expectedConfig = array('node'=>'akelos',
                                'host'=>'localhost',
                                'port'=>1978,
                                'user'=>'akelos',
                                'password'=>'akelos');
        $this->assertEqual($expectedConfig, $this->searchable->_estraier_config);
    }
    
    function test_hooks_presence()
    {
        $searchable = null;
        $observers = &$this->Article->getObservers();
        foreach ($observers as $observer) {
            if (strtolower(get_class($observer)) == 'actsassearchable') {
                $searchable = &$observer;
            }
        }
        $this->assertTrue(!empty($searchable));
        $this->assertTrue(method_exists($searchable,'afterUpdate'));
        $this->assertTrue(method_exists($searchable,'afterCreate'));
        $this->assertTrue(method_exists($searchable,'afterDestroy'));
    }
    
    function test_connection()
    {
        $this->assertIsA($this->searchable->_estraier_connection,'EstraierPure_Node');
    }
    
    function test_reindex()
    {
        
        $this->Article->clearIndex();
        $index = $this->Article->estraierIndex();
        $this->assertEqual(0,count($index));
        $this->Article->reindex();
        $index = $this->Article->estraierIndex();
        $count = $this->Article->count();
        $this->assertEqual($count,count($index));
        $this->assertEqual($this->_articles_count,$count);
    }
    function create_articles()
    {
        for ($i=0;$i<$this->_articles_count;$i++) {
            $article = new Article();
            $article->title='article'.$i;
            $article->body='body'.$i;
            if ($i<($this->_articles_count/2)) {
                $article->tags = 'firsthalf';
            } else {
                $article->tags = 'secondhalf';
            }
            $article->save();
            $comment = new Comment();
            $comment->article = &$article;
            $comment->body = 'Comment'.$article->id.'-'.$i;
            $comment->save();
        }
        
    }
    function test_clear_index()
    {
        $this->Article->clearIndex();
        $index = $this->Article->estraierIndex();
        $this->assertEqual(0,count($index));
    }
    
    function test_after_update_hook()
    {
        $article = &$this->Article->findFirst();
        $article->body='test';
        $article->save();
        
        $article->updateAttribute('body','updated via tests');
        $doc = &$article->estraierDoc();
        $this->assertEqual($article->id,$doc->attr('db_id'));
        $this->assertEqual(get_class($article),$doc->attr('type'));
        $retrievedDoc = $this->searchable->_estraier_connection->get_doc($doc->attr('@id'));
        $texts = $retrievedDoc->texts();
        $this->assertTrue(in_array($article->body,$texts));
    
    }
    
    function test_after_create_hook()
    {
        $a = &$this->Article->create(array("title" => "title created via tests", "body" => "body created via tests", "tags" => "ruby weblog"));
        $doc = &$a->estraierDoc();
        $this->assertEqual($a->id,$doc->attr('db_id'));
        $this->assertEqual(get_class($a),$doc->attr('type'));
        $this->assertEqual($a->tags,$doc->attr('custom'));
        $this->assertEqual($a->title,$doc->attr('@title'));
        $retrievedDoc = $this->searchable->_estraier_connection->get_doc($doc->attr('@id'));
        $texts = $retrievedDoc->texts();
        $this->assertEqual(array($a->title,$a->body),$texts);

    }
    
    function test_after_destroy_hook()
    {
        $article = &$this->Article->findFirst();
        $article->destroy();
        $this->assertTrue(false==$article->estraierDoc());
    }
    
    function test_fulltext_search()
    {
        $this->Article->reindex();
        $matches = $this->Article->fulltextSearch('article'.rand(0,$this->_articles_count));
        $this->assertEqual(1,count($matches));
    }
    
    function test_fulltext_search_with_attributes()
    {
        $this->Article->reindex();
        $matches = $this->Article->fulltextSearch('article*',array('attributes'=>'custom STRINC second'));
        $this->assertEqual(5,count($matches));
        
    }
    
    function test_fulltext_search_with_attributes_array()
    {
        $this->Article->reindex();
        $matches = $this->Article->fulltextSearch('article*',array('attributes'=>array('custom STRINC second','@title STRBW article')));
        $this->assertEqual(5,count($matches));
    }
}
?>