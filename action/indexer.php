<?php
/**
 * SubjectIndex plugin indexer
 *
 * @author  Symon Bent
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_subjectindex_indexer extends DokuWiki_Action_Plugin {

    /**
     * Register its handlers with the DokuWiki's event controller
     */
    function register(&$controller) {
        $controller->register_hook('INDEXER_PAGE_ADD', 'AFTER', $this, '_subject_index');
    }

    function _subject_index(&$event, $param) {
        if (isset($event->data['page'])) {
            $page = $event->data['page']; // param names changed in Rincewind release
        } else {
            $page = $event->data[0];    // pre-Rincewind params
        }
        if (empty($page)) return;   // get out if no such wikipage

        require_once (DOKU_INC . 'inc/indexer.php');
        $page_idx = idx_getIndex('page', '');

        require_once(DOKU_PLUGIN . 'subjectindex/inc/common.php');
        $index_file = get_subj_index($this->getConf('subjectindex_data_dir'));
        $entry_idx = file($index_file);

        // first remove any entries that reference non-existant files (currently once a day!)
        if ($this->_cleanup_time()) {
            $this->_remove_invalid_entries($entry_idx, $page_idx);
        }

        // get page id--this corresponds to line number in page.idx file
        $pid = array_search("$page\n", $page_idx);
        // grab all current subject index entries that match this page: the "delete list"
        $page_entry_idx = preg_grep('`.*\|' . $pid . '$`', $entry_idx);
        unset($page_idx);

        // now get all marked up entries for this wiki page
        $wiki = rawWiki($page);
        $match_cnt = preg_match_all('`' . SUBJ_IDX_INDEXER_RGX. '`', $wiki, $entry_matches);
        if ($match_cnt > 0) {
            $matches = $entry_matches[1];
            $matched = true;
        }
        // then deal with special tag matches
        $tag_section = $this->getConf('subjectindex_tag_section');
        if ( ! empty($tag_section)) {
            $match_cnt = preg_match_all('/(' . SUBJ_IDX_TAG_RGX . ')/', $wiki, $tag_matches);
            if ($match_cnt > 0) {
                foreach ($tag_matches[0] as $tag) {
                    $matches[] = $tag_section . "/" . utf8_trim($tag, '#');
                }
                $matched = true;
            }
        }
        $updated = false;
        if ($matched) {
            foreach ($matches as $match) {
                // remove any display syntax
                $match = strtok($match, '|');
                // add section number if missing (a section no. should match: <digit>/<text>...
                if (preg_match('`^\d+\/.+`', $match) == 0) $match = "0/" . $match;
                // compare the current page's entries with the delete list
                $exists = preg_grep('`^' . $match . '`', $page_entry_idx);
                if ( ! empty($exists)) {
                    // IGNORE: exists in current and original: remove from "delete list"
                    $key = $this->_key($exists, 0);
                    unset($page_entry_idx[$key]);
                } else {
                    // CREATE: must be a completely new entry then...
                    $entry_idx[] = $match . '|' . $pid . "\n";
                    $updated = true;
                }
            }
        }
        // DELETE: these index entries no longer exist on current wiki page
        foreach (array_keys($page_entry_idx) as $key) {
            unset($entry_idx[$key]);
            $updated = true;
        }
        if ($updated) {
            // sort then commit all updates
            usort($entry_idx, array($this, '_pathcmp'));
            file_put_contents($index_file, $entry_idx);
        }
    }
    /**
     * String compare function: sorts index "paths" correctly
     * i.e. root paths come before leaves
     */
    private function _pathcmp($a, $b) {
        $a_txt = strtok($a, '|');
        $b_txt = strtok($b, '|');
        if (strnatcasecmp($a_txt,$b_txt) != 0) {
            $a_sub = strpos($b_txt, $a_txt);
            $b_sub = strpos($a_txt, $b_txt);
            if ($a_sub !== false && $a_sub == 0) {
                return -1;
            } elseif ($b_sub !== false && $b_sub == 0) {
                return 1;
            }
        }
        return strnatcasecmp($a, $b);
    }
    /**
     * Returns position key instead of string key
     *
     * @param array $a
     * @param <type> $pos
     * @return integer
     */
    private function _key(array $a, $pos) {
        $temp = array_slice($a, $pos, 1, true);
        return key($temp);
    }

    private function _remove_invalid_entries(&$entry_idx, $page_idx) {
        $missing_idx = array();
        foreach ($entry_idx as $key => $value) {
            $entry = explode('|', $value);
            $idx = intval($entry[1]);
            $page = $page_idx[$idx];
            if ( ! isset($missing_idx[$idx]) && ! valid_page($page)) {
                $missing_idx[$idx][] = $key;    // add to index of missing pages
            }
        }
        foreach ($missing_idx as $missing) {
            foreach ($missing as $idx) {
                unset($entry_idx[$idx]);
            }
        }
    }
    /**
     * Returns true if a day has passed since last cleanup
     * @return bool true => time to do clean up
     */
    private function _cleanup_time() {
        $last_cleanup = file_get_contents(SUBJ_IDX_CHECK_TIME);
        if ($last_cleanup === false) $last_cleanup = 0;
        if ($last_cleanup == 0 || time() > $last_cleanup + 60 * 60 * 24) {
            file_put_contents(SUBJ_IDX_CHECK_TIME, time());
            return true;
        } else {
            return false;
        }
    }
}
