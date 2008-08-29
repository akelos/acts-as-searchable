<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

// +----------------------------------------------------------------------+
// | Akelos Framework - http://www.akelos.org                             |
// +----------------------------------------------------------------------+
// | Copyright (c) 2002-2007, Akelos Media, S.L.  & Bermi Ferrer Martinez |
// | Released under the GNU Lesser General Public License, see LICENSE.txt|
// +----------------------------------------------------------------------+

/**
* @package ActiveRecord
* @subpackage Behaviours
* @author Arno Schneider <arno a.t. bermilabs dot com>
* @copyright Copyright (c) 2002-2007, Akelos Media, S.L. http://www.akelos.org
* @license GNU Lesser General Public License <http://www.gnu.org/copyleft/lesser.html>
 * @ExtensionPoint BaseActiveRecord
 */
class ActsAsSearchableExtensions
{
    function &fulltextSearch($query, $options = array()) {
        $matches = array();
        if (isset($this->searchable) && method_exists($this->searchable,"fulltextSearch")) {
            $matches = &$this->searchable->fulltextSearch(&$this,$query, $options);
        }
        return $matches;
    }
    
    function friendlySearch($query, $options = array()) {
        $matches = array();
        if (isset($this->searchable) && method_exists($this->searchable,"friendlySearch")) {
            $matches = &$this->searchable->friendlySearch(&$this, $query, $options);
        }
        return $matches;
    }
    
    function clearIndex() {
        if (isset($this->searchable) && method_exists($this->searchable,"clearIndex")) {
            return $this->searchable->clearIndex(&$this);
        }
        return false;
    }
    
    function reindex($options = array()) {
        if (isset($this->searchable) && method_exists($this->searchable,"reindex")) {
            return $this->searchable->reindex(&$this, $options);
        }
        return false;
    }
    
    function &estraierIndex() {
        $index = array();
        if (isset($this->searchable) && method_exists($this->searchable,"estraierIndex")) {
             $index = &$this->searchable->estraierIndex(&$this);
        }
        return $index;
    }
    
    function &estraierDoc() {
        $doc = null;
        if (isset($this->searchable) && method_exists($this->searchable,"estraierDoc")) {
             $doc = &$this->searchable->estraierDoc(&$this);
        }
        return $doc;
    }
}
?>