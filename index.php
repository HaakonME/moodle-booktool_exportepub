<?php
// This file is part of Lucimoo
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Export EPUB export function.
 *
 * @package    booktool
 * @subpackage exportepub
 * @copyright  2012-2014 Mikael Ylikoski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* This file contains code based on mod/book/tool/print/index.php
 * (copyright 2004-2011 Petr Skoda) from Moodle 2.4. */

require(dirname(__FILE__).'/../../../../config.php');

$id        = required_param('id', PARAM_INT);           // Course Module ID
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID

// =========================================================================
// security checks

$cm = get_coursemodule_from_id('book', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$book = $DB->get_record('book', array('id' => $cm->instance), '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/book:read', $context);
require_capability('booktool/exportepub:export', $context);

// =========================================================================

if ($chapterid) {
    $chapter = $DB->get_record('book_chapters', array('id' => $chapterid, 'bookid' => $book->id), '*', MUST_EXIST);
} else {
    $chapter = false;
}

$PAGE->set_url('/mod/book/tool/exportepub/add.php',
               array('id' => $id, 'chapterid' => $chapterid));

unset($id);
unset($chapterid);

require_once($CFG->dirroot . '/mod/book/locallib.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/LuciEPUB.php');

$ebooksettings = array();
// $ebooksettings['embedLocalFiles'] = false;
$ebooksettings['embedNonlocalFiles'] = false;
$ebooksettings['includeDescription'] = false;

try {
    require(dirname(__FILE__) . '/config.php');
} catch (Exception $e) {
    // Ignore configuration if none exists
}

/**
 * Helper class to store regular expression matches.
 */
class match_saver {
    public $matches = array();

    public function callback($matches) {
        $this->matches[] = $matches[1];
        return 'images/' . $matches[1] . $matches[2];
    }
}

// Create EPUB
$modified = $book->timemodified;
$booktitle = format_string($book->name, true, array('context' => $context));
$epub = new LuciEPUB();
$epub->set_title($booktitle);
$epub->set_uid();
$epub->set_date();
if ($CFG->lang) {
    $epub->add_language($CFG->lang);
}

// Set default metadata
if (isset($ebooksettings['publisher'])) {
    $epub->set_publisher($ebooksettings['publisher']);
}
if (isset($ebooksettings['rights'])) {
    $epub->set_rights($ebooksettings['rights']);
}

// Add stylesheet and cover
$epub->add_item_filepath(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'luci.css',
                         'text/css', 'luci.css');
$epub->add_spine_item($epub->get_html_cover($booktitle, null, null, null,
                                            'luci.css'), 'cover.html');

// Add description text
if ($ebooksettings['includeDescription']) {
    $text = format_text($book->intro, $book->introformat,
                        array('noclean' => true, 'context' => $context));
    $epub->add_spine_item($epub->get_html_wrap($book->intro,
                                               get_string('summary'),
                                               'luci.css'), 'intro.html');
    $epub->set_item_toc(get_string('summary'));
}

// Add chapters
$chapters = book_preload_chapters($book);
$allchapters = $DB->get_records('book_chapters', array('bookid' => $book->id),
                                'pagenum');
$fs = get_file_storage();
$first = true;
foreach ($chapters as $cid => $ch) {
    $chapter = $allchapters[$ch->id];
    if ($chapter->hidden) {
        continue;
    }
    $title = book_get_chapter_title($ch->id, $chapters, $book, $context);

    $text = '';
    $text .= "<div id='ch" . $ch->id . "'>\n";
    if (!$book->customtitles) {
        if ($chapter->subchapter) {
            $text .= '<h3 class="book_chapter_title">' . $title . "</h3>\n";
        } else {
            $text .= '<h2 class="book_chapter_title">' . $title . "</h2>\n";
        }
    }

    // Add images
    $mat = new match_saver();
    $content = preg_replace_callback('~@@PLUGINFILE@@/(.+?)([\'"])~',
                                     array($mat, 'callback'),
                                     $chapter->content);
    foreach ($mat->matches as $match) {
        $fn = rawurldecode($match);
        $fullpath = '/' . $context->id . '/mod_book/chapter/' . $ch->id .
            '/' . $fn;
        $file = $fs->get_file_by_hash(sha1($fullpath));
        if ($file) {
            // $filecontent = $file->get_content();
            $epub->add_item_file($file->get_content_file_handle(),
                                 $file->get_mimetype(),
                                 'images/' . $fn);
        }
    }

    $text .= format_text($content, $chapter->contentformat,
                         array('noclean' => true, 'context' => $context));
    $text .= '</div>';

    if ($ebooksettings['embedNonlocalFiles']) {
        try {
            $doc = new DOMDocument();
            @$doc->loadXML($text, LIBXML_NONET);
            toolbook_exportepub_embed_external_files($doc, $epub);
            $text = $doc->saveXML();
            $text = substr($text, strpos($text, '?>') + 2);
        } catch (Exception $e) {
            // Ignore files that cannot be loaded
        }
    }

    $epub->add_spine_item($epub->get_html_wrap($text, $title, 'luci.css'),
                          'chap' . $ch->id . '.html');
    $epub->set_item_toc(null, true, !$first);
    $first = false;

    if ($modified < $chapter->timemodified) {
        $modified = $chapter->timemodified;
    }
}

// Note: $chapter->timemodified is not updated when a chapter is edited
// $epub->set_modified(@gmdate('Y-m-d\TH:i:s', $modified) . 'Z');
$epub->generate_nav('luci.css', true);

// Send EPUB
$outname = preg_replace('~[/\\\'":?*& ]~', '_', $booktitle);
$out = $epub->generate();
$out->sendZip(@utf8_decode($outname) . '.epub', 'application/epub+zip',
              $outname . '.epub', true);
