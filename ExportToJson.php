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

require_once('CF7DBPlugin.php');
require_once('CF7FilterParser.php');
require_once('DereferenceShortcodeVars.php');
require_once('ExportBase.php');
require_once('CFDBExport.php');

class ExportToJson extends ExportBase implements CFDBExport {

    public function export($formName, $options = null) {
        $this->setOptions($options);
        $this->setCommonOptions($options);

        $varName = 'cf7db';
        $html = false; // i.e. create an HTML script tag and Javascript variable

        if ($options && is_array($options)) {

            if (isset($options['html'])) {
                $html = $options['html'];
            }

            if (isset($options['var'])) {
                $varName = $options['var'];
            }
        }

        // Security Check
        if (!$this->isAuthorized()) {
            $this->assertSecurityErrorMessage();
            return;
        }

        // Headers
        $contentType = $html ? 'Content-Type: text/html; charset=UTF-8' : 'Content-Type: application/json';
        $this->echoHeaders($contentType);

        // Query DB for the data for that form
        $plugin = new CF7DBPlugin();
        $tableData = $plugin->getRowsPivot($formName);

        // Get the columns to display
        $columns = $this->getColumnsToDisplay($tableData->columns);
        $showSubmitField = $this->getShowSubmitField();

        $jsonData = array();
        foreach ($tableData->pivot as $submitTime => $data) {
            // Determine if row is filtered
            if (!$this->filterParser->evaluate($data)) {
                continue;
            }
            $row = array();

            if ($showSubmitField) {
                $row['Submitted'] = $plugin->formatDate($submitTime);
            }

            foreach ($columns as $aCol) {
                $cell = isset($data[$aCol]) ? $data[$aCol] : "";
                $row[$aCol] = $cell;
            }
            $jsonData[] = $row;
        }

        if (isset($options['fromshortcode'])) {
            ob_start();
        }

        if ($html) {
            ?>
            <script type="text/javascript" language="JavaScript">
                <!--
                var <?php echo $varName; ?> = <?php echo json_encode($jsonData); ?>;
                //-->
            </script>
            <?php

        }
        else {
            echo json_encode($jsonData);
        }

        if (isset($options['fromshortcode'])) {
            // If called from a shortcode, need to return the text,
            // otherwise it can appear out of order on the page
            $output = ob_get_contents();
            ob_end_clean();
            return $output;
        }
    }
}
