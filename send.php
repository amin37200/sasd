<?php
header('Content-Type: application/json; charset=utf-8');

$token = '8126936180:AAHFhuRNyJIboIXvLkbzhaFHKiNW7XyHOyo';
$chat_id = '-1002064224708';

$session = $_POST['session'] ?? time();
$message = trim($_POST['message'] ?? '');
$file = $_FILES['file'] ?? null;

$messagesFile = "messages.json";
$messages = file_exists($messagesFile) ? json_decode(file_get_contents($messagesFile), true) : [];

if (!is_dir("uploads")) {
    mkdir("uploads", 0777, true);
}

$response = ['status' => 'ok']; // پیش‌فرض موفقیت

try {
    if ($file && $file['tmp_name']) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid("upload_") . '.' . $ext;
        $target = "uploads/$filename";

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new Exception("❌ خطا در بارگذاری فایل");
        }

        $messages[] = [
            "from" => "user",
            "type" => "file",
            "url" => $target,
            "session" => $session,
            "time" => time()
        ];

        $fileType = mime_content_type($target);
        $sendType = (strpos($fileType, 'image') !== false) ? 'sendPhoto' : 'sendDocument';
        $cFile = curl_file_create(realpath($target));

        $postFields = [
            'chat_id' => $chat_id,
            ($sendType === 'sendPhoto' ? 'photo' : 'document') => $cFile,
            'caption' => "📎 فایل جدید از کاربر #$session",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '⚙ پنل کاربر', 'callback_data' => "panel_$session"]],
                ]
            ])
        ];

        $ch = curl_init("https://api.telegram.org/bot$token/$sendType");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $postFields
        ]);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('خطا در ارسال فایل به تلگرام: ' . curl_error($ch));
        }
        curl_close($ch);
    }

    if ($message) {
        $messages[] = [
            "from" => "user",
            "type" => "text",
            "text" => $message,
            "session" => $session,
            "time" => time()
        ];

        $params = [
            'chat_id' => $chat_id,
            'text' => "📩 پیام جدید از کاربر #$session:\n\n$message",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '⚡ پاسخ سریع', 'callback_data' => "reply_$session"]],
                    [['text' => '⚙ پنل کاربر', 'callback_data' => "panel_$session"]]
                ]
            ])
        ];

        $sendMessageUrl = "https://api.telegram.org/bot$token/sendMessage?" . http_build_query($params);
        $res = file_get_contents($sendMessageUrl);

        if (!$res) {
            throw new Exception("خطا در ارسال پیام به تلگرام");
        }
    }

    // ذخیره پیام‌ها
    file_put_contents($messagesFile, json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
