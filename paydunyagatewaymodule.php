<?php
/**
 * Plugin Name: PayDunya Payment gateway for WHMCS
 *
 * Description: Easily integrate payment via Orange Money, Joni Joni, VITFE and VISA/MASTERCARD/GIM-UEMOE in your WHMCS website and start accepting payments from Senegal .
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

if (!defined("WHMCS")) {
    die("Sorry my dear... -Vous ne pouvez pas accéder directement à ce fichier-");
}

/**
 * Define PayDunya gateway module related meta data.
 *
 * @return array
 */
function paydunyagatewaymodule_MetaData()
{
    return array(
        'DisplayName' => 'PayDunya',
        'APIVersion' => '1.0',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define PayDunya gateway configuration options.
 *
 * @return array
 */
function paydunyagatewaymodule_config()
{
    return array(
        // the friendly display name for PayDunya gateway
        // for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'PayDunya',
        ),

        // Master Key text field
        'masterKey' => array(
            'FriendlyName' => 'Master Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter Your Master Key',
        ),
        
        // Live Private Key text field
        'livePrivateKey' => array(
            'FriendlyName' => 'Live Private Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter Your Live Private Key',
        ),
        // Live Token text field
        'liveToken' => array(
            'FriendlyName' => 'Live Token',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter Your Live Token',
        ),

        // Test Private Key text field
        'testPrivateKey' => array(
            'FriendlyName' => 'Test Private Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter Your Test Private Key',
        ),
        // Test Token text
        'testToken' => array(
            'FriendlyName' => 'Test Token',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter Your Test Token',
        ),
        
        // the yesno checkbox option for Live or Sandbox Test
        'sandBox' => array(
            'FriendlyName' => 'Sandbox Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to use PayDunya\'s Virtual Sandbox Test Environment',
        ),
    );

}

/**
 * Payment link.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to PayDunya gateway endpoint.
 *
 * @param array $params PayDunya Gateway Module Parameters
 *
 * @return string
 */
function paydunyagatewaymodule_link($params)
{
    // Gateway Configuration Parameters
    $masterkey = $params['masterKey'];
    $sandbox = $params['sandBox'];
    
    // Define if sandbox or live payment
    if ($sandbox == "on") {

        $privatekey= $params['testPrivateKey'];
        $token = $params['testToken'];
        $posturl = 'https://app.paydunya.com/sandbox-api/v1/checkout-invoice/create';
           
    } else {

        $privatekey= $params['livePrivateKey'];
        $token = $params['liveToken'];
        $posturl = 'https://app.paydunya.com/api/v1/checkout-invoice/create';
            
    }

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $description = "Paiement de " . $amount . " FCFA pour article(s) achetés sur " . $params["description"]; 
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $cancelUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];
    $callbackUrl = $systemUrl . '/modules/gateways/callback/' . $moduleName . '_ipn.php';
    $returnUrl = $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php';

    // Payment intializer
    $paydunya_args = paydunyagatewaymodule_getPaydunyaArgs($invoiceId, $description, $companyName, $systemUrl, $cancelUrl, $returnUrl, $callbackUrl, $amount, $currencyCode);
    $url = paydunyagatewaymodule_postToUrl($posturl, $paydunya_args, $masterkey, $privatekey , $token);

    // Payment button
    $htmlOutput = '<form method="get"  action="' . $url . '">';
    $htmlOutput .= '<input class ="bt btn-info" type="submit" value="' . $langPayNow . '" />';
    $htmlOutput .= '</form>';

    return $htmlOutput;

}

/**
 * Invoice Request.
 *
 * Required by PayDunya gateway modules only.
 *
 * Request that will be send to PayDunya gateway
 *
 * @param $url PayDunya EndPoint 
 * @param $data Data that will be send to PayDunya EndPoint
 * @param $masterKey PayDunya Master Key
 * @param $privateKey PayDunya Private Key
 * @param $toKen PayDunya Token
 *
 * @return string
 */
function paydunyagatewaymodule_postToUrl($url, $data, $masterKey, $privateKey , $toKen) {            

    $json = json_encode($data);
    $ch = curl_init();
    $master_key = trim($masterKey);
    $private_key = trim($privateKey);
    $token = trim($toKen);
    
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_NOBODY => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array(
            "PAYDUNYA-MASTER-KEY: $master_key",
            "PAYDUNYA-PRIVATE-KEY: $private_key",
            "PAYDUNYA-TOKEN: $token"
            ),
        )
    );

    $response = curl_exec($ch);
    $response_decoded = json_decode($response);

    if ($response_decoded->response_code && $response_decoded->response_code == "00") {
              
        return $response_decoded->response_text;            

    } else {
                
        echo "PAYDUNYA Response : " . $response_decoded->response_text."<br>";
        die();  
                                
    }

}

/**
 * Data for PayDunya gateway
 *
 * Required by PayDunya gateway modules only.
 *
 * Defines Data that will be send to PayDunya gateway endpoint.
 *
 * @param $idinvoice Invoice Id
 * @param $invoice_description Invoice Description
 * @param $store_name Store Name
 * @param $website_url Website URL
 * @param $cancel_url Cancel URL
 * @param $return_url Return URL
 * @param $total_amount Total Amount
 * @param $currency Currency
 *
 * @return array string
 */
function paydunyagatewaymodule_getPaydunyaArgs($idinvoice, $invoice_description, $store_name, $website_url, $cancel_url, $return_url, $callback_url, $total_amount, $currency) {

    $command = "getinvoice";
    $values["invoiceid"] = $idinvoice;
    $results = localAPI($command,$values);
                    
    try {

        $paydunya_items = array();

        foreach ($results["items"]['item'] as $key => $value) {
        
            $paydunya_items[] = array(
                "name" => trim($value['type']),
                "quantity" => 1,
                "unit_price" => trim($value["amount"]),
                "total_price" => trim($value["amount"]),
                "description" => trim($value["description"])
            );
        
        }

        $paydunya_args = array(

            "invoice" => array(
                "items" => $paydunya_items,
                "total_amount" => $total_amount,
                "description" => $invoice_description
            ), 
            "store" => array(
                "name" => $store_name,
                "website_url" => $website_url
            ),
            "actions" => array(
                "cancel_url" => $cancel_url,
                "callback_url" => $callback_url,
                "return_url" => $return_url
            ), 
            "custom_data" => array(
                "invoice_id" => $idinvoice,
                "user_id" => $results["userid"],
                "currency" => $currency,
                "due_date" => $results["duedate"]
            )

        );

        return $paydunya_args;

    } catch (Exception $e) {
        // handle the error
        echo 'PAYDUNYA Response : $results is not a valid xml string';
        die();
 
    }
                    
}
