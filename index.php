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

session_start();

$bot_token = '7257193222:AAGFSmGTvRlkTWN909AGNwpKiFZO9m0GK38';

function sendMessage($chat_id, $text, $keyboard = null)
{
    global $bot_token;

    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'reply_markup' => $keyboard ? json_encode($keyboard) : null
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type:application/json\r\n",
            'content' => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = trim($message['text']);

    if ($text === '/start') {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'USD', 'callback_data' => 'from_USD'],
                    ['text' => 'EUR', 'callback_data' => 'from_EUR'],
                    ['text' => 'UZS', 'callback_data' => 'from_UZS']
                ]
            ]
        ];
        sendMessage($chat_id, "Choose the currency you want to convert from:", $keyboard);
    } elseif (isset($_SESSION['from_currency']) && isset($_SESSION['to_currency']) && is_numeric($text)) {
        $amount = (float) $text;

        $currency = new Currency();
        $converted_amount = $currency->exchange($amount, $_SESSION['from_currency'], $_SESSION['to_currency']);

        if ($converted_amount !== null) {
            $stmt = $pdo->prepare("INSERT INTO conversions (from_currency, to_currency, amount, converted_amount) VALUES (:from_currency, :to_currency, :amount, :converted_amount)");
            $stmt->execute([
                ':from_currency' => $_SESSION['from_currency'],
                ':to_currency' => $_SESSION['to_currency'],
                ':amount' => $amount,
                ':converted_amount' => $converted_amount
            ]);
            $response = "Converted $amount {$_SESSION['from_currency']} to $converted_amount {$_SESSION['to_currency']}.";
            unset($_SESSION['from_currency']);
            unset($_SESSION['to_currency']);
        } else {
            $response = "Failed to convert currency.";
        }
        sendMessage($chat_id, $response);
    } else {
        sendMessage($chat_id, "Invalid format. Please use the buttons to select currencies and then enter the amount.");
    }
} elseif (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $data = $callback_query['data'];

    if (strpos($data, 'from_') === 0) {
        $from_currency = substr($data, 5);
        $_SESSION['from_currency'] = $from_currency;
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'USD', 'callback_data' => "to_USD"],
                    ['text' => 'EUR', 'callback_data' => "to_EUR"],
                    ['text' => 'UZS', 'callback_data' => "to_UZS"]
                ]
            ]
        ];
        sendMessage($chat_id, "You chose $from_currency as the currency to convert from. Now choose the currency to convert to:", $keyboard);
    } elseif (strpos($data, 'to_') === 0) {
        $to_currency = substr($data, 3);
        $_SESSION['to_currency'] = $to_currency;
        sendMessage($chat_id, "You chose to convert from {$_SESSION['from_currency']} to $to_currency. Now send the amount to convert:");
    }
}
