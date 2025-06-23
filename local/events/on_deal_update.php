<?php
// /home/bitrix/www/local/events/on_deal_update.php

// Файл лога
define('LOG_FILE', __DIR__ . '/on_deal_update.log');

/**
 * Пишем строку в лог с текущим временем
 */
function addLog(string $msg): void
{
    file_put_contents(
        LOG_FILE,
        date('Y-m-d H:i:s') . " — " . $msg . PHP_EOL,
        FILE_APPEND
    );
}

// 1) Подключаем CREST
require_once(__DIR__ . '/crest/crest.php');

// 2) Читаем входные параметры из $_REQUEST
addLog('2) Получение данных из $_REQUEST');
// addLog('   $_REQUEST dump: ' . print_r($_REQUEST, true));

$data   = $_REQUEST['data'] ?? [];
$fields = $data['FIELDS'] ?? [];
$dealId = (int)($fields['ID'] ?? 0);
addLog("   Из запроса получили только ID сделки: {$dealId}");

// 2.5 ) Проверяем есть ли товары из раздела Отчеты
addLog("2.5) Запрос crm.deal.productrows.get для сделки {$dealId}");
$prodRes = CRest::call('crm.deal.productrows.get', ['id' => $dealId]);
// addLog('   Ответ crm.deal.productrows.get: ' . print_r($prodRes, true));

$products = $prodRes['result'] ?? [];
addLog('   Всего товаров в сделке: ' . count($products));

foreach ($products as $idx => $row) {
    $offerId = $row['PRODUCT_ID'];
    addLog("   Оффер ID={$offerId}");

    // 1) Получаем данные оффера
    $offerRes = CRest::call('catalog.product.get', ['id' => $offerId]);
    addLog('      catalog.product.get (offer): ' . print_r($offerRes, true));
    $offerData = $offerRes['result']['product'] ?? [];

    // 2) Вычисляем ID основного товара
    $baseProductId = (int)($offerData['parentId']['value'] ?? 0);
    if (!$baseProductId) {
        addLog("      Нет parentId для оффера {$offerId}, пропускаем");
        // Устанавливаем нулевой раздел, если нужно
        $products[$idx]['SECTION_ID'] = 0;
        continue;
    }
    addLog("      Основной товар ID={$baseProductId}");
    // Сохраним его тоже, если нужно
    $products[$idx]['BASE_PRODUCT_ID'] = $baseProductId;

    // 3) Получаем данные основного товара
    $baseRes = CRest::call('catalog.product.list', [
        'filter' => ['=id' => $baseProductId, 'iblockId' => 24],
        'select' => ['id', 'iblockId', 'iblockSectionId']
    ]);
    addLog('      catalog.product.list (base): ' . print_r($baseRes, true));

    // В зависимости от формата результата
    $sectionId = 0;
    if (!empty($baseRes['result']['products'][0]['iblockSectionId'])) {
        $sectionId = (int)$baseRes['result']['products'][0]['iblockSectionId'];
    } elseif (!empty($baseRes['result'][0]['IBLOCK_SECTION_ID'])) {
        $sectionId = (int)$baseRes['result'][0]['IBLOCK_SECTION_ID'];
    }
    addLog("      Раздел основного товара: {$sectionId}");

    // 4) Записываем в массив
    $products[$idx]['SECTION_ID'] = $sectionId;
}





// 3) Запрос полной сделки, сразу со всеми UF_*
addLog("3) Запрос crm.deal.get для сделки {$dealId} (все UF_*)");
$dealGet = CRest::call('crm.deal.get', [
    'id'     => $dealId,
    'select' => ['*', 'UF_*']
]);
// addLog('   Ответ crm.deal.get: ' . print_r($dealGet, true));


// ********************

if ($dealId !== 11406) {
    addLog("   Сделка {$dealId} не тестовая, выходим");
    http_response_code(500);
    exit;
}








if (empty($dealGet['result'])) {
    addLog("   Ошибка: сделка {$dealId} не найдена");
    http_response_code(500);
    exit;
}



$dealData = $dealGet['result'];
$stageId  = $dealData['STAGE_ID'] ?? '<не задана>';
$boundEco = $dealData['PARENT_ID_1040'] ?? 0;
addLog("   В ответе STAGE_ID = '{$stageId}'");

// 4) Проверяем стадию и привязку эс
if ($stageId !== '8740560') {
    addLog("   Пропуск: стадия '{$stageId}' ≠ '8740560'");
    http_response_code(200);
    exit('Stage mismatch');
}
addLog('   Стадия совпадает, продолжаем…');

if ($boundEco) {
    addLog("   ЭкоСопровождение уже привязано (PARENT_ID_1040={$boundEco}), выходим.");
    http_response_code(200);
    exit;
}

// 4.5) Получаем компанию, привязанную к сделке, и её наименование
$companyId = $dealData['COMPANY_ID'] ?? 0;
if ($companyId) {
    addLog("4.5) Запрос crm.company.get для компании {$companyId}");
    $companyGet = CRest::call('crm.company.get', [
        'id'     => $companyId,
        'select' => ['ID', 'TITLE']
    ]);
    // addLog('     Ответ crm.company.get: ' . print_r($companyGet, true));

    $companyTitle = $companyGet['result']['TITLE'] ?? '<без названия>';
    addLog("     Название компании: {$companyTitle}");
} else {
    addLog('4.5) Компания не привязана к сделке');
}

// 7) Создаём «Экосопровождение» (1040)
addLog("7) Создание элемента ЭкоСопровождение (1040) для сделки {$dealId}");
$currYear = date("Y");
$ecoFields = [
    'title'             => "ЭС {$companyTitle} ({$currYear})",
    'parentId2' => $dealId,
    'ufCrm4_1749200267126' => $dealData['UF_CRM_1688738703839'],
    'ufCrm4_1749200096453' => $dealData['UF_CRM_1688738931896'],
    'ufCrm4_1749200125449' => $dealData['UF_CRM_1749750815409'],
    'assignedById' => $dealData['UF_CRM_1700738335'],
];
addLog('   Переданные поля: ' . print_r($ecoFields, true));
$ecoCreate = CRest::call('crm.item.add', [
    'entityTypeId' => 1040,
    'fields'       => $ecoFields,
]);
// addLog('   Ответ crm.item.add (Эко): ' . print_r($ecoCreate, true));
if (!empty($ecoCreate['error'])) {
    addLog("   Ошибка создания ЭкоСопровождения: {$ecoCreate['error_description']}");
    http_response_code(500);
    exit;
}
$ecoId = $ecoCreate['result']['item']['id'];
addLog("   ЭкоСопровождение создано, ID={$ecoId}");

// 7.1) Обновляем сделку, записываем в поле PARENT_ID_1040 ID созданного смарт-процесса
addLog("7.1) Обновление сделки {$dealId}: устанавливаем PARENT_ID_1040 = {$ecoId}");
$dealUpdate = CRest::call('crm.deal.update', [
    'id'     => $dealId,
    'fields' => [
        'PARENT_ID_1040' => $ecoId,
    ],
]);
// addLog('   Ответ crm.deal.update: ' . print_r($dealUpdate, true));
if (!empty($dealUpdate['error'])) {
    addLog("   Ошибка обновления сделки: {$dealUpdate['error_description']}");
} else {
    addLog("   Сделка {$dealId} успешно привязана к ЭкоСопровождению {$ecoId}");
}

// 9) Декодируем JSON-маппинг адресов
addLog("9) Декодирование JSON-маппинга адресов");
$mapJson = $dealData['UF_CRM_1749574676273'] ?? '{}';
addLog('5) Сырой JSON из UF_CRM_1749574676273: ' . $mapJson);
$addressMap = json_decode($mapJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    addLog('   Ошибка JSON в UF_CRM_ADDRESS_MAP: ' . json_last_error_msg());
    $addressMap = [];
}
addLog('   Расшифрованный addressMap: ' . print_r($addressMap, true));

// 10) Цикл по товарам и создание «Отчётов»
addLog('10) Цикл создания элементов Отчёты (1046)');
foreach ($products as $index => $row) {
    $prodId   = $row['PRODUCT_ID'];
    $prodName = $row['PRODUCT_NAME'];
    $addresses = $addressMap[$index+1] ?? [];
    addLog("   Товар #{$index}: ID={$prodId}, NAME=\"{$prodName}\", адрес=" . $addresses);

    $reportFields = [
        'title'             => "Отчёт по «{$prodName}»",
        'parentId1040' => $ecoId,
        'ufCrm5_1749062018496'      => $addresses,
    ];
    addLog('   Поля для создания Отчёта: ' . print_r($reportFields, true));

    $repCreate = CRest::call('crm.item.add', [
        'entityTypeId' => 1046,
        'fields'       => $reportFields,
    ]);
    addLog('   Ответ crm.item.add (Отчёт): ' . print_r($repCreate, true));
    if (!empty($repCreate['error'])) {
        addLog("   Ошибка создания Отчёта для продукта {$prodId}: {$repCreate['error_description']}");
    } else {
        $repId = $repCreate['result']['item']['id'] ?? '<неопределён>';
        addLog("   Отчёт создан успешно, ID={$repId}");
    }
}

addLog('11) Все отчёты созданы, завершение скрипта');

// 12) Успешный ответ
echo 'success';
http_response_code(200);
exit;
?>
