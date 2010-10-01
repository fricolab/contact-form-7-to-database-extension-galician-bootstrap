<?php
include_once('../../../wp-config.php');
include_once('../../../wp-load.php');
include_once('../../../wp-includes/wp-db.php');
require_once('CF7DBTableData.php');
require_once('CF7DBPlugin.php');

function CF7DBPlugin_exportToCSV($formName) {
    $plugin = new CF7DBPlugin();

    $roleAllowed = $plugin->getRoleOption('CanSeeSubmitData');
    $canSeeData = $plugin->isRoleOrBetter($roleAllowed);
    if (!$canSeeData) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $tableData = $plugin->getRowsPivot($formName);

    header("Content-Type: text/csv; charset=UTF-8");
//    header("Content-Type: application/vnd.ms-excel");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
    header("Content-Disposition: attachment; filename=\"$formName.csv\"");

    echo chr(239) . chr(187) . chr(191); // File encoding UTF-8 Byte Order Mark (BOM) http://wiki.sdn.sap.com/wiki/display/ABAP/Excel+files+-+CSV+format

    $eol = "\n";
    $comma = ",";

    // Column Headers
    echo CF7DBPlugin_PrepareCsvValue(__("Submitted"));
    echo $comma;
    foreach ($tableData->columns as $aCol) {
        echo CF7DBPlugin_PrepareCsvValue($aCol);
        echo $comma;
    }
    echo $eol;


    // Rows
    foreach ($tableData->pivot as $submitTime => $data) {
        echo date('Y-m-d', $submitTime);
        echo $comma;
        foreach ($tableData->columns as $aCol) {
            $cell = isset($data[$aCol]) ? $data[$aCol] : "";
            echo CF7DBPlugin_PrepareCsvValue($cell);
            echo $comma;
        }
        echo $eol;
    }
}

function CF7DBPlugin_PrepareCsvValue($text) {
    // In CSV, escape double-quotes but putting two double quotes together
    $quote = '"';
    $text = str_replace($quote, $quote.$quote, $text);

    // Quote it to escape line breaks
    $text = $quote . $text . $quote;

    return $text;
}


if (isset($_GET['form_name'])) {
    CF7DBPlugin_exportToCSV($_GET['form_name']);
}
else if (isset($_POST['form_name'])) {
    CF7DBPlugin_exportToCSV($_POST['form_name']);
}
else {
    ?>
<html><body>Error: No "form_name" parameter submitted</body></html>
    <?php
}
 
