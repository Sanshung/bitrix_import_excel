<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$uploaddir = $_SERVER["DOCUMENT_ROOT"].'/import/';
$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);
print_r($_FILES['userfile']);
if($_FILES['userfile']['type'] == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
{
    if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
        rename (basename($_FILES['userfile']['name']), 'catalog.xlsx');
        rename ('step.json', 'old_step.json');

        echo "Файл корректен и был успешно загружен.\n";
        LocalRedirect('/import/?type=uploaded');
    } else {
        LocalRedirect('/import/?type=error');
    }
}
else {
    LocalRedirect('/import/?type=error');
}
