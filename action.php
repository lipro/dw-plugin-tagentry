<?php
/**
 * Tagentry plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Robin Gareus <robin@gareus.org>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_tagentry extends DokuWiki_Action_Plugin {
  /**
   * return some info
   */
  function getInfo(){
    return array(
      'author' => 'Robin Gareus',
      'email'  => 'robin@gareus.org',
      'date'   => '2008-12-31',
      'name'   => 'tagentry Plugin',
      'desc'   => 'adds a tag-selection table below the entry form.',
      'url'    => 'http://mir.dnsalias.com/wiki/tagentry',
    );
  }

  /**
   * register the eventhandlers
   */
  function register(&$controller){
      // old hook
      $controller->register_hook('HTML_EDITFORM_INJECTION',
                                 'BEFORE',
                                 $this,
                                 'handle_editform_output',
                                 array('editform' => true, 'oldhook' => true));

      // new hook
      $controller->register_hook('HTML_EDITFORM_OUTPUT',
                                 'BEFORE',
                                 $this,
                                 'handle_editform_output',
                                 array('editform' => true, 'oldhook' => false));
  }

  /**
   * Create the additional fields for the edit form.
   */
  function handle_editform_output(&$event, $param){
    if(!$param['oldhook']){
      $pos = $event->data->findElementByAttribute('type','submit');
      if(!$pos) return; // no button -> source view mode
    }elseif($param['editform'] && !$event->data['writable']){
      if($param['editform'] && !$event->data['writable']) return;
    }

    $tagns=$this->getConf('namespace');
    if ($thlp=&plugin_load('helper', 'tag')) {
      if ($this->getConf('tagsrc') == 'Pagenames in tag NS') {
        $tagnst=$thlp->getConf('namespace');
        if(!empty($tagnst)) $tagns=$tagnst;
      }
    }

    if ($this->getConf('tagsrc') == 'All tags' && $thlp) {
      $alltags=$this->_gettags(&$thlp);
    } else {
      $alltags=$this->_getpages($tagns);
    }

    $out  = '';
    $out .= '<div id="plugin__tagentry_wrapper">';
    $options=array(
      'tagboxtable' => $this->getConf('table'),
    # 'limit' => intval($this->getConf('limit')),
      'blacklist' => split(' ',$this->getConf('blacklist')),
      'class' => '',
    );
    $out .= $this->_format_tags($alltags,$options);
    $out .= '</div>';

    if($param['oldhook']){
      // old wiki - just print
      echo $out;
    }else{
      // new wiki - insert at correct position
      $event->data->insertElement($pos++,$out);
    }
  }

  /**
   * callback function for dokuwiki search()
   *
   * Build a list of tags from the tag namespace
   * $opts['ns'] is the namespace to browse
   */
  function _tagentry_search_tagpages(&$data,$base,$file,$type,$lvl,$opts){
    $return = true;
    $item = array();
    if($type == 'd') {
      // TODO: check if namespace mismatch -> break recursion early.
      return true;
    }elseif($type == 'f' && !preg_match('#\.txt$#',$file)){
      return false;
    }

    $id = pathID($file);
    // TODO: use a regexp ?!
    if (getNS($id) != $opts['ns']) return false;

    //check hidden
    if(isHiddenPage($id)){
      return false;
    }

    //check ACL
    if($type=='f' && auth_quickaclcheck($id) < AUTH_READ){
      return false;
    }

    $data[]=noNS($id);
    return $return;
  }

  function _gettags(&$thlp) {
    $data = array();
    foreach ($thlp->topic_idx as $k => $v) {
        if (!is_array($v) || empty($v) || (!trim($v[0]))) continue;
        $data[$k] = count($v);
    }
    arsort($data);
    return(array_keys($data)); 
  }

  /**
   * list all pages in the namespace.
   *
   * @param $tagns namespace to search.
   * @return array list of tag names.
   */
  function _getpages($tagns='wiki:tags') {
    global $conf;
    require_once(DOKU_INC.'inc/search.php');
    $data = array();
    search($data, $conf['datadir'], array($this, '_tagentry_search_tagpages'),
           array('ns' => $tagns));
    return($data); 
  }

  function clipstring($s, $len=22) {
    return substr($s,0,$len).((strlen($s)>$len)?'..':'');
  }

  function escapeJSstring ($o) {
    return ( # TODO: use JSON ?!
      str_replace("\n", '\\n', 
        str_replace("\r", '', 
          str_replace('\'', '\\\'', 
            str_replace('\\', '\\\\', 
        $o)))));
  }

  /** 
   * render and return the tag-select box.
   *
   * @param $alltags array of tags to display.
   * @param $options array 
   * @return string XHTML form.
   */
  function _format_tags($alltags, $options) {
    $rv='';
    if (!is_array($alltags)) return $rv;

    $rv.='<div class="'.$options['class'].'">';
    $rv.=' <div><label>Tags:</label></div>';
    # TODO style here -> style.css ?!
    $rv.=' <div style="overflow:auto; max-height:5em; margin-bottom:.25em;">';
    if ($options['tagboxtable']) $rv.='<table><tr>';
    $i=0;
    natcasesort($alltags);
    foreach ($alltags as $t)  {
      if (is_array($options['blacklist']) 
          && in_array($t, $options['blacklist'])) continue;
      if ($i%5==0 && $i!=0) { 
        if ($options['tagboxtable']) $rv.="</tr>\n<tr>";
        else $rv.="<br/>\n";
      }
      $i++;
      if ($options['tagboxtable']) $rv.='<td>';
      $rv.='<input type="checkbox" id="plugin__tagentry_cb'.$t.'" value="1" name="'.$t.'" onclick="tagentry_clicktag(\''.$this->escapeJSstring($t).'\', this);" /> '.$this->clipstring($t).'&nbsp;';
      $rv.="\n";
      if ($options['tagboxtable']) $rv.='</td>';
      if ($options['limit']>0 && $i>$options['limit']) { 
        $rv.="&nbsp;...";
         break;
       }
    }
    if ($options['tagboxtable']) $rv.='</tr></table>';
    $rv.=' </div>';
    $rv.='</div>';
    return ($rv);
  }
}

//Setup VIM: ex: et sw=2 ts=2 enc=utf-8 :
