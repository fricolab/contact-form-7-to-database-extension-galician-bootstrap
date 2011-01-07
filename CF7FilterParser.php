<?php
/*
    Contact Form 7 to Database Extension
    Copyright 2011 Michael Simpson  (email : michael.d.simpson@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

include_once('CF7DBValueConverter.php');

/**
 * Used to parse boolean expression strings like 'field1=value1&&field2=value2||field3=value3&&field4=value4'
 * Where logical AND and OR are represented by && and || respectively.
 * Individual expressions (like 'field1=value1') are of the form $name . $operator . $value where
 * $operator is any PHP comparison operator or '=' which is interpreted as '=='.
 * $value has a special case where if it is 'null' it is interpreted as the value null
 */
class CF7FilterParser {

    /**
     * @var array of arrays of string where the top level array is broken down on the || delimiters
     */
    var $tree;

    /**
     * @var CF7DBValueConverter callback that can be used to pre-process values in the filter string
     * passed into parseFilterString($filterString).
     * For example, a function might take the value '$user_email' and replace it with an actual email address
     * just prior to checking it against input data in call evaluate($data)
     */
    var $compValuePreprocessor;

    public function hasFilters() {
        return count($this->tree) > 0; // count is null-safe
    }

    public function getFilterTree() {
        return $this->tree;
    }

    /**
     * Parse a string with delimiters || and/or && into a Boolean evaluation tree.
     * For example: aaa&&bbb||ccc&&ddd would be parsed into the following tree,
     * where level 1 represents items ORed, level 2 represents items ANDed, and
     * level 3 represent individual expressions. 
     * Array
     * (
     *     [0] => Array
     *         (
     *             [0] => Array
     *                 (
     *                     [0] => aaa
     *                     [1] => =
     *                     [2] => bbb
     *                 )
     *
     *         )
     *
     *     [1] => Array
     *         (
     *             [0] => Array
     *                 (
     *                     [0] => ccc
     *                     [1] => =
     *                     [2] => ddd
     *                 )
     *
     *             [1] => Array
     *                 (
     *                     [0] => eee
     *                     [1] => =
     *                     [2] => fff
     *                 )
     *
     *         )
     *
     * )
     * @param  $filterString string with delimiters && and/or ||
     * which each element being an array of strings broken on the && delimiter
     */
    public function parseFilterString($filterString) {
        $this->tree = array();
        $arrayOfORedStrings = $this->parseORs($filterString);
        foreach ($arrayOfORedStrings as $anANDString) {
            $arrayOfANDedStrings = $this->parseANDs($anANDString);
            $andSubTree = array();
            foreach ($arrayOfANDedStrings as $anExpressionString) {
                $exprArray = $this->parseExpression($anExpressionString);
                $andSubTree[] = $exprArray;
            }
            $this->tree[] = $andSubTree;
        }
    }

    /**
     * @param  $filterString
     * @return array
     */
    public function parseORs($filterString) {
        return preg_split('/\|\|/', $filterString, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param  $filterString
     * @return array
     */
    public function parseANDs($filterString) {
        $retVal = preg_split('/&&/', $filterString, -1, PREG_SPLIT_NO_EMPTY);
        if (count($retVal) == 1) {
            // This took me a long time chase down. Looks like in some cases when using this in a
            // WordPress web page, the text that gets here is '&#038;&#038;' rather than '&&'
            // (But oddly, this is not always the case). So check for this case explicitly.
            $retVal = preg_split('/&#038;&#038;/', $filterString, -1, PREG_SPLIT_NO_EMPTY);
        }

        //echo "<pre>Parsed '$filterString' into " . print_r($retVal, true) . '</pre>';
        return $retVal;
    }

    /**
     * Parse a comparison expression into its three components
     * @param  $comparisonExpression string in the form 'value1' . 'operator' . 'value2' where
     * operator is a php comparison operator or '='
     * @return array of string [ value1, operator, value2 ]
     */
    public function parseExpression($comparisonExpression) {
        return preg_split('/(===)|(==)|(=)|(!==)|(!=)|(<>)|(<=)|(<)|(>=)|(>)|(~~)/',
                          $comparisonExpression, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    }


    /**
     * Evaluate expression against input data. Assumes parseFilterString was called to set up the expression to
     * evaluate. Expression should have key . operator . value tuples and input $data should have the same keys
     * with values to check against them.
     * For example, an expression in this object is 'name=john' and the input data has [ 'name' => 'john' ]. In
     * this case true is returned. if $data has [ 'name' => 'fred' ] then false is returned.
     * @param  $data array [ key => value]
     * @return boolean result of evaluating $data against expression tree
     */
    public function evaluate($data) {
        $retVal = true;
        if ($this->tree) {
            $retVal = false;
            foreach ($this->tree as $andArray) { // loop each OR'ed $andArray
                $andBoolean = true;
                // evaluation the list of AND'ed comparison expressions
                foreach ($andArray as $comparison) {
                    $andBoolean = $this->evaluateComparison($comparison, $data); //&& $andBoolean
                    if (!$andBoolean) {
                        break; // short-circuit AND expression evaluation
                    }
                }
                $retVal = $retVal || $andBoolean;
                if ($retVal) {
                    break; // short-circuit OR expression evaluation
                }
            }
        }
        return $retVal;
    }

    public function evaluateComparison($andExpr, &$data) {
        if (is_array($andExpr) && count($andExpr) == 3) {
            $left = $data[$andExpr[0]];
            $op = $andExpr[1];
            $right = $andExpr[2];
            if ($this->compValuePreprocessor) {
                try {
                    $right = $this->compValuePreprocessor->convert($right);
                }
                catch (Exception $ex) {
                    trigger_error($ex, E_USER_NOTICE);
                }
            }
            return $this->evaluateLeftOpRightComparison($left, $op, $right);
        }
        return false;
    }


    /**
     * @param  $left mixed
     * @param  $operator string representing any PHP comparison operator or '=' which is taken to mean '=='
     * @param  $right $mixed. SPECIAL CASE: if it is the string 'null' it is taken to be the value null
     * @return bool evaluation of comparison $left $operator $right
     */
    public function evaluateLeftOpRightComparison($left, $operator, $right) {
        // Could do this easier with eval() but I want since this text ultimately
        // comes form a shortcode's user-entered attributes, I want to avoid a security hole
        if ($right == 'null') {
            // special case
            $right = null;
        }
        switch ($operator) {
            case '=' :
            case '==':
                return $left == $right;

            case '===':
                return $left === $right;

            case '!=':
                return $left != $right;

            case '!==':
                return $left !== $right;

            case '<>':
                return $left <> $right;

            case '>':
                return $left > $right;

            case '>=':
                return $left >= $right;

            case '<':
                return $left < $right;

            case '<=':
                return $left <= $right;

            case '~~':
                return preg_match($right, $left) > 0;

            default:
                trigger_error("Invalid operator: '$operator'", E_USER_NOTICE);
                return false;
        }
    }

    /**
     * @param  $converter CF7DBValueConverter
     * @return void
     */
    public function setComparisonValuePreprocessor($converter) {
        $this->compValuePreprocessor = $converter;
    }

}
