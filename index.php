<?

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/fileman/prolog.php");
//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
//$APPLICATION->SetTitle("Обновление товаров");
$APPLICATION->SetTitle("Импорт товаров excel");
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>
<? if($_GET['type'] == false or $_GET['type'] == 'error'):?>
<?php if($_GET['type'] == 'error') echo 'Неверный формат фаила';?>
<div class="adm-detail-content-item-block">
    <table class="adm-detail-content-table edit-table" id="edit1_edit_table">
        <tbody>
        <tr>
            <td>
                <form enctype="multipart/form-data" action="send-file.php" method="POST">
                    <!-- Поле MAX_FILE_SIZE должно быть указано до поля загрузки файла -->
                    <input type="hidden" name="MAX_FILE_SIZE" value="30000000" class="adm-btn" />
                    <!-- Название элемента input определяет имя в массиве $_FILES -->
                    Отправить этот файл: <input name="userfile" type="file" />
                    <input type="submit" value="Отправить файл" class="adm-btn" />
                </form>
                <p>
                <a href="/import/catalog_example.xlsx">Скачать пример фаила</a>
                </p>
            </td>
        </tr>

        </tbody>
    </table>
</div>

<?php elseif($_GET['type'] == 'uploaded'):?>
<div class="stop">
<script>
    $(document).ready(function () {
        var next = true;
       var myVar = setInterval(function() {
           if(next == true) {
               next = false;
               $.get('ajax.php', function (data) {
                   if (data == 'ok') {
                       $('.stop').html('');
                       $('.result').html('Выгрузка закончена');
                       //$('.result').html(data);
                       clearInterval(myVar);
                   } else {
                       next = true;
                       $('.result').html(data);
                   }

               });
           }


       }, 1000);

    })
</script>
</div>
<div class="result">
    Выгрузка началась. Дождитесь окончания
</div>
<?php endif;?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");?>
<?//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
