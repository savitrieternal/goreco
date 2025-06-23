<?php
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter\Csrf;
use Bitrix\Crm\DealTable;  // <-- добавили

if (!CModule::IncludeModule('crm'))
    return;

class DealAddressComponent extends CBitrixComponent implements Controllerable
{
    public function configureActions()
    {
        return [
            'saveAddress' => [
                'prefilters' => [ new Csrf() ],
            ],
        ];
    }

    /**
     * @param int    $dealId
     * @param int    $rowIndex
     * @param string $address
     * @return array
     * @throws \Exception
     */
    public function saveAddressAction($dealId, $rowIndex, $address)
    {
        $dealId   = (int)$dealId;
        $rowIndex = (int)$rowIndex;
        $address  = trim($address);

        // 1) Читаем текущее значение UF-поля
        $rsDeal = CCrmDeal::GetListEx(
            [],
            ['=ID' => $dealId],
            false,
            false,
            ['UF_CRM_1749574676273']
        );
        $map = [];
        if ($arDeal = $rsDeal->Fetch())
        {
            if (!empty($arDeal['UF_CRM_1749574676273']))
            {
                $decoded = json_decode($arDeal['UF_CRM_1749574676273'], true);
                if (is_array($decoded))
                {
                    $map = $decoded;
                }
            }
        }

        // 2) Обновляем массив
        $map[$rowIndex] = $address;

        // 3) Сохраняем через D7 DealTable::update
        $result = DealTable::update(
            $dealId,
            ['UF_CRM_1749574676273' => json_encode($map, JSON_UNESCAPED_UNICODE)]
        );

        if (!$result->isSuccess())
        {
            // Собираем все ошибки в строку
            $errors = implode('; ', $result->getErrorMessages());
            throw new \Exception('Не удалось сохранить адрес: '.$errors);
        }

        return ['success' => true];
    }

    public function executeComponent()
    {
        // шаблон не нужен
    }
}
