<script type="text/javascript">

    jQuery("document").ready(function () {


        jQuery("#toltipstatus").tooltip({placement: 'bottom'});


    });


</script>	


<h2>Журнал работы плагина</h2>
<?php
$plugins_url = admin_url() . 'options-general.php?page=' . OrTextBase::URL_ADMIN_MENU_PLUGIN; //URL страницы плагина
$ortextfun = new OrTextFunc();
$jornalarray = get_option('ortext_jornal');


if (isset($_GET['clearjornal'])) {
    update_option('ortext_jornal', array());
    ?>
    <script type = "text/javascript">
        document.location.href = "<?php echo $plugins_url; ?>";
    </script>
    <?php
}
?>
<a class="btn btn-primary" href="<?php echo $plugins_url . '&clearjornal'; ?>">Очистить журнал</a>
<span class="glyphicon glyphicon-exclamation-sign btn btn-info btn-sm" id="toltipstatus" data-toggle="tooltip" title="201 - Текст добавлен. 409 - Текст уже был добавлен ранее. 500 - Ошибка на стороне Яндекс. 777 - Не известная ошибка, обратитесь к разработчику плагина">Типы ошибок</span>

<table class="table table-bordered table-hover table-condensed">
    <thead>
        <tr>
            <th>Дата и время добавления</th> 
            <th>Номер записи (id поста по Wordpress)</th>
            <th>Заголовок записи</th>
            <th>Статус добавления</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($jornalarray as $jornalprint) { ?>
            <tr class="<?php
            switch (trim($jornalprint['status'])) {
                case 201: echo 'success';
                    break;
                case 409: echo 'info';
                    break;
                case 500: echo 'warning';
                    break;
                case 777: echo 'danger';
                    break;
            }
            ?>">
                <th><?php echo $jornalprint['time']; ?></th>
                <th><?php echo $jornalprint['idpost']; ?></th>
                <th><?php echo $jornalprint['title']; ?></th>
                <th><?php echo $jornalprint['status']; ?></th>
            </tr>
        <?php } ?>
    </tbody>



</table>