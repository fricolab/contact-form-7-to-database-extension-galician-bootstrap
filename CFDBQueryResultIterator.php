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

class CFDBQueryResultIterator {

    /**
     * @var resource
     */
    var $results;

    /**
     * @var array
     */
    var $row;

    /**
     * @var string
     */
    var $submitTimeKeyName;

    /**
     * @var int
     */
    var $limitEnd;

    /**
     * @var int
     */
    var $idx;

    /**
     * @var int
     */
    var $limitStart;

    /**
     * @var array
     */
    var $columns;

    /**
     * @var array
     */
    var $displayColumns;

    /**
     * @var CF7DBPlugin
     */
    var $plugin;

    /**
     * @var CF7DBEvalutator|CF7FilterParser|CF7SearchEvaluator
     */
    var $rowFilter;

    /**
     * @var array
     */
    var $fileColumns;

    /**
     * @var bool
     */
    var $onFirstRow = false;


    public function query(&$sql, &$rowFilter, $queryOptions = array()) {
        $this->rowFilter = $rowFilter;
        $this->results = null;
        $this->row = null;
        $this->plugin = new CF7DBPlugin();
        $this->submitTimeKeyName = isset($queryOptions['submitTimeKeyName']) ? $queryOptions['submitTimeKeyName'] : null;
        if (isset($queryOptions['limit'])) {
            $limitVals = explode(',', $queryOptions['limit']);
            if (isset($limitVals[1])) {
                $this->limitStart = trim($limitVals[0]);
                $this->limitEnd = $this->limitStart + trim($limitVals[1]);
            }
            else if (isset($limitVals[0])) {
                $this->limitEnd = trim($limitVals[0]);
            }
        }
        $this->idx = -1;

        // For performance reasons, we bypass $wpdb so we can call mysql_unbuffered_query
        $con = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD, true);
        if (function_exists('mysql_set_charset')) { // PHP 5 >= 5.2.3
            mysql_set_charset('utf8', $con);
        }
        if (!$con) {
            trigger_error("MySQL Connection failed: " . mysql_error(), E_USER_NOTICE);
            return;
        }
        if (!mysql_select_db(DB_NAME, $con)) {
            trigger_error("MySQL DB Select failed: " . mysql_error(), E_USER_NOTICE);
            return;
        }
        $this->results = mysql_unbuffered_query($sql, $con);
        if (!$this->results) {
            trigger_error("MySQL unbuffered query failed: " . mysql_error(), E_USER_NOTICE);
            return;
        }

        $this->row = mysql_fetch_assoc($this->results);
        if ($this->row) {
            $this->columns = array();
            foreach (array_keys($this->row) as $aCol) {
                // hide this metadata column
                if ('fields_with_file' != $aCol) {
                    $this->columns[] = $aCol;
                }
            }
            $this->onFirstRow = true;
        }
        else {
            $this->onFirstRow = false;
        }
    }

    /**
     * Fetch next row into variable
     * @return bool if next row exists
     */
    public function nextRow() {
        if ($this->results) {
            while (true) {

                if (!$this->onFirstRow) {
                    $this->row = mysql_fetch_assoc($this->results);
                }
                $this->onFirstRow = false;

                if (!$this->row) {
                    return false;
                }

                // Format the date
                $submitTime = $this->row['Submitted'];
                $this->row['Submitted'] = $this->plugin->formatDate($submitTime);

                // Determine if row is filtered
                if ($this->rowFilter && !$this->rowFilter->evaluate($this->row)) {
                    continue;
                }

                $this->idx += 1;
                if ($this->limitStart && $this->idx < $this->limitStart) {
                    continue;
                }
                if ($this->limitEnd && $this->idx >= $this->limitEnd) {
                    while (mysql_fetch_array($this->results));
                    mysql_free_result($this->results);
                    $this->row = null;
                    return false;
                }

                // Keep the unformatted submitTime if needed
                if ($this->submitTimeKeyName) {
                    $this->row[$this->submitTimeKeyName] = $submitTime;
                }
                break;
            }
        }
        if (!$this->row) {
            mysql_free_result($this->results);
        }
        return $this->row ? true : false;
    }


}
