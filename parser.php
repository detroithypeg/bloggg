<?php
// ============================================================
//  УНИВЕРСАЛЬНЫЙ АВТОПАРСЕР НОВОСТЕЙ (DESIGNNEWS, D4U, TOPDESIGNMAG)
// ============================================================

$sources = [
    [
        'name' => 'DesignNews',
        'url' => 'https://designnews.ru/',
        'domain' => 'https://designnews.ru',
        'category' => 'Новости дизайна',
        'filter' => '/news/' 
    ],
    [
        'name' => 'D4U',
        'url' => 'https://d4u.ru/m',
        'domain' => 'https://d4u.ru',
        'category' => 'Тренды дизайна',
        'filter' => '/m/' 
    ],
    [
        'name' => 'TopDesignMag',
        'url' => 'https://topdesignmag.ru/',
        'domain' => 'https://topdesignmag.ru',
        'category' => 'Дизайн интерьера',
        'filter' => '/' // У этого сайта статьи лежат в корне, ссылки без спец. путей
    ]
];

$jsonPath = __DIR__ . '/news.json';
$imgDir = __DIR__ . '/images/blog/';

date_default_timezone_set('Europe/Moscow');

function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
    ]);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

if (!is_dir($imgDir)) {
    mkdir($imgDir, 0755, true);
}

$existingNews = [];
if (file_exists($jsonPath)) {
    $existingNews = json_decode(file_get_contents($jsonPath), true) ?: [];
}

$addedCount = 0;
$log = [];

foreach ($sources as $source) {
    echo "Проверяем: {$source['name']}...\n";
    
    $html = fetchUrl($source['url']);
    if (!$html) {
        $log[] = "❌ Ошибка: не удалось загрузить {$source['url']}";
        continue;
    }

    $doc = new DOMDocument();
    @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($doc);

    $articles = [];
    
    foreach ($xpath->query("//a[.//img]") as $linkNode) {
        $href = $linkNode->getAttribute('href');
        
        // Логика фильтрации для каждого сайта
        $isValidLink = false;
        if ($source['name'] === 'TopDesignMag') {
            // Для TopDesignMag берем ссылки, которые не ведут на категории и не являются пустыми
            if (strlen($href) > 10 && strpos($href, 'category') === false && strpos($href, '#') === false) {
                $isValidLink = true;
            }
        } else {
            // Для остальных (DesignNews, D4U) проверяем по фильтру
            if (strpos($href, $source['filter']) !== false && trim($href) !== rtrim($source['filter'], '/') && strpos($href, 'page') === false) {
                $isValidLink = true;
            }
        }

        if ($isValidLink) {
            $imgNode = $xpath->query(".//img", $linkNode)->item(0);
            $imgSrc = $imgNode->getAttribute('src');
            
            // Обработка ленивой загрузки (lazyload)
            if (!$imgSrc || strpos($imgSrc, 'data:image') !== false) {
                $imgSrc = $imgNode->getAttribute('data-src');
            }
            if (!$imgSrc) {
                $srcset = $imgNode->getAttribute('srcset');
                if ($srcset) {
                    $parts = explode(' ', trim($srcset));
                    $imgSrc = $parts[0];
                }
            }
            
            if ($imgSrc && !isset($articles[$href])) {
                $title = $imgNode->getAttribute('alt');
                if (!$title) {
                    $title = trim(preg_replace('/\s+/', ' ', $linkNode->textContent));
                }
                
                if ($title) {
                    $articles[$href] = [
                        'href' => $href,
                        'img' => $imgSrc,
                        'title' => $title
                    ];
                }
            }
        }
    }

    if (empty($articles)) {
        $log[] = "⚠️ Не найдено статей на {$source['name']}";
        continue;
    }

    // Берем первую статью
    $firstItem = reset($articles);
    $fullLink = (strpos($firstItem['href'], 'http') === 0) ? $firstItem['href'] : rtrim($source['domain'], '/') . '/' . ltrim($firstItem['href'], '/');
    $imageUrl = (strpos($firstItem['img'], 'http') === 0) ? $firstItem['img'] : rtrim($source['domain'], '/') . '/' . ltrim($firstItem['img'], '/');
    $title = html_entity_decode($firstItem['title'], ENT_QUOTES, 'UTF-8');

    // Проверяем дубликаты
    $isDuplicate = false;
    foreach ($existingNews as $news) {
        if (isset($news['link']) && $news['link'] === $fullLink) {
            $isDuplicate = true;
            break;
        }
    }

    if ($isDuplicate) {
        $log[] = "⏩ Пропущено (уже есть): $title";
        continue;
    }

    // Скачиваем картинку
    $imagePath = '';
    if ($imageUrl) {
        $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$ext) $ext = 'jpg';
        $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
        
        $imageName = 'auto-' . md5($fullLink) . '.' . $ext;
        $imagePath = 'images/blog/' . $imageName;
        
        if (!file_exists(__DIR__ . '/' . $imagePath)) {
            $imgData = fetchUrl($imageUrl);
            if ($imgData) {
                file_put_contents(__DIR__ . '/' . $imagePath, $imgData);
            }
        }
    }

    // Формируем новую запись
    $newItem = [
        "date" => date('Y-m-d'),
        "category" => $source['category'],
        "title" => $title,
        "excerpt" => "Свежая статья с сайта {$source['name']}. Перейдите по ссылке, чтобы прочитать полный текст.",
        "image" => $imagePath,
        "link" => $fullLink
    ];

    array_unshift($existingNews, $newItem);
    $addedCount++;
    $log[] = "✅ Добавлено ({$source['name']}): $title";
}

// Сохраняем обновленный JSON
$existingNews = array_slice($existingNews, 0, 50);
file_put_contents($jsonPath, json_encode($existingNews, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "--- РЕЗУЛЬТАТ ---\n";
echo implode("\n", $log) . "\n";
echo "Всего добавлено новых статей: $addedCount\n";
?>