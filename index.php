<?php

declare(strict_types=1);

require 'Currency.php';

$host = 'localhost';
$db = 'currency_bot';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

$bot_token = '7257193222:AAGFSmGTvRlkTWN909AGNwpKiFZO9m0GK38';

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'];

    $parts = explode(' ', $text);

    if (count($parts) === 3) {
        $amount = (float) $parts[0];
        $from_currency = strtoupper($parts[1]);
        $to_currency = strtoupper($parts[2]);

        $currency = new Currency();
        $converted_amount = $currency->exchange($amount, $from_currency, $to_currency);

        if ($converted_amount !== null) {
            $stmt = $pdo->prepare("INSERT INTO conversions (from_currency, to_currency, amount, converted_amount) VALUES (:from_currency, :to_currency, :amount, :converted_amount)");
            $stmt->execute([
                ':from_currency' => $from_currency,
                ':to_currency' => $to_currency,
                ':amount' => $amount,
                ':converted_amount' => $converted_amount
            ]);

            $response = "Converted $amount $from_currency to $converted_amount $to_currency.";
        } else {
            $response = "Failed to convert currency.";
        }
    } else {
        $response = "Invalid format. Use: amount from_currency to_currency";
    }

    file_get_contents("https://api.telegram.org/bot$bot_token/sendMessage?chat_id=$chat_id&text=" . urlencode($response));
}
