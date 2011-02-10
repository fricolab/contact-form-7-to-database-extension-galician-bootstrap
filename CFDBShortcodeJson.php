<?php
/*
    "Contact Form to Database Extension" Copyright (C) 2011 Michael Simpson  (email : michael.d.simpson@gmail.com)

    This file is part of Contact Form to Database Extension.

    Contact Form to Database Extension is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Contact Form to Database Extension is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Contact Form to Database Extension.
    If not, see <http://www.gnu.org/licenses/>.
*/

require_once('CFDBShortCodeLoaderSecurityCheck.php');
require_once('ExportToJson.php');

class CFDBShortcodeJson extends CFDBShortCodeLoaderSecurityCheck {

    /**
     * @param  $atts array of short code attributes
     * @return string JSON. See ExportToJson.php
     */
    public function handleShortcodePostSecurityCheck($atts) {
        if ($atts['show']) {
            $showColumns = preg_split('/,/', $atts['show'], -1, PREG_SPLIT_NO_EMPTY);
            $atts['showColumns'] = $showColumns;
        }
        if ($atts['hide']) {
            $hideColumns = preg_split('/,/', $atts['hide'], -1, PREG_SPLIT_NO_EMPTY);
            $atts['hideColumns'] = $hideColumns;
        }
        $atts['html'] = true;
        $atts['fromshortcode'] = true;
        $export = new ExportToJson();
        $html = $export->export($atts['form'], $atts);
        return $html;
    }
}
