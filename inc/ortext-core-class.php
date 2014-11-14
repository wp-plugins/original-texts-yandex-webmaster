<?php

/**
 * Базовый Класс для плагина Оригинальные тексты Яндекс
 */
class OrTextBase {

    const NAME_PLUGIN = 'Original Texts Yandex';
    const PATCH_PLUGIN = 'original-texts-yandex-webmaster'; //Директория плагина
    const URL_ADMIN_MENU_PLUGIN = 'ortext-yandex-page'; //Адрес в админке
    const OPTIONS_NAME_PAGE = 'ortext-yandex-opt.php'; //страница опций плагина
    const NAME_TITLE_PLUGIN_PAGE = 'Оригинальные тексты Яндекс'; // Название титульной страницы плагина
    const NAME_MENU_OPTIONS_PAGE = 'OriginalTextYa'; // Название пунтка меню
    const NAME_SERVIC_ORIGINAL_TEXT='Оригинальные тексты Yandex Webmaster';

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
    }

    /**
     * Активация фишек
     */
    public function addActios() {
        add_action('admin_menu', array($this, 'adminOptions'));
        add_action('add_meta_boxes', array($this, 'settingMetabos')); //Добавляем метабокс в пост
        add_action('save_post', array($this, 'metaboxSavePost')); //Сохранение галки в постах
        add_action('save_post', array($this, 'metaboxSentYandex')); //Отправка значений в яндекс
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
        add_meta_box('ortext-metabox', OrTextBase::NAME_SERVIC_ORIGINAL_TEXT, array($this, 'metabosHtml'), 'post', 'side', 'high');

    }

    /**
     * Отрисовка МетаБокса
     */
    public function metabosHtml($post) {
        $ortext_yasent = get_option('ortext_yasent'); // настройка для публикаций по умолчанию
        // Используем nonce для верификации
        wp_nonce_field(plugin_basename(__FILE__), 'ortext_noncename');
        // Поля формы для введения данных
        if (empty($ortext_yasent)) {
            if (get_post_meta($post->ID, '_ortext_meta_value_key', true) == 'on') {
                $cheked = 'checked';
            } else {
                $cheked = '';
            }
        } elseif (!empty($ortext_yasent)) {
            $cheked = 'checked';
        }

        echo '<input type="checkbox" name="ortext_new_field" ' . $cheked . '/>';
        echo '<span class="description">Добавлять текст в сервис '.OrTextBase::NAME_SERVIC_ORIGINAL_TEXT.' при сохранение?</span>';
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


        $data = $_POST['ortext_new_field'];
        //$data = 'off';
        //Обновление данных в базе даннхы
        update_post_meta($post_id, '_ortext_meta_value_key', $data);
    }

    /**
     * Отправка данных в Яндекс по галке
     */
    public function metaboxSentYandex($post_id) {

        //$postId = intval($_REQUEST['postId']);
        $postData = get_post($post_id);
        $title=$postData->post_title;
        $text = $postData->post_content;

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
        //if (!isset($_POST['ortext_new_field']))
        //    return;
        $chek = get_post_meta($post_id, '_ortext_meta_value_key', true);
        //wp_mail('admin@b2motor.ru', "Ппалигн", $text . " ----  " . $aa);
        if ($chek == 'on') {
            //$cheked = 'checked';
            //wp_mail('admin@b2motor.ru', "Ппалигн", $text . " ----  " . $chek);
           $status_sent=$ortextfun->sendTextOriginal2($text); //Отправка текста
           $ortextfun->logJornal($post_id, $title, $status_sent); //Логируем результаты
        } else {
            return;
        }

        return;
    }

}
