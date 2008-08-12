<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

// +----------------------------------------------------------------------+
// | Akelos Framework - http://www.akelos.org                             |
// +----------------------------------------------------------------------+
// | Copyright (c) 2002-2007, Akelos Media, S.L.  & Bermi Ferrer Martinez |
// | Released under the GNU Lesser General Public License, see LICENSE.txt|
// +----------------------------------------------------------------------+
/**
 see http://svn.viney.net.nz/things/rails/plugins/acts_as_taggable_on_steroids/lib/acts_as_taggable.rb
 */
/**
 * @package ActiveRecord
 * @subpackage Behaviours
 * @author Arno Schneider <arno a.t. bermilabs dot com>
 * @copyright Copyright (c) 2002-2007, Akelos Media, S.L. http://www.akelos.org
 * @license GNU Lesser General Public License <http://www.gnu.org/copyleft/lesser.html>
 */

require_once(AK_LIB_DIR.DS.'AkActiveRecord'.DS.'AkObserver.php');

/**
 * Specify this act if you want to provide fulltext search capabilities to your model via Hyper Estraier. This
 * assumes a setup and running Hyper Estraier node accessible through the HTTP API provided by the EstraierPure
 * Ruby module (which is bundled with this plugin).
 *
 * The act supplies appropriate hooks to insert, update and remove documents from the index when you update your
 * model data, create new objects or remove them from your database. For the initial indexing a convenience
 * class method <tt>reindex!</tt> is provided.
 *
 * Example:
 *
 *   class Article extends ActiveRecord
 *   {
 *     var $acts_as = 'searchable';
 *   }
 *
 *   $article->reindex();
 *
 * As soon as your model data has been indexed you can make use of the <tt>fulltextSearch</tt> class method
 * to search the index and get back instantiated matches.
 *
 *    $results = $article->fulltextSearch('akelos')
 *
 *  Configuration done via yaml:
 *
 *  config/estraier.yaml
 *
 *         host: localhost
 *         user: admin
 *         password: admin
 *         port: 1978
 *         node: akelos
 */
class ActsAsSearchable extends AkObserver
{
    var $_instance;
    var $_attributes;
    var $_searchable_fields;
    var $_if_changed;
    var $_changed_attributes = array();
    var $_trigger_update_attributes = array();
    var $_estraier_config = array();
    var $_estraier_connection;
    var $_use_after_instantiate = false;

    /**
     *   == Configuration options
     *
     *      <tt>searchable_fields</tt> - Fields to provide searching and indexing for (default: 'body')
     *      <tt>attributes</tt> - Additional attributes to store in Hyper Estraier with the appropriate method supplying the value
     *      <tt>if_changed</tt> - Extra list of attributes to add to the list of attributes that trigger an index update when changed
     *
     * Examples:
     *
     *   var $acts_as = array('searchable'=>array('attributes' => array('title','blog' => 'blog_title'), 'searchable_fields' => array('title', 'body'));
     *
     * This would store the return value of the <tt>title</tt> method in the <tt>title</tt> attribute and the return value of the
     * <tt>blog_title</tt> method in the <tt>blog</tt> attribute. The contents of the <tt>title</tt> and <tt>body</tt> columns
     * would end up being indexed for searching.
     *
     * == Attribute naming
     *
     * Attributes that match the reserved names of the Hyper Estraier system attributes are mapped automatically. This is something
     * to keep in mind for custom ordering options or additional query constraints in <tt>fulltextSearch</tt>
     * For a list of these attributes see <tt>EstraierPure::SYSTEM_ATTRIBUTES</tt> or visit:
     *
     *   http://hyperestraier.sourceforge.net/uguide-en.html#attributes
     *
     * From the example above:
     *
     *   $model->fulltextSearch('query', array('order' => '@title STRA'))               # Returns results ordered by title in ascending order
     *   $model->fulltextSearch('query', array('attributes' => 'blog STREQ poocs.net')) # Returns results with a blog attribute of 'poocs.net'
     *
     *
     * @param AkActiveRecord $ActiveRecordInstance
     * @param array $options
     * @return ActsAsSearchable
     */
    function ActsAsSearchable(&$ActiveRecordInstance, $options = array())
    {
        $this->_instance = &$ActiveRecordInstance;
        $this->observe(&$this->_instance);
        $this->init($options);
        if (method_exists($ActiveRecordInstance,'afterInstantiate')) {
            $this->_use_after_instantiate = true;
        }
    }

    function init($options = array())
    {
        $default_options = array('searchable_fields'=>array('body'),
                                 'attributes'=>array('title'),
                                 'if_changed'=>array());

        $options = array_merge($default_options, $options);

        $this->_searchable_fields = $options['searchable_fields'];
        $this->_attributes = $options['attributes'];
        $this->_if_changed = $options['if_changed'];

        $this->_trigger_update_attributes = array_merge($this->_searchable_fields,
        array_values($this->_attributes),
        $this->_if_changed);
        $this->_trigger_update_attributes = array_unique($this->_trigger_update_attributes);

        $server_default_options = array('node'=>'akelos',
                                        'host'=>'localhost',
                                        'port'=>1978,
                                        'user'=>'akelos',
                                        'password'=>'akelos');

        $server_options = Ak::getSettings('estraier',false);

        if (is_array($server_options)) {
            $server_options = array_merge($server_default_options, $server_options);
        } else {
            $server_options = $server_default_options;
        }

        $this->_estraier_config = $server_options;

        $this->_connectEstraier();
    }
    function instantiate(&$record)
    {
        $this->_buildTriggerUpdateAttributes();
        return $this->_registerUnchangedAttributeValues(&$record);
    }
    function _buildTriggerUpdateAttributes()
    {
        $this->_trigger_update_attributes = array_merge($this->_searchable_fields,
        array_values($this->_attributes),
        $this->_if_changed);
        $this->_trigger_update_attributes = array_unique($this->_trigger_update_attributes);
    }

    function &fulltextSearch(&$record, $query, $options = array())
    {
        $default_options = array('limit'=>100,'offset'=>0);
        $parameters = array('available_options'=>array('limit', 'offset',
                                                       'order', 'attributes',
                                                       'raw_matches', 'find'));

        Ak::parseOptions(&$options,$default_options,$parameters);

        $find_options = isset($options['find'])?$options['find']:array();
        if (isset($find_options['limit'])) {
            unset($find_options['limit']);
        }
        if (isset($find_options['offset'])) {
            unset($find_options['offset']);
        }

        $cond = &new EstraierPure_Condition;
        $cond->set_phrase($query);
        $cond->add_attr('type STREQ '.get_class($record));
        if (isset($options['attributes'])) {
            if (!is_array($options['attributes'])) {
                $options['attributes'] = array($options['attributes']);
            }
            foreach ($options['attributes'] as $attr) {
                if (!empty($attr)) {
                    $cond->add_attr($attr);
                }
            }
        }
        $searchOptions = array();

        isset($options['limit'])?$cond->set_max($options['limit']):null;
        isset($options['offset'])?$cond->set_skip($options['offset']):null;
        isset($options['order'])?$cond->set_order($options['order']):null;
        isset($options['raw_matches']) && $options['raw_matches']==true? $raw_matches = true:$raw_matches=false;
        $matches = array();
        $res = &$this->_estraier_connection->search($cond, 0);

        if ($res) {
            // for each document in the result
            if ($res->doc_num()<1) return $matches;

            $db_ids = array();
            $docs = array();
            for($i=0;$i<$res->doc_num();$i++) {
                $doc = &$res->get_doc($i);
                if ($raw_matches) {
                    $docs[] = clone $doc;
                } else {
                    $db_ids[] = $doc->attr('db_id');
                }
            }
            if ($raw_matches) {
                $matches = &$docs;
            } else {
                if (!isset($find_options['conditions'])) {
                    $find_options['conditions']='id IN ('.implode(',',$db_ids).')';
                } else {
                    $find_options['conditions'].=' AND id IN ('.implode(',',$db_ids).')';
                }
                $matches = &$record->find('all',$find_options);
            }
            if (!$matches) $matches = array();

            return $matches;
        }

        return $matches;
    }

    function afterCreate(&$record)
    {
        $res = $this->_addToIndex(&$record);
        $this->_clearChangedAttributes(&$record);
        return $res;
    }
    function afterUpdate(&$record)
    {
        $res = $this->_updateIndex(&$record);
        $this->_clearChangedAttributes(&$record);
        return $res;
    }
    function afterDestroy(&$record)
    {
        return $this->_removeFromIndex(&$record);
    }
    function afterInstantiate(&$record)
    {
        $this->_registerUnchangedAttributeValues(&$record);
    }

    function _addToIndex(&$record)
    {
        $doc = &$this->_documentObject(&$record);
        if (!($res=$this->_estraier_connection->put_doc($doc))) {
            /**$stack = &EstraierPure_Utility::errorstack();
             $errorText = 'Unknown';
             if ($stack->hasErrors()) {
             $errorText=$stack->getErrors();
             if (is_array($errorText)) {
             $errorText = implode(",\n", $errors);
             }
             }*/
            $errorText = 'Could not save doc';
            $record->addErrorToBase(Ak::t("Could not index record. Error: \n\n %error",array('%error'=>$errorText)));
            return false;
        }
        return true;
    }
    function _updateIndex(&$record, $force = false)
    {
        $res = true;
        if (!$this->_use_after_instantiate || $force || $this->_changed(&$record)) {
            $this->_removeFromIndex(&$record);
            $res = $this->_addToIndex(&$record);

        }
        return $res;
    }
    function _removeFromIndex(&$record)
    {
        $doc = &$this->estraierDoc(&$record);
        $res = true;
        if ($doc) {
            $res = $this->_estraier_connection->out_doc($doc->attr('@id'));
        }
        return $res;
    }


    function _changed(&$record, $attribute = null)
    {
        $this->_buildTriggerUpdateAttributes();
        $checkAttributes = $attribute != null? array($attribute):$this->_trigger_update_attributes;
        foreach ($checkAttributes as $attribute) {
            if (isset($record->acts_as_searchable_original_attributes[$attribute])) {
                $attributes = $record->getColumns();
                $attributes = array_keys($attributes);
                if (in_array($attribute,$attributes)) {
                    $currentValue = $record->get($attribute);
                } else if (method_exists($record, $attribute)) {
                    $currentValue = @$record->$attribute();
                } else {
                    $currentValue = 0;
                }
                $changed = md5($currentValue)!=$record->acts_as_searchable_original_attributes[$attribute];
            } else {
                $changed = false;
            }
            if ($changed) {
                return $changed;
            }
        }
        return false;
    }
    function _clearChangedAttributes(&$record)
    {
        return $this->_registerUnchangedAttributeValues(&$record);
    }

    function _registerUnchangedAttributeValues(&$record)
    {
        $attributes = $record->getColumns();
        $attributes = array_keys($attributes);
        $orgValues = array();
        foreach ($this->_trigger_update_attributes as $trigger) {
            if (in_array($trigger,$attributes)) {
                $orgValues[$trigger] = md5($record->get($trigger));
            } else if (method_exists($record,$trigger)) {
                $orgValues[$trigger] = md5(@$record->$trigger());
            } else {
                $orgValues[$trigger] = md5(0);
            }
        }
        $record->acts_as_searchable_original_attributes = $orgValues;
        return $orgValues;
    }

    function _writeChangedAttributes($attributes)
    {

    }

    function _connectEstraier()
    {
        require_once(dirname(__FILE__).DS.'vendor'.DS.'EstraierPure'.DS.'Node.php');
        //require_once(AK_VENDOR_DIR.DS.'pear'.DS.'EstraierPure'.DS.'estraierpure.php');
        $this->_estraier_connection = &new EstraierPure_Node;
        @$this->_estraier_connection->set_url("http://{$this->_estraier_config['host']}:{$this->_estraier_config['port']}/node/{$this->_estraier_config['node']}");
        @$this->_estraier_connection->set_auth($this->_estraier_config['user'],$this->_estraier_config['password']);
    }

    function estraierDoc(&$record)
    {
        $cond = &new EstraierPure_Condition;
        $cond->add_attr('db_id STREQ '.$record->id);
        $cond->add_attr('type STREQ '.get_class($record));
        //$cond->phrase='';
        $res = &$this->_estraier_connection->search($cond, 0);
        if ($res) {
            // for each document in the result
            if ($res->doc_num()<1) return false;
            $doc = &$res->get_doc(0);
            return $doc;
        }
        return false;
    }

    function &_documentObject(&$record)
    {
        $doc = &new EstraierPure_Document;
        $doc->add_attr('db_id', $record->id);
        $doc->add_attr('type', get_class($record));
        /**
         * generate uri here which is really pointing to the product
         */
        $doc->add_attr('@uri',"/".get_class($record).'/'.$record->id);

        if (!empty($this->_attributes)) {
            foreach ($this->_attributes as $name=>$attr) {

                $value = null;
                if (is_int($name)) $name = $attr;
                if (in_array($name,array('db_id','type','uri'))) continue;
                if (isset($record->$attr)) {
                    $value = $record->$attr;
                } else if (method_exists($record, $attr)) {
                    $value = $record->$attr();
                }
                $doc->add_attr($this->_estraierAttribute($name),$value);
            }
        }

        foreach ($this->_searchable_fields as $field) {
            if (isset($record->$field)) {
                $value = $record->$field;
            } else if (method_exists($record, $field)) {
                $value = $record->$field();
            }
            $doc->add_text($value);
        }
        return $doc;

    }

    function reindex(&$record, $options = array())
    {
        $items = &$record->find('all',$options);
        if (is_array($items))
        foreach ($items as $item) {
            $this->_updateIndex(&$item,true);
        }
    }
    function _estraierAttribute($attribute)
    {
        /**
         * From: http://hyperestraier.sourceforge.net/uguide-en.html#attributes
         *
         *  @id : the ID number determined automatically when the document is registered.
         * @uri : the location of a document which any document should have.
         * @digest : the message digest calculated automatically when the document is registered.
         * @cdate : the creation date.
         * @mdate : the last modification date.
         * @adate : the last access date.
         * @title : the title used as a headline in the search result.
         * @author : the author.
         * @type : the media type.
         * @lang : the language.
         * @genre : the genre.
         * @size : the size.
         * @weight : the scoring weight.
         * @misc : miscellaneous information
         */
        $system_attributes = array('id','uri','digest','cdate',
                                   'mdate','adate','title','author',
                                   'type','lang','genre','weigth','misc');


        $attribute = ltrim($attribute,'@');
        if (in_array($attribute,$system_attributes)) $attribute = '@'.$attribute;
        return $attribute;
    }
    function clearIndex(&$record)
    {
        $docs = &$this->estraierIndex(&$record);
        foreach ($docs as $doc) {
            $this->_estraier_connection->out_doc($doc->attr('@id'));
        }
    }

    function &estraierIndex(&$record)
    {
        $cond = &new EstraierPure_Condition;
        $cond->add_attr('type STREQ '.get_class($record));
        $res = &$this->_estraier_connection->search($cond, 0);
        $docs = array();
        if ($res) {
            // for each document in the result
            if ($res->doc_num()<1) return $docs;
            for ($i=0;$i<$res->doc_num();$i++) {
                $docs[] = $res->get_doc($i);
            }
        }
        return $docs;

    }
    function friendlySearch(&$record, $query,$options = array())
    {
        $phrase = $this->_generateSearchPhrase($query);
        return $this->fulltextSearch(&$record, $phrase, $options);
    }
    function _searchExtractTerms($search_string)
    {
        $chars = preg_split('//',$search_string);
        $currentTerm = '';
        $lastChar = '';
        $insideParenthensis = false;
        $terms = array();
        foreach ($chars as $char) {
            if (!$insideParenthensis && $char == '"') {
                
                $insideParenthensis = true;
                $currentTerm.='"';
                
            } elseif ($insideParenthensis) {
                
                if ($char == '"' && $lastChar == '\\') {
                    $currentTerm.=$char;
                } elseif ($char == '"') {
                    $insideParenthensis = false;
                    $terms[] = $currentTerm.'"';
                    $currentTerm = '';
                } else {
                    $currentTerm.=$char;
                }
                
            } else {
                if ($char == ' ' && trim($currentTerm) != '') {
                    $terms[] = trim($currentTerm);
                    $currentTerm = '';
                } else {
                    $currentTerm.=$char;
                }
            }
            $lastChar=$char;
        }
        if (trim($currentTerm) != '') {
            $terms[] = trim($currentTerm);
        }
        return $terms;
    }
    
    
    function _generateSearchPhrase($phrase)
    {
        $terms = $this->_searchExtractTerms($phrase);
        $phrase = '';
        $i=0;
        foreach ($terms as $term) {
            switch ($term{0}) {
                case '+':
                    if ($i>0) $phrase.=' AND ';
                    $term = substr($term,1);
                    break;
                case '-':
                    if ($i>0) $phrase.=' ANDNOT ';
                    $term = substr($term,1);
                    break;
                default:
                    if ($i>0)$phrase.=' OR ';
            }
            $phrase .= $term;
            $i++;
        }
        return $phrase;
    }
    
 
}
?>