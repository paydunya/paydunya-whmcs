<?php
/**
 * PayDunya WHMCS Callback File
 *
 * Description: Callback File for PayDunya Response
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
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch PayDunya gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$success = false;
$invoiceId = "";
$transactionId = "";
$paymentAmount = "";
$sandbox = $gatewayParams['sandBox'];
$homePage = $gatewayParams["systemurl"];
$masterKey = $gatewayParams['masterKey'];

// Detects if it's sandbox or live payment
if ($sandbox == "on") {
        
    $privateKey = $gatewayParams['testPrivateKey'];
    $toKen = $gatewayParams['testToken'];       
    $getUrl = 'https://app.paydunya.com/sandbox-api/v1/checkout-invoice/confirm/';

}else {
    
    $privateKey = $gatewayParams['livePrivateKey'];
    $toKen = $gatewayParams['liveToken'];    
    $getUrl = 'https://app.paydunya.com/api/v1/checkout-invoice/confirm/';

}

if (isset($_REQUEST["token"]) && $_REQUEST["token"] <> "") {
    
    $invoiceToken = trim($_REQUEST["token"]);
    $confirm = paydunyagatewaymodule_checkPaydunyaResponse($invoiceToken, $masterKey, $privateKey, $toKen, $getUrl, $homePage);

}else {

    $query_str = $_SERVER['QUERY_STRING'];
    $query_str_arr = explode("?", $query_str);

    foreach ($query_str_arr as $value) {

        $data = explode("=", $value);

        if (trim($data[0]) == "token") {

            $invoiceToken = isset($data[1]) ? trim($data[1]) : "";

            if ($invoiceToken <> "") {
              
                $confirm = paydunyagatewaymodule_checkPaydunyaResponse($invoiceToken, $masterKey, $privateKey, $toKen, $getUrl, $homePage);

            }

            break;
        }

    }

}

$success = $confirm['success'];
$invoiceId = $confirm['invoiceId'];
$transactionId = $confirm['transactionId'];
$paymentAmount = $confirm['paymentAmount'];

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

if ($success) {
    
    echo '
    <html>
        <head>
          <meta charset="UTF-8">
          <title>Confirmation</title>
          <!-- Latest compiled and minified CSS -->
          <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">      

          <!-- Optional theme -->
          <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css" integrity="sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r" crossorigin="anonymous">    

          <!-- Latest compiled and minified JavaScript -->
          <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous">
          </script>
          
        </head>
        <body class="container text-center" padding-top: 150px">
          ';
    echo "<br> <p class='alert alert-success' role='alert'> Votre paiement à été effectuer avec success </p>";
    echo  $confirm['message']."
        </body>
    </html>";

}

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

/**
 * Check PayDunya Response.
 *
 * @param $invoice_token Invoice Token
 * @param $master_key PayDunya Master Key
 * @param $private_key PayDunya Private Key
 * @param $token PayDunya Token
 * @param $geturl PayDunya EndPoint 
 * @param $home_page WHMCS Client Area
 */
function paydunyagatewaymodule_checkPaydunyaResponse($invoice_token, $master_key, $private_key, $token, $geturl, $home_page) {
    
    if ($invoice_token <> "") {
        
        try {

            $ch = curl_init();        
            $url = $geturl.$invoice_token;
    
            curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_NOBODY => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => array(
                    "PAYDUNYA-MASTER-KEY: $master_key",
                    "PAYDUNYA-PRIVATE-KEY: $private_key",
                    "PAYDUNYA-TOKEN: $token"
                ),
            ));

            $response = curl_exec($ch);
            $response_decoded = json_decode($response);
            $respond_code = $response_decoded->response_code;;
            
            if ($respond_code == "00") {
                //payment found
                $status = $response_decoded->status;
                $custom_data = $response_decoded->custom_data;
                
                if ($status == "completed") {
                    //payment was completely processed
                    $message = "Merci pour votre achat. La transaction a été un succès, le paiement a été reçu.<br> Votre <a href=".$response_decoded->receipt_url.">facture PAYDUNYA</a> <br><br><a href=".$home_page." class = 'btn btn-info'>Page d'accueil</a>";
                    $message_type = "success";
                    $success = true;
                    $invoiceId = $response_decoded->custom_data->invoice_id;
                    $transactionId = $invoice_token;
                    $paymentAmount = $response_decoded->invoice->total_amount;
                    
                }else {
                    //payment is still pending, or user cancelled request
                    $message = "<p class= 'alert alert-warning'> La transaction est en attente de validation.</p> <br><br><a href=".$home_page." class = 'btn btn-info'>Page d'accueil</a>";
                    $message_type = "payment is still pending";
                    $success = false;
                    $invoiceId = "";
                    $transactionId = "";
                    $paymentAmount = "";

                }

            }else {
                //payment not found
                $message = "<p class= 'alert alert-warning'> Merci de nous avoir choisi. Malheureusement, la transaction a été refusée.</p> <br><br><a href=".$home_page." class = 'btn btn-info'>Page d'accueil</a>";
                $message_type = "payment not found";
                $success = false;
                $invoiceId = "";
                $transactionId = "";
                $paymentAmount = "";
                
            }

            $notification_message = array(
                'message' => $message,
                'message_type' => $message_type,
                'success' => $success,
                'invoiceId' => $invoiceId,
                'transactionId' => $transactionId,
                'paymentAmount' => $paymentAmount,
            );

          return $notification_message;

        } catch (Exception $e) {

            echo ' is not a valid xml string';
            die();

        }

    }else{

        echo "Le token est vide";
        die();

    }

}
