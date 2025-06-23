<?
require_once(__DIR__ . '/crest/crest.php');
$select = array('UF_*', '*');

// сделка
$result = CRest::call('crm.deal.get', array(
    'id' => 11406,
    'select' => $select,
));

// эс
// $result = CRest::call('crm.item.get', array(
//     'id' => 48,
//     'select' => $select,
//     'entityTypeId' => 1040
// ));

// отчет
// $result = CRest::call('crm.item.get', array(
//     'id' => 52,
//     'select' => $select,
//     'entityTypeId' => 1046
// ));




echo "<pre>";
print_r($result);
echo "</pre>";
?>