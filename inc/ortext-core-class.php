<?php

/**
 * Базовый Класс для плагина Оригинальные тексты Яндекс
 */
class OrTextBase {

    const NAME_PLUGIN = 'Original Texts Yandex';
    const PATCH_PLUGIN = 'original-texts-yandex-webmaster'; //Директория плагина
    const URL_ADMIN_MENU_PLUGIN = 'ortext-yandex-page'; //Адрес в админке
    const OPTIONS_NAME_PAGE = 'page/option1.php'; //страница опций плагина
    const NAME_TITLE_PLUGIN_PAGE = 'Оригинальные тексты Яндекс'; // Название титульной страницы плагина
    const NAME_MENU_OPTIONS_PAGE = 'OriginalTextYa'; // Название пунтка меню
    const NAME_SERVIC_ORIGINAL_TEXT = 'Оригинальные тексты Yandex Webmaster';
    const URL_PLUGIN_CONTROL = 'options-general.php?page=ortext-yandex-page'; //Адрес админки плагина полный

    /**
     * Констурктора класса
     */

    public function __construct() {
        $this->addActios();
        $this->addOptions();
    }

    /**
     * Опции вызываемые деактивацией
     */
    public function deactivationPlugin() {
        delete_option('ortext_id');
        delete_option('ortext_passwd');
        delete_option('ortext_token');
        delete_option('ortext_token_key');
        delete_option('ortext_token_time');
        delete_option('ortext_loadsite');
        delete_option('ortext_yasent');
        delete_option('ortext_jornal');
        delete_option('ortext_posttype'); //Типы выбранных записей
        delete_option('ortextprol');
        wp_clear_scheduled_hook('ortextya_cron');
    }

    /**
     * Активация фишек
     */
    public function addActios() {
        add_action('admin_menu', array($this, 'adminOptions'));
        add_action('add_meta_boxes', array($this, 'settingMetabos')); //Добавляем метабокс в пост
        add_action('save_post', array($this, 'metaboxSavePost')); //Сохранение галки в постах
        add_action('save_post', array($this, 'metaboxSentYandex')); //Отправка значений в яндекс
        //cron
        add_filter('cron_schedules', array($this, 'cronTimeList')); //Список своих крон, время, периоды
        //add_action('wp', array($this, 'addCronWP')); //Регистрация крон хука
        if (!wp_next_scheduled('ortextya_cron')) {
            wp_schedule_event(time(), 'fri_day', 'ortextya_cron');
        }
        add_action('ortextya_cron', array($this, 'sendEmailToken')); //Добавляем к хуку выполнение функции


        add_filter('plugin_action_links', array($this, 'pluginLinkSetting'), 10, 2); //Настройка на странице плагинов
    }

    /**
     * Добавление опций в базу данных
     */
    public function addOptions() {
        add_option('ortext_id', $value = '', $deprecated = '', $autoload = 'yes');
        add_option('ortext_passwd', $value = '', $deprecated = '', $autoload = 'yes');
        add_option('ortext_token', ''); //Код токена
        add_option('ortext_token_key', $value = '', $deprecated = '', $autoload = 'yes'); // Токен яндекса
        add_option('ortext_token_time', $value = '', $deprecated = '', $autoload = 'yes'); //Время жизни токена
        add_option('ortext_loadsite', $value = '', $deprecated = '', $autoload = 'yes'); //Текущей загруженный проект
        add_option('ortext_yasent', $value = '', $deprecated = '', $autoload = 'yes'); //Опция для установки галочки в записях о отправки в яндекс
        add_option('ortext_jornal', array()); //Массив с журналом
        add_option('ortext_posttype', array('post' => 'post')); //Типы выбранных записей
        add_option('ortextprol');
    }

    /**
     * Добавляет пункт настроек на странице активированных плагинов
     */
    public function pluginLinkSetting($links, $file) {
        $this_plugin = self::PATCH_PLUGIN . '/index-ortext-yandex.php';
        if ($file == $this_plugin) {
            $settings_link1 = '<a href="options-general.php?page=ortext-yandex-page">' . __("Settings", "default") . '</a>';
            array_unshift($links, $settings_link1);
        }
        return $links;
    }

    /**
     * Параметры активируемого меню
     */
    public function adminOptions() {
        $page_option = add_options_page(self::NAME_TITLE_PLUGIN_PAGE, self::NAME_MENU_OPTIONS_PAGE, 8, self::URL_ADMIN_MENU_PLUGIN, array($this, 'showSettingPage'));
        add_action('admin_print_styles-' . $page_option, array($this, 'syleScriptAddpage')); //загружаем стили только для страницы плагина
    }

    /**
     * Стили, скрипты
     */
    public function syleScriptAddpage() {
        wp_register_script('ortext_bootstrapjs1', plugins_url() . '/' . self::PATCH_PLUGIN . '/' . 'bootstrap/js/bootstrap.js');
        wp_enqueue_script('ortext_bootstrapjs1');
        wp_register_style('ortext_bootstrapcss1', plugins_url() . '/' . self::PATCH_PLUGIN . '/' . 'bootstrap/css/bootstrap.css');
        wp_enqueue_style('ortext_bootstrapcss1');
        wp_register_style('ortext_adminpagecss', plugins_url() . '/' . self::PATCH_PLUGIN . '/' . 'css/adminpag.css');
        wp_enqueue_style('ortext_adminpagecss');
    }

    /**
     * Страница меню
     */
    public function showSettingPage() {
        include_once WP_PLUGIN_DIR . '/' . self::PATCH_PLUGIN . '/' . self::OPTIONS_NAME_PAGE;
    }

    /**
     * Метабокс в записи
     */
    public function settingMetabos() {
        $array_posts = get_option('ortext_posttype'); //Типы постов
        foreach ($array_posts as $k => $v) {
            add_meta_box('ortext-metabox', OrTextBase::NAME_SERVIC_ORIGINAL_TEXT, array($this, 'metabosHtml'), "$v", 'side', 'high');
        }
    }

    /**
     * Отрисовка МетаБокса
     */
    public function metabosHtml($post) {
        $ortext_yasent = get_option('ortext_yasent'); // настройка для публикаций по умолчанию
        $ortextprol = get_option('ortextprol');
// Используем nonce для верификации
        wp_nonce_field(plugin_basename(__FILE__), 'ortext_noncename');
// Поля формы для введения данных
        if (empty($ortext_yasent)) {
            if (get_post_meta($post->ID, '_ortext_meta_value_key', true) == 'on') {
                $cheked0 = 'checked';
            } else {
                $cheked0 = '';
            }
        } elseif (!empty($ortext_yasent)) {
            $cheked0 = 'checked';
        }
        if (!empty($ortextprol['ck_reg1'])) {
            if (get_post_meta($post->ID, '_ortext_meta_value_key_reg1', true) == 'on') {
                $cheked1 = 'checked';
            } else {
                $cheked1 = '';
            }
        }
        if (!empty($ortextprol['ck_reg2'])) {
            if (get_post_meta($post->ID, '_ortext_meta_value_key_reg2', true) == 'on') {
                $cheked2 = 'checked';
            } else {
                $cheked2 = '';
            }
        }
        if (!empty($ortextprol['ck_reg3'])) {
            if (get_post_meta($post->ID, '_ortext_meta_value_key_reg3', true) == 'on') {
                $cheked3 = 'checked';
            } else {
                $cheked3 = '';
            }
        }
        if (!empty($ortextprol['ck_reg4'])) {
            if (get_post_meta($post->ID, '_ortext_meta_value_key_reg4', true) == 'on') {
                $cheked4 = 'checked';
            } else {
                $cheked4 = '';
            }
        }

        echo '<input type="checkbox" name="ortext_new_field" ' . $cheked0 . '/>';
        echo '<span class="description">Добавлять текст в сервис ' . OrTextBase::NAME_SERVIC_ORIGINAL_TEXT . ' при сохранение?</span>';
        echo '</br>';
        if (!empty($ortextprol['ck_reg1'])) {
            echo '<input type="checkbox" name="ortext_new_field_reg1" ' . $cheked1 . '/>';
            echo '<span class="description">' . $ortextprol['namereg1'] . '</span>';
            echo '</br>';
        }
        if (!empty($ortextprol['ck_reg2'])) {
            echo '<input type="checkbox" name="ortext_new_field_reg2" ' . $cheked2 . '/>';
            echo '<span class="description">' . $ortextprol['namereg2'] . '</span>';
            echo '</br>';
        }
        if (!empty($ortextprol['ck_reg3'])) {
            echo '<input type="checkbox" name="ortext_new_field_reg3" ' . $cheked3 . '/>';
            echo '<span class="description">' . $ortextprol['namereg3'] . '</span>';
            echo '</br>';
        }
        if (!empty($ortextprol['ck_reg4'])) {
            echo '<input type="checkbox" name="ortext_new_field_reg4" ' . $cheked4 . '/>';
            echo '<span class="description">' . $ortextprol['namereg4'] . '</span>';
            echo '</br>';
        }
    }

    /**
     * Сохранение данных Метабокса при сохрание записи
     */
    public function metaboxSavePost($post_id) {

// проверяем nonce нашей страницы, потому что save_post может быть вызван с другого места.
        if (!wp_verify_nonce($_POST['ortext_noncename'], plugin_basename(__FILE__)))
            return $post_id;

// проверяем, если это автосохранение ничего не делаем с данными нашей формы.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return $post_id;

// проверяем разрешено ли пользователю указывать эти данные
        if (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }


// Убедимся что поле установлено.
//        if (!isset($_POST['ortext_new_field']))
//            return;


        $data0 = $_POST['ortext_new_field'];
        $data1 = $_POST['ortext_new_field_reg1'];
        $data2 = $_POST['ortext_new_field_reg2'];
        $data3 = $_POST['ortext_new_field_reg3'];
        $data4 = $_POST['ortext_new_field_reg4'];
//Обновление данных в базе даннхы
        update_post_meta($post_id, '_ortext_meta_value_key', $data0);
        update_post_meta($post_id, '_ortext_meta_value_key_reg1', $data1);
        update_post_meta($post_id, '_ortext_meta_value_key_reg2', $data2);
        update_post_meta($post_id, '_ortext_meta_value_key_reg3', $data3);
        update_post_meta($post_id, '_ortext_meta_value_key_reg4', $data4);
    }

    /**
     * Отправка данных в Яндекс по галке
     */
    public function metaboxSentYandex($post_id) {

//$postId = intval($_REQUEST['postId']);
        $postData = get_post($post_id);
        $title = $postData->post_title;
        $textNostrip = strip_tags($postData->post_content);
        $text = htmlspecialchars($textNostrip);
        $post_type = $postData->post_type;
        $ortextprol = get_option('ortextprol');
        $array_preg = array();
        $array_replace = array();
        $ortextfun = new OrTextFunc;

        if (!wp_verify_nonce($_POST['ortext_noncename'], plugin_basename(__FILE__)))
            return $post_id;

// проверяем, если это автосохранение ничего не делаем с данными нашей формы.

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return $post_id;

// проверяем разрешено ли пользователю указывать эти данные
        if (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }


// Убедимся что поле установлено.
        $chek0 = get_post_meta($post_id, '_ortext_meta_value_key', true);
        $chek1 = get_post_meta($post_id, '_ortext_meta_value_key_reg1', true);
        $chek2 = get_post_meta($post_id, '_ortext_meta_value_key_reg2', true);
        $chek3 = get_post_meta($post_id, '_ortext_meta_value_key_reg3', true);
        $chek4 = get_post_meta($post_id, '_ortext_meta_value_key_reg4', true);
        if ($chek1 == 'on') {
            array_push($array_preg, $ortextprol['reg1']);
            array_push($array_replace, "");
        }
        if ($chek2 == 'on') {
            array_push($array_preg, $ortextprol['reg2']);
            array_push($array_replace, "");
        }
        if ($chek3 == 'on') {
            array_push($array_preg, $ortextprol['reg3']);
            array_push($array_replace, "");
        }
        if ($chek4 == 'on') {
            array_push($array_preg, $ortextprol['reg4']);
            array_push($array_replace, "");
        }

        if ($chek0 == 'on') {
            if (!empty($array_preg)) {
                $text_reg1 = preg_replace($array_preg, $array_replace, $text);
                //echo $text_reg1;
                //exit();
                $status_sent = $ortextfun->sendTextOriginal2($text_reg1); //Отправка текста
                $ortextfun->logJornal($post_id, $title, $status_sent, $post_type); //Логируем результаты
            } else {
                $status_sent = $ortextfun->sendTextOriginal2($text); //Отправка текста
                $ortextfun->logJornal($post_id, $title, $status_sent, $post_type); //Логируем результаты
            }
        } else {
            return;
        }


        return;
    }

    /**
     * Активная вкладка в админпанели плагина
     * @return string css Класс для активной вкладки
     */
    static public function adminActiveTab($tab_name = null, $tab = null) {

        if (isset($_GET['tab']) && !$tab)
            $tab = $_GET['tab'];
        else
            $tab = 'general';

        $output = '';
        if (isset($tab_name) && $tab_name) {
            if ($tab_name == $tab)
                $output = ' nav-tab-active';
        }
        echo $output;
    }

    /**
     * Подключает нужную страницу исходя из вкладки на страницы настроек плагина
     * @result include_once tab{номер вкладки}-option1.php
     */
    static public function tabViwer() {
        $tab = $_GET['tab'];
        switch ($tab) {
            case 'project':
                include_once WP_PLUGIN_DIR . '/' . self::PATCH_PLUGIN . '/page/tab2-option1.php';
                break;
            case 'jornal':
                include_once WP_PLUGIN_DIR . '/' . self::PATCH_PLUGIN . '/page/tab3-option1.php';
                break;
            case 'about':
                include_once WP_PLUGIN_DIR . '/' . self::PATCH_PLUGIN . '/page/tab4-option1.php';
                break;
            case 'help':
                include_once WP_PLUGIN_DIR . '/' . self::PATCH_PLUGIN . '/page/tab5-option1.php';
                break;
            case 'progeneral':
                include_once WP_PLUGIN_DIR . '/' . self::PATCH_PLUGIN . '/page/tab6-option1.php';
                break;

            default :
                include_once WP_PLUGIN_DIR . '/' . self::PATCH_PLUGIN . '/page/tab1-option1.php';
        }
    }

    /**
     * Отправляет Email сообщение
     * 
     */
    public function sendEmailToken() {
        $ortextprol = get_option('ortextprol');
        $site = get_bloginfo('name');
        $message.="Срок вашего токена Яндекс скоро заканчивается, пожалуйста получите новый токен. В противном случае плагин «Оригинальные тексты Яндекс» не будет работать. ";
        $message.='The term of your token Yandex ends soon, please obtain a new token. Otherwise the plugin "Original texts Yandex" will not work.';
        $tek_data = date('d-m-Y'); //Тукущая дата, нужна для проверки
        $ortext_token_time = get_option('ortext_token_time'); //Время жизни токена
        $temp_off_token = time() + ($ortext_token_time);

        $dateoff_token = date('d-m-Y', $temp_off_token); // Дата окончания токена в человеческом виде
        if (strtotime($tek_data) >= strtotime("$dateoff_token - 15 day")) {
            if ($ortextprol['ck_email'] == 'on') {
                wp_mail($ortextprol['email'], $site, $message);
            }
        }
    }

    /**
     * Cron периоды, служит для добавления своих итервалов в WP
     */
    public function cronTimeList($schedules) {
        $schedules['fri_day'] = array(
            'interval' => 259200,
            'display' => 'Каждые 72 часа'
        );
        return $schedules;
    }

//    /**
//     * Добавляет задание в WP
//     */
//    public function addCronWP() {
//        if (!wp_next_scheduled('ortextya_cron')) {
//            wp_schedule_event(time(), 'fri_day', 'ortextya_cron');
//        }
//    }
}
