<?php
// local/ajax/get_product_date.php
// Returns product PROPERTY_121 value as date string
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('PUBLIC_AJAX_MODE', true);

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;

if (!Loader::includeModule('iblock')) {
    echo Json::encode(['error' => 'iblock module not installed']);
    exit;
}

$productId = isset($_POST['productId']) ? (int)$_POST['productId'] : 0;
$iblockId  = 24; // catalog iblock id

if ($productId <= 0) {
    echo Json::encode(['error' => 'invalid productId']);
    exit;
}

$propRes = CIBlockElement::GetProperty($iblockId, $productId, [], ['ID' => 121]);
$value   = '';
if ($prop = $propRes->Fetch()) {
    $value = trim($prop['VALUE']);
}

header('Content-Type: application/json; charset=UTF-8');
echo Json::encode(['date' => $value]);
