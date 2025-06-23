<?php
// local/ajax/get_company_addresses.php
// Включаем вывод ошибок (только в разработке)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('PUBLIC_AJAX_MODE', true);

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;

if (!Loader::includeModule('crm')) {
    echo Json::encode(['error' => 'crm module not installed']);
    exit;
}

$dealId = isset($_POST['dealId']) ? (int)$_POST['dealId'] : 0;
if ($dealId <= 0) {
    echo Json::encode(['error' => 'invalid dealId']);
    exit;
}

// Получаем сделку
$arDeal = CCrmDeal::GetByID($dealId, false);
if (!$arDeal) {
    echo Json::encode(['error' => 'deal not found']);
    exit;
}

$companyId = (int)$arDeal['COMPANY_ID'];
if ($companyId <= 0) {
    echo Json::encode(['error' => 'company not bound']);
    exit;
}

// 1) Получаем компанию c UF-полем адресов
$fieldCodeCompany = 'UF_CRM_1749209962992';
$rs = CCrmCompany::GetList(
    [],
    ['ID' => $companyId],
    [],
    ['nTopCount' => 1],
    ['*', 'UF_*']
);
if ($row = $rs->Fetch()) {
    $arCompany = $row;
} else {
    echo Json::encode(['error' => 'company not found']);
    exit;
}

// Собираем и очищаем адреса из UF-поля компании
$addresses = [];
if (
    isset($arCompany[$fieldCodeCompany])
    && is_array($arCompany[$fieldCodeCompany])
    && count($arCompany[$fieldCodeCompany]) > 0
) {
    foreach ($arCompany[$fieldCodeCompany] as $addrEntry) {
        // разбиваем по маркеру и берём только текст до него
        $parts = explode('|;|', $addrEntry);
        $clean = trim($parts[0]);
        if ($clean !== '') {
            $addresses[] = $clean;
        }
    }
}

// 2) Читаем маппинг из UF-поля сделки
$fieldCodeDeal = 'UF_CRM_1749574676273';
$map = [];
$rsDeal = CCrmDeal::GetListEx(
    [],
    ['ID' => $dealId],
    false,
    false,
    ['UF_CRM_1749574676273']
);
if ($dealRow = $rsDeal->Fetch()) {
    if (!empty($dealRow[$fieldCodeDeal])) {
        $decoded = Json::decode($dealRow[$fieldCodeDeal]);
        if (is_array($decoded)) {
            $map = $decoded;
        }
    }
}

// Выдаём JSON
header('Content-Type: application/json; charset=UTF-8');
echo Json::encode([
    'dealId'    => $dealId,
    'companyId' => $arCompany,
    'addresses' => $addresses,
    'map'       => $map,
]);
