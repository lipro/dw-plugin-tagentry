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


class action_plugin_tagentry extends DokuWiki_Action_Plugin {

    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Robin Gareus',
            'email'  => 'robin@gareus.org',
            'date'   => '2008-12-21',
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
     * Create the additional fields for the edit form
     */
    function handle_editform_output(&$event, $param){
        // check if source view -> no captcha needed
        if(!$param['oldhook']){
            // get position of submit button
            $pos = $event->data->findElementByAttribute('type','submit');
            if(!$pos) return; // no button -> source view mode
        }elseif($param['editform'] && !$event->data['writable']){
            if($param['editform'] && !$event->data['writable']) return;
        }

        $alltags=$this->_gettags();

        global $ID;

        $out  = '';
        $out .= '<div id="plugin__tagentry_wrapper">';
        $out .= $this->_format_tags($alltags,array('tagboxtable' => 1));
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
   * 
   */
  private function _gettags() {
    require_once(DOKU_INC.'inc/search.php');
    global $conf;
    $data = array();
    $tagns = $conf['plugin']['tag']['namespace']; # XXX wrong rg
    if (empty($tagns)) {
      #$tagns = 'wiki:tags'; 
      $tagns = 'tag'; 
    }
    search($data,$conf['datadir'],'_tagentry_search_tagpages',array('ns' => $tagns));
    return($data); 
  }
  private function clipstring($s, $len=22) {
    return substr($s,0,$len).((strlen($s)>$len)?'..':'');
  }

  private function escapeJSstring ($o) {
    return ( # TODO: use JSON ?!
      str_replace("\n", '\\n', 
      str_replace("\r", '', 
      str_replace('\'', '\\\'', 
      str_replace('\\', '\\\\', 
        $o)))));
  }


  private function _format_tags($alltags, $options) {
    $rv='';
    if (!is_array($alltags)) return $rv;

    $rv.='<div><label>Tags:</label><br/>';
    $rv.='<div style="overflow:auto; max-height:4em; margin-bottom:.5em;">';
    if ($options['tagboxtable']) $rv.='<table><tr>';
    $i=0;
    sort($alltags);
    foreach ($alltags as $t)  {
      if ($t=="bookmark") continue;
      if ($i%5==0 && $i!=0) { 
	if ($options['tagboxtable']) $rv.="</tr>\n<tr>";
	else $rv.="<br/>\n";
      }
      $i++;
      if ($options['tagboxtable']) $rv.='<td>';
      $rv.='<input type="checkbox" id="plugin__tagentry_cb'.$t.'" value="1" name="'.$t.'" onclick="tagentry_clicktag(\''.$this->escapeJSstring($t).'\', this);" /> '.$this->clipstring($t).'&nbsp;';
      $rv.="\n";
      if ($options['tagboxtable']) $rv.='</td>';
      # TODO: allow to limit number of tags ?
      #if ($i>100) { $rv.="&nbsp;..."; break; }
    }
    if ($options['tagboxtable']) $rv.='</tr></table>';
    $rv.='</div>';
    #$rv.='<p></p>';
    $rv.='</div>';
    return ($rv);
  }

}

//Setup VIM: ex: et sw=2 ts=2 enc=utf-8 :
