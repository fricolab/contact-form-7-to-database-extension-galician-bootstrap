<?php
/*
    "Contact Form to Database" Copyright (C) 2011-2013 Michael Simpson  (email : michael.d.simpson@gmail.com)

    This file is part of Contact Form to Database.

    Contact Form to Database is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Contact Form to Database is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Contact Form to Database.
    If not, see <http://www.gnu.org/licenses/>.
*/

require_once('CFDBAbstractQueryResultsIterator.php');

class CFDBQueryResultIterator extends CFDBAbstractQueryResultsIterator {

    /**
     * @var resource
     */
    var $results;


    /**
     * If you do not iterate over all the rows returned, be sure to call this function
     * on all remaining rows to free resources.
     * @return void
     */
    protected function freeResult() {
        if ($this->results) {
            mysql_free_result($this->results);
            $this->results = null;
        }
    }
    /**
     * @return array associative
     */
    protected function fetchRow() {
        return mysql_fetch_assoc($this->results);
    }

    protected function hasResults() {
        return !empty($this->results);
    }

    /**
     * @param $sql
     * @param $queryOptions
     * @return void
     */
    protected function queryDataSource(&$sql, $queryOptions) {
        // For performance reasons, we bypass $wpdb so we can call mysql_unbuffered_query
        $con = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD, true);
        if (!$con) {
            trigger_error("MySQL Connection failed: " . mysql_error(), E_USER_NOTICE);
            return;
        }

        // Target charset is in wp-config.php DB_CHARSET
        if (defined('DB_CHARSET')) {
            if (DB_CHARSET != '') {
                global $wpdb;
                if (method_exists($wpdb, 'set_charset')) {
                    $collate = null;
                    if (defined('DB_COLLATE')) {
                        if (DB_COLLATE != '') {
                            $collate = DB_COLLATE;
                        }
                    }
                    $wpdb->set_charset($con, DB_CHARSET, $collate);
                } else {
                    $setCharset = 'SET NAMES \'' . DB_CHARSET . '\'';
                    if (defined('DB_COLLATE')) {
                        if (DB_COLLATE != '') {
                            $setCharset = $setCharset . ' COLLATE \'' . DB_COLLATE . '\'';
                        }
                    }
                    mysql_query($setCharset, $con);
                }
            }
        }

        if (!mysql_select_db(DB_NAME, $con)) {
            trigger_error('MySQL DB Select failed: ' . mysql_error(), E_USER_NOTICE);
            return;
        }

        if (isset($queryOptions['unbuffered']) && $queryOptions['unbuffered'] === 'true') {
            // FYI: using mysql_unbuffered_query disrupted nested shortcodes if the nested one does a query also
            $this->results = mysql_unbuffered_query($sql, $con);
            if (!$this->results) {
                trigger_error('mysql_unbuffered_query failed: ' . mysql_error(), E_USER_NOTICE);
                return;
            }
        } else {
            $this->results = @mysql_query($sql, $con);
            if (!$this->results) {
                trigger_error('mysql_query failed. Try adding <code>unbuffered="true"</code> to your short code. <br/>' . mysql_error(), E_USER_WARNING);
                return;
            }
        }
    }


}
