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

require_once('ExportBase.php');
require_once('CFDBExport.php');

class ExportToValue extends ExportBase implements CFDBExport {

    public function export($formName, $options = null) {
        $this->setOptions($options);
        $this->setCommonOptions();

        // Security Check
        if (!$this->isAuthorized()) {
            $this->assertSecurityErrorMessage();
            return;
        }

        // See if a function is to be applied
        $funct = null;
        if ($this->options && is_array($this->options)) {
            if (isset($this->options['function'])) {
                $funct = $this->options['function'];
            }
        }

        // Headers
        $this->echoHeaders('Content-Type: text/plain; charset=UTF-8');

        // Get the data
        $this->setDataIterator($formName);

        if ($funct == 'count' &&
                count($this->showColumns) == 0 &&
                count($this->hideColumns) == 0) {
            // Just count the number of entries in the database
            return $this->getDBRowCount($formName);
        }


        if ($funct) {
            // Apply function to dataset
            switch ($funct) {
                case 'count':
                    $count = 0;
                    $colsPerRow = count($this->dataIterator->displayColumns);
                    while ($this->dataIterator->nextRow()) {
                        $count += $colsPerRow;
                    }
                    if ($this->isFromShortCode) {
                        return $count;
                    }
                    else {
                        echo $count;
                        return;
                    }

                case 'min':
                    $min = null;
                    while ($this->dataIterator->nextRow()) {
                        foreach ($this->dataIterator->displayColumns as $col) {
                            $val = $this->dataIterator->row[$col];
                            if ($min === null) {
                                $min = $val;
                            }
                            else {
                                if ($val < $min) {
                                    $min = $val;
                                }
                            }
                        }
                    }
                    if ($this->isFromShortCode) {
                        return $min;
                    }
                    else {
                        echo $min;
                        return;
                    }

                case 'max':
                    $max = null;
                    while ($this->dataIterator->nextRow()) {
                        foreach ($this->dataIterator->displayColumns as $col) {
                            $val = $this->dataIterator->row[$col];
                            if ($max === null) {
                                $max = $val;
                            }
                            else {
                                if ($val > $max) {
                                    $max = $val;
                                }
                            }
                        }
                    }
                    if ($this->isFromShortCode) {
                        return $max;
                    }
                    else {
                        echo $max;
                        return;
                    }


                case 'sum':
                    $sum = 0;
                    while ($this->dataIterator->nextRow()) {
                        foreach ($this->dataIterator->displayColumns as $col) {
                            $sum = $sum + $this->dataIterator->row[$col];
                        }
                    }
                    if ($this->isFromShortCode) {
                        return $sum;
                    }
                    else {
                        echo $sum;
                        return;
                    }

                case 'mean':
                    $sum = 0;
                    $count = 0;
                    while ($this->dataIterator->nextRow()) {
                        foreach ($this->dataIterator->displayColumns as $col) {
                            $count += 1;
                            $sum += $this->dataIterator->row[$col];
                        }
                    }
                    $mean = $sum / $count;
                    if ($this->isFromShortCode) {
                        return $mean;
                    }
                    else {
                        echo $mean;
                        return;
                    }
            }
        }

        // At this point in the code: $funct not defined or not recognized
        // output values for each row/column
        if ($this->isFromShortCode) {
            $outputData = array();
            while ($this->dataIterator->nextRow()) {
                foreach ($this->dataIterator->displayColumns as $col) {
                    $outputData[] = $this->dataIterator->row[$col];
                }
            }
            ob_start();
            switch (count($outputData)) {
                case 0:
                    echo '';
                    break;
                case 1:
                    echo $outputData[0];
                    break;
                default:
                    echo implode($outputData, ', ');
                    break;
            }
            $output = ob_get_contents();
            ob_end_clean();
            // If called from a shortcode, need to return the text,
            // otherwise it can appear out of order on the page
            return $output;
        }
        else {
            while ($this->dataIterator->nextRow()) {
                $first = true;
                foreach ($this->dataIterator->row as $val) {
                    if ($first) {
                        $first = false;
                    }
                    else {
                        echo ', ';
                    }
                    echo $val;
                }
            }
        }
    }
}
