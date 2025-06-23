<?php

// // /home/bitrix/www/local/events/on_product_update.php

// // Файл лога
// define('LOG_FILE', __DIR__ . '/on_product_update.log');

// /**
//  * Пишем строку в лог с текущим временем
//  */
// function addLog(string $msg): void
// {
//     file_put_contents(
//         LOG_FILE,
//         date('Y-m-d H:i:s') . " — " . $msg . PHP_EOL,
//         FILE_APPEND
//     );
// }

// // 1) Подключаем CREST
// require_once(__DIR__ . '/crest/crest.php');

// // 2) Читаем входные параметры из $_REQUEST
// addLog('2) Получение данных из $_REQUEST');
// addLog('   $_REQUEST dump: ' . print_r($_REQUEST, true));

// $data      = $_REQUEST['data']   ?? [];
// $fields    = $data['FIELDS']     ?? [];
// $productId = (int)($fields['ID'] ?? 0);
// addLog("   Из запроса получили ID товара: {$productId}");

// // 3) Получаем данные товара из CRM
// addLog("3) Запрос crm.product.get для товара {$productId}");
// $response = Crest::call('crm.product.get', ['id' => $productId]);

// // 4) Логируем ответ API
// if (isset($response['result'])) {
//     addLog('   Успешно получили данные товара:');
//     addLog(print_r($response['result'], true));
// } else {
//     addLog('   Ошибка при получении товара:');
//     addLog(print_r($response, true));
// }

// // 5) Успешный ответ для веб-хука
// echo 'success';
// http_response_code(200);
// exit;
?>
