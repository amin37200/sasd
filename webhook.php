<?php
$token = '8126936180:AAHFhuRNyJIboIXvLkbzhaFHKiNW7XyHOyo'; // توکن ربات

$update = json_decode(file_get_contents('php://input'), true);

function apiRequest($method, $params = []) {
    global $token;
    $url = "https://api.telegram.org/bot$token/$method";
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode($params),
        ],
    ];
    return file_get_contents($url, false, stream_context_create($options));
}

// دریافت callback_query (برای دکمه‌ها)
if (isset($update['callback_query'])) {
    $data = $update['callback_query']['data'];
    $chat_id = $update['callback_query']['message']['chat']['id'];
    $message_id = $update['callback_query']['message']['message_id'];
    $callback_id = $update['callback_query']['id'];

    if (strpos($data, "panel_") === 0) {
        $session = substr($data, 6);

        $text = "📊 پنل تیکت کاربر #$session:\n\n";
        $text .= "میتونی به کاربر پیام بدی یا چت رو حذف کنی.";

        $keyboard = [
            "inline_keyboard" => [
                [
                    ["text" => "✏️ پاسخ", "callback_data" => "reply_$session"],
                    ["text" => "🗑 حذف چت", "callback_data" => "delete_$session"]
                ]
            ]
        ];

        apiRequest("answerCallbackQuery", ["callback_query_id" => $callback_id]);

        apiRequest("editMessageText", [
            "chat_id" => $chat_id,
            "message_id" => $message_id,
            "text" => $text,
            "reply_markup" => $keyboard
        ]);
    }

    // پاسخ‌دهی به تیکت
    if (strpos($data, "reply_") === 0) {
        $session = substr($data, 6);

        file_put_contents("reply_session.json", json_encode([
            "admin_id" => $chat_id,
            "session" => $session
        ]));

        apiRequest("answerCallbackQuery", ["callback_query_id" => $callback_id]);
        apiRequest("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "✏️ پاسخ خود را برای تیکت #$session ارسال کنید:",
            "reply_markup" => [
                "force_reply" => true
            ]
        ]);
    }

    // حذف چت کاربر
    if (strpos($data, "delete_") === 0) {
        $session = substr($data, 7);
        $messages = file_exists("messages.json") ? json_decode(file_get_contents("messages.json"), true) : [];
        $messages = array_filter($messages, fn($m) => $m['session'] !== $session);
        file_put_contents("messages.json", json_encode(array_values($messages), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        apiRequest("answerCallbackQuery", ["callback_query_id" => $callback_id]);
        apiRequest("sendMessage", [
            "chat_id" => $chat_id,
            "text" => "✅ چت تیکت #$session حذف شد."
        ]);
    }

    exit;
}

// اگر پاسخ به یک تیکت ارسال شد
if (isset($update['message']['reply_to_message'])) {
    $text = $update['message']['text'] ?? '';
    $reply = json_decode(file_get_contents("reply_session.json"), true);
    $session = $reply['session'] ?? null;

    if ($session) {
        $messages = file_exists("messages.json") ? json_decode(file_get_contents("messages.json"), true) : [];
        $messages[] = [
            "from" => "support",
            "text" => $text,
            "session" => $session,
            "time" => time()
        ];
        file_put_contents("messages.json", json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        apiRequest("sendMessage", [
            "chat_id" => $update['message']['chat']['id'],
            "text" => "✅ پاسخ شما برای تیکت #$session ذخیره شد."
        ]);
    }
}
