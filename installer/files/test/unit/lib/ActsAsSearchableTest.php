<?php
require_once(AK_BASE_DIR.DS.'app'.DS.'vendor'.DS.'plugins'.DS.'acts_as_searchable'.DS.'lib'.DS.'ActsAsSearchable.php');
require_once(AK_LIB_DIR.DS.'AkDateParser.php');
class ActsAsSearchabelTest extends AkUnitTest
{
    var $_articles_count = 10;
    
    function setUp()
    {
        $this->installAndIncludeModels('Article');
        $this->installAndIncludeModels('Comment');
        $this->searchable = new ActsAsSearchable(&$this->Article);
        $this->populateTables('articles');
        $this->populateTables('comments');
    }
    
    function _test_friendly_search()
    {
        $this->reindex();
        $results = $this->Article->friendlySearch('article1 +body1');
        $this->assertTrue(1,count($results));
        $this->assertEqual(1,$results[0]->id);
        
        $results = $this->Article->friendlySearch('article1 article2');
        $this->assertTrue(2,count($results));
        $this->assertEqual(1,$results[0]->id);
        $this->assertEqual(2,$results[1]->id);
        
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
        $methods = array();
        foreach ($observers as $observer) {
            if (strtolower(get_class($observer)) == 'actsassearchable') {
                $methods=get_class_methods(get_class($observer));
                break;
            }
        }
        $this->assertTrue(!empty($methods));
        
        
        $this->assertTrue(in_array('afterUpdate', $methods));
        $this->assertTrue(in_array('afterCreate', $methods));
        $this->assertTrue(in_array('afterDestroy', $methods));
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
        $this->reindex();
        $index = $this->Article->estraierIndex();
        $count = $this->Article->count();
        $this->assertEqual($count,count($index));
        $this->assertEqual(3,$count);
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
        $a->destroy();

    }
    
    function test_after_destroy_hook()
    {
        $article = &$this->Article->findFirst();
        $article->destroy();
        $this->assertTrue(false==$article->estraierDoc());
    }
    
    function test_fulltext_search()
    {
        $this->reindex();
        $matches = $this->Article->fulltextSearch('mauris');
        $this->assertEqual(1,count($matches));
    }
    
    function test_fulltext_search_with_attributes()
    {
        $this->reindex();
        $matches = $this->Article->fulltextSearch('',array('attributes'=>'custom STRINC akelos'));
        $this->assertEqual(2,count($matches));
        $this->assertEqual(array(2,3),array($matches[0]->id,$matches[1]->id));
        
    }
    
    function test_fulltext_search_with_attributes_array()
    {
        $this->reindex();
        $matches = $this->Article->fulltextSearch('',array('attributes'=>array('custom STRINC akelos','@title STRBW lorem')));
        $this->assertEqual(1,count($matches));
        $this->assertEqual(array(3),array($matches[0]->id));
    }
    
    function test_fulltext_search_with_number_attribute()
    {
        $this->reindex();
        $matches = $this->Article->fulltextSearch('',array('attributes'=>array('comments_count NUMGE 1')));
        $this->assertEqual(1,count($matches));
        $this->assertEqual(1,$matches[0]->id);
    }
    
    function test_fulltext_search_with_date_attribute()
    {
        $this->reindex();
        $matches = $this->Article->fulltextSearch('ipsum',array('attributes'=>array('@cdate NUMLE '.AkDateParser::parse('1.year.from.now'))));
        $this->assertEqual(1,count($matches));
        $this->assertEqual(3,$matches[0]->id);
        
        $matches = $this->Article->fulltextSearch('',array('attributes'=>array('@cdate NUMLE '.AkDateParser::parse('6.days.ago'))));
        $this->assertEqual(1,count($matches));
        $this->assertEqual(1,$matches[0]->id);
    }
    
    function test_fulltext_search_with_ordering()
    {
        $this->reindex();
        $expectedIds = array(1,2,3);
        $matches = $this->Article->fulltextSearch('',array('order'=>'db_id NUMA','limit'=>3,'raw_matches'=>true));
        $ids = array($matches[0]->attr('db_id'),$matches[1]->attr('db_id'),$matches[2]->attr('db_id'));
        $this->assertEqual($expectedIds,$ids);
        
        $expectedIds = array(3,2,1);
        $matches = $this->Article->fulltextSearch('',array('order'=>'db_id NUMD','limit'=>3,'raw_matches'=>true));
        $ids = array($matches[0]->attr('db_id'),$matches[1]->attr('db_id'),$matches[2]->attr('db_id'));
        $this->assertEqual($expectedIds,$ids);
    }
    
    function test_fulltext_search_with_pagination()
    {
        $this->reindex();
        $expectedIds = array(1,2);
        $matches = $this->Article->fulltextSearch('',array('order'=>'db_id NUMA','limit'=>2,'raw_matches'=>true));
        $ids = array($matches[0]->attr('db_id'),$matches[1]->attr('db_id'));
        $this->assertEqual($expectedIds,$ids);
        
        $expectedIds = array(3,2);
        $matches = $this->Article->fulltextSearch('',array('order'=>'db_id NUMD','limit'=>2,'raw_matches'=>true));
        $ids = array($matches[0]->attr('db_id'),$matches[1]->attr('db_id'));
        $this->assertEqual($expectedIds,$ids);
        
        $expectedIds = array(2,3);
        $matches = $this->Article->fulltextSearch('',array('order'=>'db_id NUMA','limit'=>2,'offset'=>1,'raw_matches'=>true));
        $ids = array($matches[0]->attr('db_id'),$matches[1]->attr('db_id'));
        $this->assertEqual($expectedIds,$ids);
        
        $expectedIds = array(2,1);
        $matches = $this->Article->fulltextSearch('',array('order'=>'db_id NUMD','limit'=>2,'offset'=>1,'raw_matches'=>true));
        $ids = array($matches[0]->attr('db_id'),$matches[1]->attr('db_id'));
        $this->assertEqual($expectedIds,$ids);
    }
    
    
    function test_fulltext_search_with_no_results()
    {
        $this->reindex();
        $matches = $this->Article->fulltextSearch('i do not exist');
        $this->assertEqual(0,count($matches));
    }
    
    function test_fulltext_search_with_find()
    {
        $this->reindex();
        $matches = $this->Article->fulltextSearch('',array('find'=>array('order'=>'title ASC')));
        $this->assertEqual(3,count($matches));
        $expectedIds = array(1,3);
        $ids = array($matches[0]->id,$matches[1]->id);
        $this->assertEqual($expectedIds, $ids);
        
        $matches = $this->Article->fulltextSearch('',array('find'=>array('order'=>'title DESC')));
        $this->assertEqual(3,count($matches));
        $expectedIds = array(2,3);
        $ids = array($matches[0]->id,$matches[1]->id);
        $this->assertEqual($expectedIds, $ids);
        
    }
    
    function test_fulltext_with_invalid_find_parameters()
    {
        $this->reindex();
        $matches = $this->Article->fulltextSearch('',array('idonotexist'=>1));
        $this->assertTrue(!empty($matches));
    }
    
    function test_act_if_changed()
    {
        $comment = $this->Comment->findFirst();
        $this->assertFalse($comment->searchable->_changed(&$comment));
        $comment->article_id=12312;
        $this->assertTrue($comment->searchable->_changed(&$comment));
    }
    
    function test_act_changed_attributes()
    {
        $article = $this->Article->findFirst();
        $this->assertFalse($article->searchable->_changed(&$article));
        $article->tags="test 1231 23";
        $this->assertTrue($article->searchable->_changed(&$article));
        
        $article->save();
        $this->assertFalse($article->searchable->_changed(&$article));
    }

    function reindex()
    {
        $this->Article->reindex();
        sleep(5);
    }
    
    
}
?>