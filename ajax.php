<?php

header("Content-type: application/json; charset=utf-8");

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

Loader::includeModule("iblock");
Cmodule::IncludeModule("catalog");
Loader::includeModule("highloadblock");
$blockElement = new CIBlockElement;

$iblockID = 13;

const STEP_COUNT = 10;
$currentStep = 1;
$endRow = false;
$logEditName = 0;
$updatePhoto = 0;
$totalStep = 0;
$addSection = 0;
$logProductUpdate = 0;
$logPriceUpdate = 0;
$updateElementSection = 0;
$logProductAdd = 0;
$logPriceAdd = 0;
$logPriceSkip = 0;
$arIssetSection2 = [];
$arIssetElement = [];


$arStep = json_decode(file_get_contents('step.json'), true);
if ( ! empty($arStep['step'])) {
    $currentStep = $arStep['step'] + 1;
    $updatePhoto = $arStep['updatePhoto'];
    $logEditName = $arStep['edit_name'];
    $totalStep = $arStep['total'];
    $addSection = $arStep['section_add'];
    $logProductAdd = $arStep['product_add'];
    $logProductUpdate = $arStep['product_update'];
    $logPriceUpdate = $arStep['price_update'];
    $logPriceAdd = $arStep['price_add'];
    $logPriceSkip = $arStep['price_skip'];
    $arIssetSection2 = $arStep['arIssetSection2'];
    $arIssetElement = $arStep['arIssetElement'];
    $updateElementSection = $arStep['updateElementSection'];
    $arrElementSections = $arStep['arrElementSections'];
}
if ( ! empty($arStep['total']) && $currentStep >= $arStep['total']) {
    rename('step.json', 'old_step.json');
    
    //print_r2($arIssetSection2);
    //print_r2($arIssetElement);
    $del = 0;
    if (count($arIssetElement) > 10) {
        $requestElements = $blockElement::GetList(
            array("SORT" => "ASC"),
            array("IBLOCK_ID" => $iblockID, "!ID" => $arIssetElement),
            false,
            false,
            array(
                "ID",
                "NAME",
                "IBLOCK_ID",
                "CATALOG_GROUP_1",
                "CODE",
                "PREVIEW_PICTURE",
                "PROPERTY_CML2_ARTICLE",
            )
        );
        $arrElements = [];
        
        while ($element = $requestElements->GetNext()) {
            $el = new CIBlockElement();
            $el->Update($element['ID'], array('ACTIVE' => 'N'));
            $del++;
        }
    }
    file_put_contents('del.txt', $del);
    die('ok');
}

if ( ! file_exists('catalog.xlsx')) {
    $output = array(
        "type" => "error",
        "text" => "Ошибка при открытии файла",
    );
    echo json_encode($output);
}

// подключим класс для работы с талицами
require_once $_SERVER["DOCUMENT_ROOT"] . '/import/classes/PHPExcel.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/import/import.excel.php';
$startTime = microtime(true);
$excel = PHPExcel_IOFactory::createReaderForFile('catalog.xlsx');

$excelObj = $excel->load('catalog.xlsx');
$worksheet = $excelObj->getSheet(0);
$lastRow = $worksheet->getHighestRow();

$output = []; // массив с ответом для ajax
$mapArticul = []; // карта артикулов для выборки из API Битрикс

$start = (($currentStep - 1) * STEP_COUNT) + 1;
//print_r2('$start'.$start);
$end = $currentStep * STEP_COUNT;
//print_r2('$end'.$end);
// соберем всю информацию в цикле в массив $product
$product = [];
$arSection = [];

$arTransParams = array(
    "max_len"               => 100,
    "change_case"           => 'L', // 'L' - toLower, 'U' - toUpper, false - do not change
    "replace_space"         => '_',
    "replace_other"         => '_',
    "delete_repeat_replace" => true
);

$arProps = ImportExcel::PropertyGetList();


$arPropsIsset = [];

$columnName = '';
$columnPrice = '';
$columnPreviewP = '';
$columnDetailP = '';
$columnSection1 = '';
$columnSection2 = '';

$highestColumm = $worksheet->getHighestColumn(); // e.g. "EL"
$highestRow = 1;
$highestColumm++;
for ($row2 = 1; $row2 < $highestRow + 1; $row2++) {
    $dataset = array();
    for ($column = 'A'; $column != $highestColumm; $column++) {
        if (trim($worksheet->getCell($column . $row2)->getValue()) == 'Название элемента') {
            $columnName = $column;
        } elseif (trim($worksheet->getCell($column . $row2)->getValue()) == 'Категория') {
            $columnSection1 = $column;
        } elseif (trim($worksheet->getCell($column . $row2)->getValue()) == 'Тип (подкатегория)') {
            $columnSection2 = $column;
        } elseif (trim($worksheet->getCell($column . $row2)->getValue()) == 'Цена') {
            $columnPrice = $column;
        } elseif (trim($worksheet->getCell($column . $row2)->getValue()) == 'Картинка для анонса') {
            $columnPreviewP = $column;
        } elseif (trim($worksheet->getCell($column . $row2)->getValue()) == 'Картинка для анонса') {
            $columnPreviewP = $column;
        } elseif (trim($worksheet->getCell($column . $row2)->getValue()) == 'Детальная картинка') {
            $columnDetailP = $column;
        } elseif ( ! empty($arProps[$worksheet->getCell($column . $row2)->getValue()])) {
            $arPropsIsset[$column] = $arProps[$worksheet->getCell($column . $row2)->getValue()];
        }
    }
}

if ($columnName == false) {
    die();
}

for ($row = $start; $row <= $end; $row++) {
    
    if ($row > $lastRow) {
        $endRow = true;
    }
    if ($row > $lastRow || $row == 1) {
        continue;
    }
    
    $name = (string)$worksheet->getCell($columnName . $row)->getValue();
    
    if (empty($name)) {
        continue;
    }
    
    $articul = CUtil::translit($name, "ru", $arTransParams);
    
    
    $section1 = trim($worksheet->getCell($columnSection1 . $row)->getValue(), " \t\n\r\0\x0B\xA0");
    $section2 = trim($worksheet->getCell($columnSection2 . $row)->getValue(), " \t\n\r\0\x0B\xA0");
    $section3 = ''; //trim($worksheet->getCell('C' . $row)->getValue()," \t\n\r\0\x0B\xA0");
    
    
    if ($section1 && empty($arSection[$section1])) {
        $arSection[$section1] = [];
    }
    if ($section2 && empty($arSection[$section1][$section2])) {
        $arSection[$section1][$section2] = [];
    }
    if ($section3 && empty($arSection[$section1][$section2][$section3])) {
        $arSection[$section1][$section2][$section3] = 1;
    }
    
    
    $section = '';
    if ( ! empty($section3)) {
        $section = mb_strtolower($section1 . '-' . $section2 . '-' . $section3);
    } elseif ( ! empty($section2)) {
        $section = mb_strtolower($section1 . '-' . $section2);
    } elseif ( ! empty($section1)) {
        $section = mb_strtolower($section1);
    }
    
    $arFile = [];
    /*$arFileRow = array('O', 'P', 'Q', 'R', 'S');
    $arFile[] = $worksheet->getCell('K' . $row)->getValue().'.jpg';
    foreach ($arFileRow as $key) {
        $img = $worksheet->getCell($key . $row)->getValue();
        if (!empty($img)) {
            $arFile[] = $img;
        }
    }*/
    
    //$CML2_ARTICLE = $worksheet->getCell('B' . $row)->getValue();
    $CML2_ARTICLE = $articul;
    // Сформируем массив
    // $mapArticul[] = $articul;
    $mapArticul[] = $CML2_ARTICLE;
    $product[$CML2_ARTICLE] = array(
        "ROW"     => $row,
        "ARTICUL" => $articul,
        "CODE"    => $articul,
        "NAME"    => $name,
        //"DESCRIPTION_PRE" => $worksheet->getCell('E' . $row)->getValue(),
        //"DESCRIPTION"     => $worksheet->getCell('G' . $row)->getValue(),
        "IMG"     => $worksheet->getCell($columnPreviewP . $row)->getValue(),
        "PROPS"   => [],
        "PRICE"   => $worksheet->getCell($columnPrice . $row)->getValue(),
        "SECTION" => $section,
        //"FILE" => $arFile
    );
    foreach ($arPropsIsset as $key => $item) {
        $item['VALUE'] = $worksheet->getCell($key . $row)->getValue();
        if ($item['PROPERTY_TYPE'] == 'L') {
            if ( ! empty($item['ENUM_LIST'][$item['VALUE']])) {
                $product[$CML2_ARTICLE]['PROPS'][$item['CODE']] = $item['ENUM_LIST'][$item['VALUE']];
            } else {
                $item['ENUM_LIST'][$item['VALUE']] = ImportExcel::PropertyEnumAdd($item['ID'], $item['VALUE']);
                $arPropsIsset[$key] = $item;
                $product[$CML2_ARTICLE]['PROPS'][$item['CODE']] = $item['ENUM_LIST'][$item['VALUE']];
            }
        } elseif ($item['PROPERTY_TYPE'] == 'E') {
            $item['VALUE'] = mb_strtolower(trim($item['VALUE']));
            if ( ! empty($item['ENUM_LIST'][$item['VALUE']])) {
                $product[$CML2_ARTICLE]['PROPS'][$item['CODE']] = $item['ENUM_LIST'][$item['VALUE']];
            } /*else {
                $item['ENUM_LIST'][$item['VALUE']] = ImportExcel::PropertyEnumAdd($item['ID'], $item['VALUE']);
                $arPropsIsset[$key] = $item;
                $product[$CML2_ARTICLE]['PROPS'][$item['CODE']] = $item['ENUM_LIST'][$item['VALUE']];
            }*/
        } elseif ($item['USER_TYPE'] == 'directory') {
            if ( ! empty($item['HL_ENUM_LIST'][$item['VALUE']])) {
                $product[$CML2_ARTICLE]['PROPS'][$item['CODE']] = $item['HL_ENUM_LIST'][$item['VALUE']];
            } else {
                $item['HL_ENUM_LIST'][$item['VALUE']] = ImportExcel::PropertyHLAddValue($item['HL']['ID'],
                    $item['VALUE']);
                $arPropsIsset[$key] = $item;
                $product[$CML2_ARTICLE]['PROPS'][$item['CODE']] = $item['HL_ENUM_LIST'][$item['VALUE']];
            }
        } elseif ($item['PROPERTY_TYPE'] == 'F' && !empty($item['VALUE'])) {
            if ( ! file_exists($_SERVER["DOCUMENT_ROOT"] . '/upload/product-3d/' . $item['VALUE'])) {
                if (file_exists($_SERVER["DOCUMENT_ROOT"] . '/upload/product-3d/' . $item['VALUE']
                    . '.zip')
                ) {
                    $item['VALUE'] = $_SERVER["DOCUMENT_ROOT"] . '/upload/product-3d/' . $item['VALUE'] . '.zip';
                }
            }
            if ( ! file_exists($_SERVER["DOCUMENT_ROOT"] . '/upload/product-3d/' . $item['VALUE'])) {
                if (file_exists($_SERVER["DOCUMENT_ROOT"] . '/upload/product-3d/' . $item['VALUE']
                    . '.rar')
                ) {
                    $item['VALUE'] = $_SERVER["DOCUMENT_ROOT"] . '/upload/product-3d/' . $item['VALUE'] . '.rar';
                }
            }
            $product[$CML2_ARTICLE]['PROPS'][$item['CODE']][] = CFile::MakeFileArray($item['VALUE']);
    
        } else {
            $product[$CML2_ARTICLE]['PROPS'][$item['CODE']] = $item['VALUE'];
        }
    }
    
}

//print_r2($product);
//print_r2($arSection);
//die();
unset($excel);
unset($excelObj);
unset($worksheet);
$time = microtime(true) - $startTime;
//print_r2($time);
$startTime = microtime(true);
$obSection = CIBlockSection::GetList(
    array("left_margin" => "asc"),
    array("IBLOCK_ID" => $iblockID),
    false,
    false
);
$arIssetSection = array();

$arDelSection = array();
$arParent = array();
while ($ar = $obSection->GetNext()) {
    $arParent[$ar['ID']] = $ar['NAME'];
    $arParent2[$ar['ID']] = $ar['IBLOCK_SECTION_ID'];
    if ( ! empty($arParent2[$ar['IBLOCK_SECTION_ID']]) && ! empty($arParent[$arParent2[$ar['IBLOCK_SECTION_ID']]])) {
        $arIssetSection[mb_strtolower($arParent[$arParent2[$ar['IBLOCK_SECTION_ID']]] . '-'
            . $arParent[$ar['IBLOCK_SECTION_ID']] . '-' . $ar['NAME'])]
            = $ar['ID'];
        //$arIssetSection2222[mb_strtolower($arParent[$arParent2[$ar['IBLOCK_SECTION_ID']]] . '-' . $arParent[$ar['IBLOCK_SECTION_ID']] . '-' . $ar['NAME'])] = $ar['ID'];
    } elseif ($ar['IBLOCK_SECTION_ID'] > 0) {
        $arIssetSection[mb_strtolower($arParent[$ar['IBLOCK_SECTION_ID']] . '-' . $ar['NAME'])] = $ar['ID'];
    } else {
        $arIssetSection[mb_strtolower($ar['NAME'])] = $ar['ID'];
    }
}

foreach ($arSection as $name => $arr) {
    if (empty($arIssetSection[mb_strtolower($name)])) {
        $parentID = ImportExcel::UpdateSection(array('name' => $name));
        $addSection++;
        $arIssetSection[mb_strtolower($name)] = $parentID;
        $arIssetSection2[$parentID] = $parentID;
    } else {
        $parentID = $arIssetSection[mb_strtolower($name)];
        ImportExcel::UpdateSection(array('name' => $name), $parentID);
        $arIssetSection2[$parentID] = $parentID;
    }
    foreach ($arr as $name2 => $arr2) {
        if (empty($arIssetSection[mb_strtolower($name . '-' . $name2)])) {
            $parentID2 = ImportExcel::UpdateSection(array('name' => $name2), 0, $parentID);
            $addSection++;
            $arIssetSection[mb_strtolower($name . '-' . $name2)] = $parentID2;
            $arIssetSection2[$parentID2] = $parentID2;
        } else {
            $parentID2 = $arIssetSection[mb_strtolower($name . '-' . $name2)];
            ImportExcel::UpdateSection(array('name' => $name2), $parentID2, $parentID);
            $arIssetSection2[$parentID2] = $parentID2;
            
        }
        foreach ($arr2 as $name3 => $arr3) {
            if (empty($arIssetSection[mb_strtolower($name . '-' . $name2 . '-' . $name3)])) {
                $parentID3 = ImportExcel::UpdateSection(array('name' => $name3), 0, $parentID2);
                $addSection++;
                $arIssetSection[mb_strtolower($name . '-' . $name2 . '-' . $name3)] = $parentID3;
                $arIssetSection2[$parentID3] = $parentID3;
            } else {
                $parentID3 = $arIssetSection[mb_strtolower($name . '-' . $name2 . '-' . $name3)];
                ImportExcel::UpdateSection(array('name' => $name3), $parentID3, $parentID2);
                $arIssetSection2[$parentID3] = $parentID3;
            }
            
        }
    }
    
}
//die();
//print_r2($arIssetSection);
/*foreach ($product as $item)
{
    print_r2($item);
    $s++;
    if($s > 10)
        break;
}*/
//die();
// Теперь обработаем данные, которые получили из таблицы.

$requestElements = $blockElement::GetList(
    array("SORT" => "ASC"),
    array(
        "IBLOCK_ID"   => $iblockID,
        "ACTIVE_DATE" => "Y",
        "ACTIVE"      => "Y",
        /*"CODE" => $mapArticul,*/
        /*"CODE" => $mapArticul*/
    ),
    false,
    false,
    array(
        "ID",
        "NAME",
        "IBLOCK_ID",
        "CATALOG_GROUP_1",
        "CODE",
        "PREVIEW_PICTURE",
        "PROPERTY_CML2_ARTICLE",
        "IBLOCK_SECTION_ID",
    )
);
$arrElements = [];

while ($element = $requestElements->GetNextElement()) {
    $item = $element->GetFields();
    $articul = $item["CODE"];
    $articul = CUtil::translit($item["NAME"], "ru", $arTransParams);
    
    
    if (empty($product[$articul])) {
        continue;
    }
    $arIssetElement[] = $item['ID'];
    
    
    $oldFile = CFile::GetPath($item['PREVIEW_PICTURE']);
    if ( ! file_exists($_SERVER["DOCUMENT_ROOT"] . '/upload/product-photo/' . $product[$articul]['IMG'])) {
        if (file_exists($_SERVER["DOCUMENT_ROOT"] . '/upload/product-photo/' . $product[$articul]['IMG'] . '.jpg')) {
            $product[$articul]['IMG'] = $product[$articul]['IMG'] . '.jpg';
        }
    }
    
    if (file_exists($_SERVER["DOCUMENT_ROOT"] . '/upload/product-photo/' . $product[$articul]['IMG'])) {
        $newFile = '/upload/product-photo/' . $product[$articul]['IMG'];
        //print_r2($newFile);
        if (filesize($_SERVER["DOCUMENT_ROOT"] . $newFile) != filesize($oldFile) or empty($item['PREVIEW_PICTURE'])) {
            $arLoadProductArray = array(
                "PREVIEW_PICTURE" => CFile::MakeFileArray($newFile),
                "DETAIL_PICTURE"  => CFile::MakeFileArray($newFile),
                //"IBLOCK_SECTION_ID" => $arIssetSection[mb_strtolower($product[$articul]['SECTION'])],
            );
            //print_r2($arLoadProductArray);
            $idElement = $blockElement->Update($item['ID'], $arLoadProductArray);
            $updatePhoto++;
        }
    }
    
    if ( ! empty($arIssetSection[mb_strtolower($product[$articul]['SECTION'])])) {
        $arrElementSections[$articul][] = $arIssetSection[mb_strtolower($product[$articul]['SECTION'])];
    }
    if (count($arrElementSections[$articul]) > 1) {
        //print_r2($arrElementSections[$articul]);
        ///print_r2($articul);
        $arLoadProductArray = array(
            "IBLOCK_SECTION_ID" => $arrElementSections[$articul][0],
            "IBLOCK_SECTION"    => $arrElementSections[$articul],
        );
        
        $idElement = $blockElement->Update($item['ID'], $arLoadProductArray);
        $updateElementSection++;
    } elseif ($arIssetSection[mb_strtolower($product[$articul]['SECTION'])] != $item['IBLOCK_SECTION_ID']) {
        $arLoadProductArray = array(
            "IBLOCK_SECTION_ID" => $arIssetSection[mb_strtolower($product[$articul]['SECTION'])],
        );
        
        $idElement = $blockElement->Update($item['ID'], $arLoadProductArray);
        $updateElementSection++;
    }
    
    $idElement = $blockElement->Update($item['ID'], [
        "PREVIEW_TEXT" => $item["DESCRIPTION_PRE"],
        "DETAIL_TEXT"  => $item["DESCRIPTION"]
    ]);
    
    
    if (intval($item["CATALOG_PRICE_1"]) != $product[$articul]["PRICE"]) {
        
        $arFields = array(
            "PRODUCT_ID"       => $item["ID"],
            "CATALOG_GROUP_ID" => 1, // Базовая цена
            "PRICE"            => $product[$articul]["PRICE"],
            "CURRENCY"         => "KZT",
        );
        
        // получим код ценового предложения
        $requestPrice = CPrice::GetList(array(), array("PRODUCT_ID" => $item["ID"], "CATALOG_GROUP_ID" => 1));
        if ($price = $requestPrice->Fetch()) {
            CPrice::Update($price["ID"], $arFields);
            $logPriceUpdate++;
        } else {
            CPrice::Add($arFields);
            $logPriceAdd++;
        }
        
    } else {
        $logPriceSkip++;
    }
    
    // Добавим количество на складах
    
    $storageID = false;
    $product[$articul]["COUNT"] = 1;
    $storageCount = $product[$articul]["COUNT"];
    
    // получим значения из других складов, чтобы их приплюсовать в общую сумму остатка
    
    
    if ($storageCount != $item["CATALOG_QUANTITY"]) {
        $storageCount = 1;
        $requestStorage = CCatalogStoreProduct::GetList(array(), array("PRODUCT_ID" => $item["ID"], "STORE_ID" => 1));
        if ($arrStorage = $requestStorage->Fetch()) {
            $storageID = $arrStorage["ID"];
        }
        
        $arFieldsStorage = array(
            "PRODUCT_ID" => $item["ID"],
            "STORE_ID"   => 1,
            "AMOUNT"     => $storageCount,
        );
        if ($storageID) {
            CCatalogStoreProduct::Update($storageID, $arFieldsStorage);
            CCatalogProduct::add(array("ID" => $item["ID"], "QUANTITY" => $storageCount));
            $logStorageUpdate++;
        } else {
            CCatalogStoreProduct::Add($arFieldsStorage);
            CCatalogProduct::add(array("ID" => $item["ID"], "QUANTITY" => $storageCount));
            $logStorageAdd++;
        }
        
    }
    
    //if(!empty($product[$articul]["PROPS"]["PARENT"]))
    //CIBlockElement::SetPropertyValueCode($item["ID"], "PARENT", $product[$articul]["PROPS"]["PARENT"]);
    //if(!empty($product[$articul]["PROPS"]["COLOR"]))
    //CIBlockElement::SetPropertyValueCode($item["ID"], "COLOR", $product[$articul]["PROPS"]["COLOR"]);
    //if(!empty($product[$articul]["PROPS"]["BRAND"]))
    //CIBlockElement::SetPropertyValueCode($item["ID"], "BRAND", $product[$articul]["PROPS"]["BRAND"]);
    
    CIBlockElement::SetPropertyValuesEx($item["ID"], $iblockID, $product[$articul]["PROPS"]);
    // удалить найденные артикулы из массива
    
    
    unset($product[$articul]);
    $logProductUpdate++;
    
}

// оставшиеся товары добавить как товары

foreach ($product as $item) {
    /*$MORE_PHOTO = [];
    for($s=1;$s<=5; $s++)
    {
        $MORE_PHOTO[] = CFile::MakeFileArray('/upload/product-photo/'.$item['FILE'][$s]);
    }*/
    $newFile = '/upload/product-photo/' . $item["PROPS"]['CML2_ARTICLE'] . '.jpg';
    $arLoadProductArray = array(
        "ACTIVE"            => "Y",
        "IBLOCK_ID"         => $iblockID,
        "IBLOCK_SECTION_ID" => $arIssetSection[mb_strtolower($item['SECTION'])],
        "NAME"              => $item["NAME"],
        "CODE"              => $item["ARTICUL"],
        "TAGS"              => $item["TAGS"],
        "PREVIEW_TEXT"      => $item["DESCRIPTION_PRE"],
        "DETAIL_TEXT"       => $item["DESCRIPTION"],
        "PREVIEW_PICTURE"   => CFile::MakeFileArray($newFile),
        "DETAIL_PICTURE"    => CFile::MakeFileArray($newFile),
        "PROPERTY_VALUES"   => $item["PROPS"],
    );
    
    $idElement = $blockElement->Add($arLoadProductArray);
    
    if ($idElement === false) {
        continue;
    }
    
    $arIssetElement[] = $idElement;
    /* Добавляем параметры товара к элементу каталога */
    $arproduct = array(
        "ID"           => $idElement,
        "VAT_INCLUDED" => "Y"
    );
    CCatalogProduct::Add($arproduct);
    
    
    $arFields = array(
        "PRODUCT_ID"       => $idElement,
        "CATALOG_GROUP_ID" => 1, // Базовая цена
        "PRICE"            => $item["PRICE"],
        "CURRENCY"         => "KZT",
    );
    
    // получим код ценового предложения
    $requestPrice = CPrice::GetList(array(), array("PRODUCT_ID" => $idElement, "CATALOG_GROUP_ID" => 1));
    if ($price = $requestPrice->Fetch()) {
        CPrice::Update($price["ID"], $arFields);
        $mapIdElements[$item["ARTICUL"]]["UPDATE"] = $price["ID"];
    } else {
        CPrice::Add($arFields);
    }
    
    // Добавим количество на складах
    
    $storageID = false;
    $storageCount = $item["COUNT"];
    $requestStorage = CCatalogStoreProduct::GetList(array(), array("PRODUCT_ID" => $idElement, "STORE_ID" => 1));
    if ($arrStorage = $requestStorage->Fetch()) {
        $storageID = $arrStorage["ID"];
    }
    
    $arFieldsStorage = array(
        "PRODUCT_ID" => $idElement,
        "STORE_ID"   => 1,
        "AMOUNT"     => $storageCount,
    );
    $mapIdElements[$item["ARTICUL"]] = $arFieldsStorage;
    if ($storageID) {
        CCatalogStoreProduct::Update($storageID, $arFieldsStorage);
        CCatalogProduct::add(array("ID" => $idElement, "QUANTITY" => $storageCount));
    } else {
        CCatalogStoreProduct::Add($arFieldsStorage);
        CCatalogProduct::add(array("ID" => $idElement, "QUANTITY" => $storageCount));
    }
    
    $logProductAdd++;
}
$time = microtime(true) - $startTime;
//($time);
// Сформируем ответ для ajax
$totalStep = ceil($lastRow / STEP_COUNT);
$percent = ($currentStep / $totalStep) * 100;

$output = array(
    "type"                 => "success",
    "step"                 => $currentStep,
    "total"                => $totalStep,
    "percent"              => $percent,
    "updatePhoto"          => $updatePhoto,
    "section_add"          => $addSection,
    "product_update"       => $logProductUpdate,
    "product_add"          => $logProductAdd,
    "edit_name"            => $logEditName,
    "price_update"         => $logPriceUpdate,
    "price_add"            => $logPriceAdd,
    "storage_update"       => $logStorageUpdate,
    "storage_add"          => $logStorageAdd,
    "price_skip"           => $logPriceSkip,
    "arIssetSection2"      => $arIssetSection2,
    "arIssetElement"       => $arIssetElement,
    "updateElementSection" => $updateElementSection,
    "arrElementSections"   => $arrElementSections,
);
//print_r2($arrElementSections);
file_put_contents('step.json', json_encode($output));

$endRow = true;
/*if($endRow === true)
{
    die('ok');
}*/
//if($endRow === true) {
$content = '<div>Процент: <b>' . $percent . '</b></div>';
$content .= '<div>Добавленно новых товаров: <b>' . $logProductAdd . '</b></div>';
$content .= '<div>Обновлено фото: <b>' . $updatePhoto . '</b></div>';
$content .= '<div>Обновлено привязок к разделам: <b>' . $updateElementSection . '</b></div>';
$content .= '<div>Всего обработано товаров: <b>' . $currentStep * STEP_COUNT . '</b></div>';
$content .= '<div>Добавленно новых разделов: <b>' . $addSection . '</b></div>';
$content .= '<div>Найдено разделов: <b>' . count($arIssetSection2) . '</b></div>';
$content .= '<div>Найдено товаров: <b>' . count($arIssetElement) . '</b></div>';
$content .= '<div>Добавлено цен: <b>' . $logPriceAdd . '</b></div>';
$content .= '<div>Обновлено цен: <b>' . $logPriceUpdate . '</b></div>';
//$content .= '<div>Добавлено цен: <b>'.$logPriceAdd.'</b></div>';
$content .= '<div>Пропущено цен (зафиксированы): <b>' . $logPriceSkip . '</b></div>';
echo $content;
//file_put_contents('exchange-log.txt',  $content);
//}

//echo json_encode($output);