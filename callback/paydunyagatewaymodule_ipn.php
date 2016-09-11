<?php
/**
 * PayDunya WHMCS IPN Callback File
 *
 * Description: Callback File for PayDunya IPN Response
 *
 * @version 1.0
 *
 * @author PayDunya
 *
 * Author URI: https://paydunya.com
 * 
 * See Online Doc.
 *
 * @see https://paydunya.com/developers/whmcs
 *
 * @copyright Copyright (c) PAYDUNYA SINCE 2015
 *
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '_ipn.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$success = false;
$invoiceId = "";
$transactionId = "";
$paymentAmount = "";
$masterKey = $gatewayParams['masterKey'];

try {

    if($_POST['data']['hash'] === hash('sha512', trim($masterKey))) {
        // GREAT ON PEUT CONTINUER

            if ($_POST['data']['status'] == "completed") {
                        
                $success = true;
                $invoiceId = trim($_POST['data']['custom_data']['invoice_id']);
                $paydunyatoken = trim($_POST['data']['invoice']['token']);
                $transactionId = $paydunyatoken;
                $paymentAmount = trim($_POST['data']['invoice']['total_amount']);

            }
    
    }else {
        
        die();
    
    }
} catch(Exception $e) {
    die();
}

$transactionStatus = $success ? 'Success' : 'Failure';

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 */

$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 */

checkCbTransID($transactionId);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
logTransaction($gatewayParams['name'], $_REQUEST, $transactionStatus);

if ($success) {
    
    /**
     * Add Invoice Payment.
     *
     * Applies a payment transaction entry to the given invoice ID.
     *
     * @param int $invoiceId         Invoice ID
     * @param string $transactionId  Transaction ID
     * @param float $paymentAmount   Amount paid (defaults to full balance)
     * @param float $paymentFee      Payment fee (optional)
     * @param string $gatewayModule  Gateway module name
     */
   
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $gatewayModuleName
    );

}


