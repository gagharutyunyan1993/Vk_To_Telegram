<?php // Конфигурация standalone приложения ВК для автоматичиского постинга


// Инструкция по получению ACCESS TOKEN
//
// Берём эту ссылку и подставляем вместо [CLIENT_ID] ID вашего приложения:
// https://oauth.vk.com/authorize?client_id=[CLIENT_ID]&display=page&redirect_uri=https://oauth.vk.com/blank.html&scope=photos,wall,offline&response_type=token&v=5.80
// Учтите, что приложение должно быть включено
define('VK_ACCESS_TOKEN', '');
define('VK_API_VERSION', '5.103'); // Версия API

define('TELEGRAM_BOT_TOKEN','');
define('TELEGRAM_CHAT_ID','');

define('DIRECTORY', dirname(__FILE__) . '/'); // Директория из-под которой работаем
define('DIRECTORY_WATERMARK', DIRECTORY . 'watermark/watermark.png'); // Водяной знак (лого группы)

define('DB_FILE', DIRECTORY . 'posts.db'); // Файл БД
define('DB_NAME', 'posts'); // Таблица БД

define('LOG_FILE', DIRECTORY . 'error.log'); // Файл для записи логов

define('TIMEOUT', '90'); // Таймаут на выполнение скрипта

define('STOP_SEARCH_AFTER_FAILURE', True); // Останавливать выполнение скрипта, если не удалось найти пост после первой попытки

// Парсер выбирает случайный пост, в диапазоне от SEARCH_RANGE_START до SEARCH_RANGE_END
define('SEARCH_RANGE_START', 1); // С какой позиции начинать искать посты
define('SEARCH_RANGE_END', 5); // До какой позиции искать посты

define('WATERMARK_ACTIVE', false); // Накладывать водяной знак или нет
// Позиционирование водяного знака (в процентах). Если у оси указано false, то берётся середина этой оси
define('WATERMARK_X', false); // По горизонтали
define('WATERMARK_Y', false); // По вертикали

define('ONLY_CRON', false); // Разрешать запуск скрипта не только через CRONTAB

define('ADD_COPYRIGHT', false); // Указание источника (защита контента Немезида https://vk.com/@adminsclub-citation)

// Группы, из которых берутся записи
$groups = array(
    '',
);

// Чёрный список слов, которые не должны содержаться в тексте поста
$blacklist = array(
    '',
);

// Для автозапуска через CRON можно воспользоваться следующим кодом:
// 0 * * * * /opt/php5.6/bin/php /path/to/file/parser.php
