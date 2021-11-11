<?php

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /");
    exit;
}

if (empty($_REQUEST["g-recaptcha-response"]) || empty($_REQUEST['name']) || empty($_REQUEST['email'])) {
    echo "error";
    exit;
}

require_once __DIR__ . "/libs/SMTPMail.class.php";

$name = $_REQUEST["name"];
$email = $_REQUEST["email"];

$userToken = $_REQUEST["g-recaptcha-response"];
$userIp = $_SERVER["REMOTE_ADDR"];

// Verify captcha
$url = "https://www.google.com/recaptcha/api/siteverify";
$secretKey = "";

$ch = curl_init($url);
curl_setopt_array($ch, array(
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => array(
        "secret" => $secretKey,
        "response" => $userToken,
        "remoteip" => $userIp
    ),
    CURLOPT_RETURNTRANSFER => true
));
$response = json_decode(curl_exec($ch), true);

if ($response["success"] == true) {
    if (($response["hostname"] == "www.alessandrolapiana.com" || $response["hostname"] == "alessandrolapiana.com") && !isset($response["error-codes"])) {
        $mail = new SMTPMail();

        $message = "From: $name ($email)\n\n";
        $message .= !empty($_REQUEST['message']) ? $_REQUEST['message'] : "";
        $from = "website@alessandrolapiana.com";
        $to = 'ale@alessandrolapiana.com';
        $subject = 'Contact From My Website';

        if ($mail->sendSendinblue($from, $to, $subject, $message)) {
            echo "success";
            exit;
        }
    }
}

echo "error";
