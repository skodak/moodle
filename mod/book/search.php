<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Book search AJAX handler
 *
 * @package    mod_book
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$query = required_param('query', PARAM_TEXT);

$cm = get_coursemodule_from_id('book', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$book = $DB->get_record('book', array('id'=>$cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/book:read', $context);

$viewhidden = has_capability('mod/book:viewhiddenchapters', $context);

// Search chapters
$results = array();
$chapters = $DB->get_records('book_chapters', array('bookid' => $book->id), 'pagenum');

foreach ($chapters as $chapter) {
    // Skip hidden chapters if user can't view them
    if ($chapter->hidden && !$viewhidden) {
        continue;
    }
    
    // Search in title and content
    $titleMatch = stripos($chapter->title, $query) !== false;
    $contentText = strip_tags($chapter->content);
    $contentMatch = stripos($contentText, $query) !== false;
    
    if ($titleMatch || $contentMatch) {
        // Get excerpt around the match
        $excerpt = '';
        if ($contentMatch) {
            $pos = stripos($contentText, $query);
            $start = max(0, $pos - 50);
            $length = 150;
            $excerpt = substr($contentText, $start, $length);
            if ($start > 0) {
                $excerpt = '...' . $excerpt;
            }
            if ($start + $length < strlen($contentText)) {
                $excerpt = $excerpt . '...';
            }
        } else {
            $excerpt = substr($contentText, 0, 150) . '...';
        }
        
        $results[] = array(
            'id' => $chapter->id,
            'title' => format_string($chapter->title),
            'excerpt' => $excerpt
        );
    }
}

echo json_encode($results);
