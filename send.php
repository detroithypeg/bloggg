<?php
// send.php — отправка заявки на ящик SpaceWeb

// Только POST-запросы
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: contacts.html");
    exit;
}

// ── Получаем данные ──
$name    = isset($_POST['name'])    ? strip_tags(trim($_POST['name']))    : '';
$contact = isset($_POST['contact']) ? strip_tags(trim($_POST['contact'])) : '';
$message = isset($_POST['message']) ? strip_tags(trim($_POST['message'])) : '';

// ── Проверка ──
if (empty($name) || empty($contact)) {
    header("Location: contacts.html?error=1#quoteForm");
    exit;
}

// ============================================================
//  НАСТРОЙКИ — ВАШ ЯЩИК SPACEWEB
// ============================================================
$to      = 'kmvprestige@kmvprestige.ru';   // получатель — ваш ящик
$from    = 'kmvprestige@kmvprestige.ru';   // отправитель — тот же ящик
$subject = 'Новая заявка — Престиж Мебель';

// ── Текст письма ──
$body  = "Имя: $name\n";
$body .= "Контакт: $contact\n";
$body .= "Комментарий:\n$message\n";

// ── Обработка вложения ──
$hasFile = isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK;

if ($hasFile) {
    if ($_FILES['attachment']['size'] > 10 * 1024 * 1024) {
        header("Location: contacts.html?error=1#quoteForm");
        exit;
    }

    $fileName = basename($_FILES['attachment']['name']);
    $fileData = chunk_split(base64_encode(file_get_contents($_FILES['attachment']['tmp_name'])));
    $boundary = md5(uniqid(time()));

    $headers  = "From: $from\r\n";
    $headers .= "Reply-To: $contact\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    $bodyFull  = "--$boundary\r\n";
    $bodyFull .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $bodyFull .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $bodyFull .= $body . "\r\n";

    $bodyFull .= "--$boundary\r\n";
    $bodyFull .= "Content-Type: application/octet-stream; name=\"$fileName\"\r\n";
    $bodyFull .= "Content-Transfer-Encoding: base64\r\n";
    $bodyFull .= "Content-Disposition: attachment; filename=\"$fileName\"\r\n\r\n";
    $bodyFull .= $fileData . "\r\n";
    $bodyFull .= "--$boundary--";

    $body = $bodyFull;
} else {
    $headers  = "From: $from\r\n";
    $headers .= "Reply-To: $contact\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
}

// UTF-8 тема
$subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

// ── Дополнительный параметр -f (конверт отправителя) ──
$additionalParams = '-f' . $from;

// ── Отправка ──
if (mail($to, $subject, $body, $headers, $additionalParams)) {
    header("Location: contacts.html?sent=1#quoteForm");
    exit;
} else {
    header("Location: contacts.html?error=1#quoteForm");
    exit;
}