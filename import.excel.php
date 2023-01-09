<?

use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;

class ImportExcel
{
    
    private static $ibID = 13;
    private static $ibSkuID = 2;
    
    
    public static function UpdateCatalog()
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        \Bitrix\Main\Loader::includeModule('catalog');
        \Bitrix\Main\Loader::includeModule('sale');
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        $arResult['SECTION'] = self::GetSection();
        $arResult['ELEMENT'] = self::GetElement();
        //$arResult['SKU'] = self::GetSKU();
        
        $arCatalog = self::GetCatalog();
        
        
        foreach ($arCatalog['groups'] as $item) {
            
            if (false) {
                $arResult['SECTION'][$item['id']] = self::UpdateSection($item, $arResult['SECTION'][$item['id']]);
                
            }
            
            if ($item['parentGroup'] != false) {
                $parentSection = $arResult['SECTION'][$item['parentGroup']];
                $item['section'] = $parentSection;
                //print_r2($item);
                $arResult['ELEMENT'][$item['id']] = self::UpdateElement($item, $arResult['ELEMENT'][$item['id']]);
                
            } else {
                $parentSection = $arResult['SECTION'][$item['parentGroup']];
                $arResult['SECTION'][$item['id']] = self::UpdateSection($item, $arResult['SECTION'][$item['id']],
                    $parentSection);
                
            }
        }
        
        foreach ($arCatalog['products'] as $item) {
            
            if ($arResult['ELEMENT'][$item['parentGroup']])//основной элемент создан//добавляем тольклько sku
            {
                
                $item['CML2_LINK'] = $arResult['ELEMENT'][$item['parentGroup']];
                //print_r2(array('add sku', $item['name'], $item['CML2_LINK']));
                $arResult['SKU'][$item['id']] = self::UpdateSKU($item, $arResult['SKU'][$item['id']]);
            } else {
                $parentSection = $arResult['SECTION'][$item['parentGroup']];
                $item['section'] = $parentSection;
                
                $arResult['ELEMENT'][$item['id']] = self::UpdateElement($item, $arResult['ELEMENT'][$item['id']]);
                $item['CML2_LINK'] = $arResult['ELEMENT'][$item['id']];
                $arResult['SKU'][$item['id']] = self::UpdateSKU($item, $arResult['SKU'][$item['id']]);
                
            }
            //$ar['section'] = $arResult['SECTION'][$item['id']];
            // $arResult['ELEMENT'][$item['id']] = self::UpdateElement($item, $arResult['ELEMENT'][$item['id']]['ID']);
        }
    }
    
    public static function GetSection()
    {
        $arResult = array();
        $ob = CIBlockSection::GetList(array(), array('IBLOCK_ID' => self::$ibID));
        while ($ar = $ob->GetNext()) {
            $arResult[$ar['CODE']] = $ar['ID'];
        }
        return $arResult;
    }
    
    public static function GetElement()
    {
        $arResult = array();
        $ob = CIBlockElement::GetList(array(), array('IBLOCK_ID' => self::$ibID));
        while ($ar = $ob->GetNext()) {
            $arResult[$ar['CODE']] = $ar['ID'];
        }
        return $arResult;
    }
    
    public static function GetSKU()
    {
        $arResult = array();
        $ob = CIBlockElement::GetList(
            array(),
            array('IBLOCK_ID' => self::$ibSkuID),
            false,
            false,
            array('ID', 'NAME', 'XML_ID', 'PROPERTY_CML2_LINK', 'IBLOCK_ID')
        );
        while ($ar = $ob->GetNext()) {
            $arResult[$ar['CODE']] = $ar['ID'];
        }
        return $arResult;
    }
    
    public static function UpdateSection($ar, $ID = 0, $parent = 0)
    {
        $bs = new CIBlockSection;
        $arTransParams = array(
            "max_len"               => 100,
            "change_case"           => 'L', // 'L' - toLower, 'U' - toUpper, false - do not change
            "replace_space"         => '_',
            "replace_other"         => '_',
            "delete_repeat_replace" => true
        );
        
        $transName = CUtil::translit($ar["name"], "ru", $arTransParams);
        $arFields = array(
            "CODE"      => $transName,
            "ACTIVE"    => "Y",
            "IBLOCK_ID" => self::$ibID,
            "NAME"      => $ar['name'],
            //"SORT" => intval($ar['order']),
            "XML_ID"    => $ar['id'],
        );
        if ($parent > 0) {
            $arFields["IBLOCK_SECTION_ID"] = $parent;
        }
        
        if ($ID > 0) {
            unset($arFields['IBLOCK_ID']);
            $res = $bs->Update($ID, $arFields);
        } else {
            $ID = $bs->Add($arFields);
            if ($ID == false) {
                if ($parent > 0) {
                    $arFields['CODE'] = $arFields['CODE'] . $parent;
                } else {
                    $arFields['CODE'] = $arFields['CODE'] . '2';
                }
                print_r2(array('добавлен раздел', $arFields));
                $ID = $bs->Add($arFields);
                
            }
        }
        return $ID;
    }
    
    public static function UpdateElement($arFields, $ID)
    {
        $el = new CIBlockElement;
        $ar['name'] = trim(str_replace('модиф', '', $ar['name']));
        $arFields = array(
            "ACTIVE"            => "Y",
            "IBLOCK_ID"         => self::$ibID,
            "NAME"              => $ar['name'],
            "SORT"              => $ar['order'],
            "XML_ID"            => $ar['id'],
            "IBLOCK_SECTION_ID" => $ar['section'],
            "PREVIEW_TEXT"      => $ar['description'],
            /*"PROPERTY_VALUES" => array(
                "CML2_ARTICLE" => $item["ARTICUL"],
                "SKLAD_7"  => $item["COUNT"],
                "NAME_CORRECT" => "Да"
            )*/
        );
        if ( ! empty($ar['images'][0]['imageUrl'])) {
            $arFields['DETAIL_PICTURE'] = CFile::MakeFileArray($ar['images'][0]['imageUrl']);
        }
        
        if ($ID > 0) {
            unset($arFields['IBLOCK_ID']);
            $el->Update($ID, $arFields);
        } else {
            $ID = $el->Add($arFields);
        }
        
        if ($ID > 0) {
            unset($arFields['IBLOCK_ID']);
            $el->Update($ID, $arFields);
            self::UpdatePrice($ID, $ar['price']);
        } else {
            $ID = $el->Add($arFields);
            self::UpdatePrice($ID, $ar['price']);
        }
        
        return $ID;
    }
    
    public static function UpdateSKU($ar, $ID)
    {
        $ar['name'] = trim(str_replace('модиф', '', $ar['name']));
        $el = new CIBlockElement;
        $PROP = array();
        $PROP['CML2_LINK'] = $ar['CML2_LINK'];
        if (strpos($ar['name'], 25)) {
            $PROP['SIZE'] = 1;
        } elseif (strpos($ar['name'], 30)) {
            $PROP['SIZE'] = 2;
        } elseif (strpos($ar['name'], 35)) {
            $PROP['SIZE'] = 3;
        }
        
        $arFields = array(
            "ACTIVE"          => "Y",
            "IBLOCK_ID"       => self::$ibSkuID,
            "PROPERTY_VALUES" => $PROP,
            "NAME"            => $ar['name'],
            "SORT"            => $ar['order'],
            "XML_ID"          => $ar['id'],
            "CODE"            => $ar['code'],
            "PREVIEW_TEXT"    => $ar['description'],
        );
        if ( ! empty($ar['images'][0]['imageUrl'])) {
            $arFields['DETAIL_PICTURE'] = CFile::MakeFileArray($ar['images'][0]['imageUrl']);
        }
        
        if ($ID > 0) {
            unset($arFields['IBLOCK_ID']);
            $el->Update($ID, $arFields);
            self::UpdatePrice($ID, $ar['price']);
        } else {
            $ID = $el->Add($arFields);
            self::UpdatePrice($ID, $ar['price']);
        }
        return $ID;
    }
    
    public static function UpdatePrice($BXID, $price)
    {
        $db_res = CPrice::GetListEx(array(), array("PRODUCT_ID" => $BXID, "CATALOG_GROUP_ID" => 1));
        if ($ar_res = $db_res->Fetch()) {
            //print_r2($ar_res);
            $arFields = array(
                "PRODUCT_ID"       => $BXID,
                "CATALOG_GROUP_ID" => 1,
                "PRICE"            => $price,
                "CURRENCY"         => "KZT"
            );
            CPrice::Update($ar_res['ID'], $arFields);
            //CCatalogProduct::Update($BXID, array('QUANTITY' => 1));
        } else {
            $arFields = array(
                "PRODUCT_ID"       => $BXID,
                "CATALOG_GROUP_ID" => 1,
                "PRICE"            => $price,
                "CURRENCY"         => "KZT"
            );
            //print_r2($arFields);
            CPrice::Add($arFields);
            
            CCatalogProduct::Add(
                array(
                    "ID"       => $BXID,
                    "QUANTITY" => 1
                )
            );
        }
    }
    
    public static function PropertyGetList()
    {
        $arProps = [];
        \Bitrix\Main\Loader::includeModule('iblock');
        $ob = CIBlockProperty::GetList([], ['IBLOCK_ID' => self::$ibID, 'PROPERTY_TYPE' => 'L']); //Список
        while ($ar = $ob->Fetch()) {
            $ar['ENUM_LIST'] = self::PropertyEnumGetList($ar['CODE']);
            $arProps[$ar['NAME']] = $ar;
        }
        $ob = CIBlockProperty::GetList([], ['IBLOCK_ID' => self::$ibID, 'PROPERTY_TYPE' => 'S']);
        while ($ar = $ob->Fetch()) {
            $arProps[$ar['NAME']] = $ar;
        }
        $ob = CIBlockProperty::GetList([], ['IBLOCK_ID' => self::$ibID, 'PROPERTY_TYPE' => 'N']);
        while ($ar = $ob->Fetch()) {
            $arProps[$ar['NAME']] = $ar;
        }
        $ob = CIBlockProperty::GetList([], ['IBLOCK_ID' => self::$ibID, 'PROPERTY_TYPE' => 'F']);
        while ($ar = $ob->Fetch()) {
            $arProps[$ar['NAME']] = $ar;
        }
        $ob = CIBlockProperty::GetList([], ['IBLOCK_ID' => self::$ibID, 'PROPERTY_TYPE' => 'E']);
        while ($ar = $ob->Fetch()) {
            $arProps[$ar['NAME']] = $ar;
            $obEl = CIBlockElement::GetList([], ['IBLOCK_ID'=> $ar['LINK_IBLOCK_ID']]);
            while ($arEl = $obEl->Fetch()) {
                $arProps[$ar['NAME']]['ENUM_LIST'][mb_strtolower(trim($arEl['NAME']))] = $arEl['ID'];
            }
        }
        $ob = CIBlockProperty::GetList([], ['IBLOCK_ID' => self::$ibID, 'USER_TYPE' => 'directory']);
        while ($ar = $ob->Fetch()) {
            if(!empty($ar['USER_TYPE_SETTINGS']['TABLE_NAME'])) {
                $hlOb
                    = HL\HighloadBlockTable::getList(['filter' => ['TABLE_NAME' => $ar['USER_TYPE_SETTINGS']['TABLE_NAME']]]);
                if ($arHL = $hlOb->fetch()) {
                    $ar['HL'] = $arHL;
                    $ar['HL_ENUM_LIST'] = self::PropertyHLValueGetList($ar['CODE'], $ar['HL']['ID']);
                    $arProps[$ar['NAME']] = $ar;
                }
            }
        }
        //dump($arProps);
        return $arProps;
    }
    
    public static function PropertyEnumGetList($code)
    {
        $arPropsEnum = [];
        $db_enum_list = CIBlockProperty::GetPropertyEnum($code, [], ["IBLOCK_ID" => self::$ibID]);
        while ($ar_enum_list = $db_enum_list->GetNext()) {
            $arPropsEnum[$ar_enum_list['VALUE']] = $ar_enum_list['ID'];
        }
        return $arPropsEnum;
    }
    
    public static function PropertyEnumAdd($PROPERTY_ID, $value)
    {
        $ibpenum = new CIBlockPropertyEnum;
        if ($PropID = $ibpenum->Add(array('PROPERTY_ID' => $PROPERTY_ID, 'VALUE' => $value))) {
            return $PropID;
        }
    }
    
    public static function PropertyHLValueGetList($code, $hlID)
    {
        if(empty($hlID))
            return false;
        $arPropsEnum = [];
        
        $hlblock = HL\HighloadBlockTable::getById($hlID)->fetch();
        if(!empty($hlblock)) {
            $entity = HL\HighloadBlockTable::compileEntity($hlblock);
            $entity_data_class = $entity->getDataClass();
    
            $rsData = $entity_data_class::getList(array(
                "select" => array("*"),
                "order"  => array("ID" => "ASC"),
            ));
    
            while ($arData = $rsData->Fetch()) {
                $arPropsEnum[$arData['UF_NAME']] = $arData['UF_XML_ID'];
            }
        }
        return $arPropsEnum;
    }
    
    public static function PropertyHLAddValue($id, $value)
    {
        if(empty($value)) return '';
        $hlbl = $id; // Указываем ID нашего highloadblock блока к которому будет делать запросы.
        $hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch();
        
        $entity = HL\HighloadBlockTable::compileEntity($hlblock);
        $entity_data_class = $entity->getDataClass();
    
        $arTransParams = array(
            "max_len"               => 100,
            "change_case"           => 'L', // 'L' - toLower, 'U' - toUpper, false - do not change
            "replace_space"         => '_',
            "replace_other"         => '_',
            "delete_repeat_replace" => true
        );
        
        $data = array(
            "UF_NAME"  => $value,
            "UF_XML_ID" => CUtil::translit($value, "ru", $arTransParams)
        );
        $result = $entity_data_class::add($data);
        //dump([$data, $result->getId()]);
        return $data['UF_XML_ID'];
    }
}

?>
