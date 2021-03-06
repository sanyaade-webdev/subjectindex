<?php
/**
 * Subject Index plugin : entry syntax
 * indexes any subject index entries on the page (to data/index/subject.idx by default)
 *
 * Using the {{entry>[heading/sub-heading/]entry[|display text]}} syntax
 * a new subject index entry can be added
 *
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Symon Bent <hendrybadao@gmail.com>
 *
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_PLUGIN . 'subjectindex/inc/common.php');

class syntax_plugin_subjectindex_entry extends DokuWiki_Syntax_Plugin {

	function __construct() {
    }

    function getType() {
		return 'substition';
	}

	function getSort() {
		return 305;
	}

	function getPType(){
		return 'normal';
	}

	function connectTo($mode) {
        // Syntax: {{entry>[idx no./heading/]entry text[|display name]}}    [..] optional
		$pattern = SUBJ_IDX_ENTRY_RGX;
		$this->Lexer->addSpecialPattern($pattern, $mode, 'plugin_subjectindex_entry');
        // Syntax: #tag, or #multi_word_tag (any chars accepted after the # symbols **except space**, first char cannot be 0-9!)
        $pattern = SUBJ_IDX_TAG_RGX;
        $this->Lexer->addSpecialPattern($pattern, $mode, 'plugin_subjectindex_entry');
	}

	function handle($match, $state, $pos, &$handler) {
        // first look for 'special' tag patterns: #tag#
        if($match[0] != '{') {
            $entry = utf8_trim($match, '#');    // remove the '#''s (old syntax also had at end...)
            $display = str_replace('_', ' ', $entry);  // swap '_' for spaces for display
            $section = $this->getConf('subjectindex_tag_section'); //index section used for tags!
            $is_tag = true;
        } else {
            $end = strpos($match, '>');
            $data = substr($match, $end + 1, -2); // remove {{entry>...}} markup
            list($entry, $display) = explode('|', $data);
            if (preg_match('`^\d+\/.+`', $entry) > 0) {
                // first digit refers to the index section to be used
                list($section, $entry) = explode('/', $entry, 2);
            } else {
                $section = 0;
            }
            $is_tag = false;
        }

        require_once(DOKU_PLUGIN . 'subjectindex/inc/common.php');
        $link_id = clean_id($entry);
        $entry = $this->remove_ord($entry); // remove any ordered list numbers (used for manual sorting)
        $sep = $this->getConf('subjectindex_display_sep');

        $hide = false;
        if ( ! isset($display)) {
            $display = '';
        // invisible entry, do not display!
        } elseif ($display == '-') {
            $display = '';
            $hide = true;
        // no display so show star by default
        } elseif ((isset($display) && empty($display)) || $display == '*') {
            $display = str_replace('/', $sep, $entry);
        }

        $entry = str_replace('/', $sep, $entry);
        $target_page = get_target_page($section);
		return array($entry, $display, $link_id, $target_page, $hide, $is_tag);
	}

	function render($mode, &$renderer, $data) {
        list($entry, $display, $link_id, $target_page, $hide, $is_tag) = $data;

        if ($mode == 'xhtml') {
            $hidden = ($hide) ? ' hidden' : '';
            $entry = ($is_tag) ? $this->getLang('subjectindex_tag') . $entry : $this->getLang('subjectindex_prefix') . $entry;
            if (empty($target_page)) {
                $title = $this->getLang('no_default_target');
                $target_page = '';
                $class = 'bad-entry';
            } else {
                $target_page = wl($target_page) . '#' . $link_id;
                $title = $this->html_encode($entry);
                $class = 'entry';
            }
			$renderer->doc .= '<a id="' . $link_id . '" class="' . $class . $hidden .
                              '" title="' . $title .
                              '" href="' . $target_page . '">' .
                              $this->html_encode($display) . '</a>' . DOKU_LF;
			return true;
		}
		return false;
	}

    private function html_encode($text) {
        $text = htmlentities($text, ENT_QUOTES, 'UTF-8');
        return $text;
    }

    private function remove_ord($text) {
        $text = preg_replace('`^\d+\.`', '', $text);
        $text = preg_replace('`\/\d+\.`', '/', $text);
        return $text;
    }
}
