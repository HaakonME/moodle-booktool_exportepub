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
 * Export EPUB library.
 *
 * @package    booktool
 * @subpackage exportepub
 * @copyright  2012-2014 Mikael Ylikoski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* This file contains code based on mod/book/tool/print/lib.php
 * (copyright 2004-2011 Petr Skoda) from Moodle 2.4. */

defined('MOODLE_INTERNAL') || die();

function booktool_exportepub_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $booknode) {
    global $PAGE;

    if ($PAGE->cm->modname !== 'book') {
        return;
    }

    if (empty($PAGE->cm->context)) {
        $PAGE->cm->context = context_module::instance($PAGE->cm->instance);
    }

    $params = $PAGE->url->params();

    if (empty($params['id'])) {
        return;
    }

    if (has_capability('booktool/exportepub:export', $PAGE->cm->context) and
        has_capability('mod/book:read', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/book/tool/exportepub/index.php',
                              array('id' => $params['id']));
        $booknode->add(get_string('downloadepub', 'booktool_exportepub'),
                       $url, navigation_node::TYPE_SETTING, null, null, null);
    }
}
