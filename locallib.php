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
 * @copyright  2014 Mikael Ylikoski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function toolbook_exportepub_http_get($url) {
    if (!preg_match('~^[a-z]+://~', $url)) {
        return array(null, null, null);
    }

    $uobj = parse_url($url);
    $host = $uobj['scheme'] . '://' . $uobj['host'];
    $path = $uobj['path'];
    if (isset($uobj['query'])) {
        $path .= '?' . $uobj['query'];
    }
    if (isset($uobj['fragment'])) {
        $path .= '#' . $uobj['fragment'];
    }
    $name = basename($uobj['path']);
    if (!$name) {
        $name = 'moo';
    }
    $name = rawurldecode($name);

    ini_set('default_socket_timeout', 5);

    // Method 1, handles http and https, not always enabled
    if (intval(ini_get('allow_url_fopen'))) {
        $data = @file_get_contents($url);
        if ($data) {
            return array($data, $name, null);
        }
    }

    // Method 2, HTTP_Request, handles http, not always enabled

    /*
    if (class_exists('HTTP_Request') and substr($host, 0, 5) == 'http:') {
        $req = new HTTP_Request($url,
                                array('timeout' => 5));
        if ($req->sendRequest() === true) {
            return array($req->getResponseBody(), $name, null);
        }
    }
    */

    // Method 3, http_get, handles http, not always enabled
    /*
    if (function_exists('http_get') and substr($host, 0, 5) == 'http:') {
        $data = http_get($url, array('timeout' => 5), $info);
        if ($data) {
            //if (array_key_exists('content_type', $info)) {
            //    $ct = $info['content_type'];
            //}
            return array($data, $name, null);
        }
    }
    */

    // Method 4, curl, handles http and https, not always enabled
    /*
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data) {
            return array($data, $name, null);
        }
    }
    */

    // Method 5, handles http, should always work
    if (!preg_match('~^http://([^:/]+)(:([0-9]+))?$~', $host, $matches)) {
        return array(null, null, null);
    }
    $hostname = $matches[1];
    $port = 80;
    if (array_key_exists(3, $matches)) {
        $port = intval($matches[3]);
    }
    $fp = @fsockopen($hostname, $port);
    if (!$fp) {
        return array(null, null, null);
    } else {
        $out = "GET " . $path . " HTTP/1.0\r\n";
        $out .= "Host: " . $hostname . "\r\n";
        $out .= "Connection: Close\r\n\r\n";
        fwrite($fp, $out);
        $data = '';
        while (!feof($fp)) {
            $data .= fread($fp, 8192);
        }
        fclose($fp);
        $pos = strpos(substr($data, 0, 500), "\r\n\r\n");
        if ($pos !== false) {
            // $headers = substr($data, 0, $pos);
            // Content-Type: image/jpeg
            $data = substr($data, $pos + 4);
            return array($data, $name, null);
        }
    }

    return array(null, null, null);
}

/**
 * Embed external files in EPUB
 *
 * @param object $doc
 * @param object $epub
 */
function toolbook_exportepub_embed_external_files($doc, $epub) {
    foreach ($doc->getElementsByTagName('img') as $element) {
        if (!$element->hasAttribute('src')) {
            continue;
        }
        $url = $element->getAttribute('src');
        list($data, $name, $type) = toolbook_exportepub_http_get($url);
        if ($data) {
            $item = $epub->add_item($data, $type, 'images/' . $name);
            $element->setAttribute('src', $item['href']);
        }
    }

    foreach ($doc->getElementsByTagName('video') as $element) {
        if (!$element->hasAttribute('src')) {
            continue;
        }
        $url = $element->getAttribute('src');
        list($data, $name, $type) = toolbook_exportepub_http_get($url);
        if ($data) {
            $item = $epub->add_item($data, $type, 'images/' . $name);
            $element->setAttribute('src', $item['href']);
        }
    }

    foreach ($doc->getElementsByTagName('audio') as $element) {
        if (!$element->hasAttribute('src')) {
            continue;
        }
        $url = $element->getAttribute('src');
        list($data, $name, $type) = toolbook_exportepub_http_get($url);
        if ($data) {
            $item = $epub->add_item($data, $type, 'images/' . $name);
            $element->setAttribute('src', $item['href']);
        }
    }

    foreach ($doc->getElementsByTagName('source') as $element) {
        if (!$element->hasAttribute('src')) {
            continue;
        }
        $url = $element->getAttribute('src');
        list($data, $name, $type) = toolbook_exportepub_http_get($url);
        if (!$type and $element->hasAttribute('type')) {
            $type = $element->getAttribute('type');
        }
        if ($data) {
            $item = $epub->add_item($data, $type, 'images/' . $name);
            $element->setAttribute('src', $item['href']);
        }
    }

    foreach ($doc->getElementsByTagName('object') as $element) {
        if (!$element->hasAttribute('data')) {
            continue;
        }
        $url = $element->getAttribute('data');
        list($data, $name, $type) = toolbook_exportepub_http_get($url);
        if (!$type and $element->hasAttribute('type')) {
            $type = $element->getAttribute('type');
        }
        if ($data) {
            $item = $epub->add_item($data, $type, 'images/' . $name);
            $element->setAttribute('data', $item['href']);
        }
    }
}
