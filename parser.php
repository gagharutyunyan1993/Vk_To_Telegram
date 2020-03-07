<?php

/*
 * Класс для автоматического постинга всего в паблик ВК
 * Автор - Антон Тройнин (@kernelpicnic). Все права защищены и т.д. и т.п.
 */
include 'Telegram.php';
include 'config.php';
class VKparser
{
    function __construct() {
        if (!is_file('config.php')) {
            $this->log('Файл с настройками не найден - остановка');

            die('Файл с настройками не найден. Вероятно, у вас есть example.config.php. ' .
                'Заполните его своими данными и переименуйте в config.php.');
        }

        include 'config.php';   // Конфигурация скрипта
        include 'vk.php'; // Класс для взаимодействия с API вконтакте

        if (!VK_ACCESS_TOKEN) {
            $this->log('Не указан "VK_ACCESS_TOKEN" в настройках');

            exit;
        }

        if (!VK_API_VERSION) {
            $this->log('Не указан "VK_API_VERSION" в настройках');

            exit;
        }

        if (!TELEGRAM_BOT_TOKEN) {
            $this->log('Не указан "TELEGRAM_BOT_TOKEN" в настройках');

            exit;
        }

        if (!TELEGRAM_CHAT_ID) {
            $this->log('Не указан "TELEGRAM_CHAT_ID" в настройках');

            exit;
        }

        // Открытие этого файла только cron'ом
        if (ONLY_CRON && (!isset($_SERVER['argv'][0]) && $_SERVER['argv'][0] != '--cron')) {
            $this->log('Скрипт запущен не через CRON - остановка');

            exit;
        }

        // Директория для информационных логов
        if (!file_exists('logs/')) {
            mkdir('logs/', 0777, true);
        }

        // Отображаем все ошибки
        error_reporting(E_ALL);
        ini_set('display_errors', TRUE);
        // Логгирование
        ini_set('log_errors', 1);
        ini_set('error_log', LOG_FILE);
        // Лимит выполнения скрипта по времени
        set_time_limit(TIMEOUT);

        // Подключаемся к SQLite. Если БД не существует, то создаём её
        $this->db = new SQLite3(DB_FILE);
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS " . DB_NAME . " (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `group` TEXT,
                `message` TEXT,
                `attachment` TEXT,
                `post_id` TEXT
            )
        ");

        // Инициализируем класс работы с API
        $this->vk = new vk(VK_ACCESS_TOKEN, VK_API_VERSION);
        $this->owner = '-' . $groups[array_rand($groups)];
        $this->blacklist = $blacklist;
        $this->Telegram = new Telegram(TELEGRAM_BOT_TOKEN);
        $this->log('Скрипт успешно запущен');
    }

    /**
     * Получаем случайный пост из одной из доступных групп, указанных в настройках
     *
     * @return array возвращаем всю обработанную информацию о посте или же
     * false, если нечего не было найдено или произошла ошибка
     */
    public function get_post() {
        $post = $this->vk->method('wall.get', array(
            'owner_id' => $this->owner, // Случайная группа из списка
            'offset'   => rand(SEARCH_RANGE_START, SEARCH_RANGE_END), // Поиск поста в определённом диапазоне
            'count'    => '1'
        ));

        if ($post->response->items) {
            $this->log('Пост найден');

            $post = $post->response->items[0];

            // Если тип поста copy или в тексте есть ссылки, то
            // скорее всего это рекламный пост - постить не будем
            if ($post->post_type === 'copy'
                || preg_match('/(http:\/\/[^\s]+)/', $post->text)
                || preg_match('/\[club(.*)]/', $post->text))
            {
                $this->log('Имеется подозрение на рекламу — пропуск');

                return false;
            }

            if (count($this->blacklist) > 0 && trim($this->blacklist[0])) {
                foreach($this->blacklist as $word) {
                    if (strpos(mb_strtolower($post->text, 'UTF-8'), mb_strtolower($word, 'UTF-8')) !== false) {
                        $this->log('В тексте поста найдено слово из чёрного списка ("' . $word . '") — пропуск');

                        return false;
                    }
                }
            }

            $this->log('Начинается обработка');

            return $this->process_post($post);
        } else {
            $this->log('Пост не найден');

            return false;
        }
    }

    /**
     * Обработка - убираем ненужную информацию, сохраняем изображения, накладываем
     * водяной знак и другие полезные процедуры
     *
     * @param  $post необработанный пост
     * @return array обработанный пост
     */
    private function process_post($post) {
        $output = new stdClass();
        // Химичим с текстом, чтобы убрать все теги <br>
        // Двойные кавычки не для красоты "\n" (!)
        $output->text = preg_replace('#<br\s*?/?>#i', "\n", $post->text);
        $output->attach = '';
        $output->post_id = $post->id;
        /*var_dump($output->post_date);
        var_dump($this->check_hash($post->date));*/
        if($this->check_hash($output->post_id) == FALSE ){
            die();
        }

        if (ADD_COPYRIGHT) {
            $output->copyright = 'https://vk.com/wall' . $post->owner_id . '_' . $post->id;
        } else {
            $output->copyright = false;
        }

        // Проверка на наличие прикреплений
        // Собираем их все в одну переменную
        if (isset($post->attachments)) {
            foreach ($post->attachments as $item) {
                if (isset($item->photo)) {
                    // Сохраняем картинку локально, выбирая самую большую
                    $this->grab_image(end($item->photo->sizes)->url);


                    // Накладываем водяной знак, если разрешено в настройках
                    if (WATERMARK_ACTIVE) {
                        $this->apply_watermark(DIRECTORY . 'watermark.png');
                    }
                    $output->attach = DIRECTORY . 'image.jpg';
                }
            }

            $this->log('Пост успешно обработан');
            return $output;
        } else {
            $this->log('Не найдено ни одного прикрепления');
        }
    }

    /**
     * Отправка полностью готового поста ВК в группу по указанному VK_GROUP_ID
     *
     * @param  array $data - массив с данными о посте
     */
    public function send_post($data) {

        $response = $this->Telegram->sendPhoto($data->attach, $data->text);

        if (!isset($response->error)) {
            // Сохраняем в БД
            $this->db->exec("INSERT INTO " . DB_NAME . " ('group', 'message', 'attachment', 'post_id')
                VALUES ('$this->owner', '$data->text', '$data->attach','$data->post_id')
            ");

            $this->log('Пост успешно отправлен');

            return true;
        } else {
            $this->log('Ошибка отправки поста: ' . $response->error->error_msg);

            return false;
        }

    }

    /**
     * Проверка документа на существование в БД. Если есть, значит такой документ уже
     * был добавлен ранее. Следовательно добавлять его не нужно.
     *
     * @param  string строка или путь к файлу
     * @param  boolean это файл или нет - влияет на используемую хеш-функцию
     * @return any
     */

    private function check_hash($post_id) {

        $rows = $this->db->querySingle("
            SELECT COUNT(*) FROM " . DB_NAME . " WHERE post_id='$post_id'
        ");

        if ($rows !== 0) {
            $this->log('Такой документ уже был добавлен ранее — пропуск');

            return false;
        } else {
            return True;
        }
    }

    /**
     * Получение документа с помощью cURL
     *
     * @param  $url URL документа
     * @return any полученный документ
     */
    private function grab_image($url) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

        $raw = curl_exec($ch);
        curl_close($ch);

        if (file_exists(DIRECTORY . 'image.jpg')) {
            unlink(DIRECTORY . 'image.jpg');
        }

        $fp = fopen(DIRECTORY . 'image.jpg', 'x');

        fwrite($fp, $raw);
        fclose($fp);
    }

    /**
     * Логгирование с записью в файл
     *
     * @param  string сообщение для записи в лог
     */

    private function grab_video(){}

    public function log($message) {
        file_put_contents('./logs/' . date('d-m-Y') . '.txt', '[' . date('d-m-Y h:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * Наложение водяного знака на изображение
     *
     * @param  $img_file используемое изображение
     * @param  $filetype получаемое расширение на выходе
     * @param  $watermark изображение водяного знака
     */
    private function apply_watermark($img_file, $filetype = 'jpg', $watermark = DIRECTORY_WATERMARK) {
        // Размеры картинки
        $image   = GetImageSize($img_file);
        $xImg    = $image[0];
        $yImg    = $image[1];

        // Размеры водяного знака
        $offset  = GetImageSize($watermark);

        // Позиционирование по горизонтали
        if (WATERMARK_X) {
            $xOffset = $image[0] * (WATERMARK_X / 100) - $offset[0]/2;
        } else {
            $xOffset = $image[0]/2 - $offset[0]/2;
        }

        // Позиционирование по вертикали
        if (WATERMARK_Y) {
            $yOffset = $image[1] * (WATERMARK_Y / 100) - $offset[1]/2;
        } else {
            $yOffset = $image[1]/2 - $offset[1]/2;
        }

        // Формат картинки
        switch ($image[2]) {
            case 1:
                $img = imagecreatefromgif($img_file);
                break;

            case 2:
                $img = imagecreatefromjpeg($img_file);
                break;

            case 3:
                $img = imagecreatefrompng($img_file);
                break;
        }

        $r     = imagecreatefrompng($watermark);
        $x     = imagesx($r);
        $y     = imagesy($r);
        $xDest = $xImg - ($x + $xOffset);
        $yDest = $yImg - ($y + $yOffset);

        imageAlphaBlending($img,1);
        imageAlphaBlending($r,1);
        imagesavealpha($img,1);
        imagesavealpha($r,1);
        imagecopyresampled($img, $r, $xDest, $yDest, 0, 0, $x, $y, $x, $y);

        switch ($filetype) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($img, $img_file, 100);
                imagejpeg($img, $img_file, 100);
                break;

            case 'gif':
                imagegif($img, $img_file);
                break;

            case 'png':
                imagepng($img, $img_file);
                break;
        }

        imagedestroy($r);
        imagedestroy($img);

        $this->log('Водяной знак успешно добавлен');
    }
}

$vkparser = new VKparser();

$post_info = '';

do {
    $post_info = $vkparser->get_post();

    sleep(5); // Пауза между получением нового поста
} while (!$post_info && !STOP_SEARCH_AFTER_FAILURE);

if ($post_info) {
    $vkparser->send_post($post_info);
} else {
    $vkparser->log('Пост не найден, включен параметр "STOP_SEARCH_AFTER_FAILURE" - завершение работы парсера');
}
