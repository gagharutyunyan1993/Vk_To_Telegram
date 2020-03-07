# Парсинг контента ВКонтакте

PHP парсер, который позволяет брать разные посты из групп ВК и добавлять в свой Телеграм канал.

## Перед началом работы
Прежде всего проверьте, включен ли SQLite3 (http://php.net/manual/ru/sqlite3.installation.php) и cURL (https://stackoverflow.com/questions/1347146/how-to-enable-curl-in-php-xampp). Настройка может отличаться в зависимости от ОС, условий запуска (хостинг) и т.д.

## Настройка
Для начала работы необходимо открыть config.php и сделать следующее:

1. Указать `VK_GROUP_ID` - ID своей группы;
2. Указать `TELEGRAM_BOT_TOKEN` - Токен своего Телеграм бота; (Как получить Токен бота [здесь](https://romua1d.ru/kak-poluchit-token-bota-telegram-api/))
3. Добавить бота в свой телеграм канал.
4. Указать `VK_GROUP_ID` - ID своей группы; (ID телеграм канала можно получить с помощью бота [здесь](https://telegram.me/myidbot/) заранее добавить его админом канала)
5. Получить `VK_ACCESS_TOKEN`. В настройках (`config.php`) описана инструкция, как это сделать. Приложения создаются по ссылке [здесь](https://vk.com/apps?act=manage);
6. Перечислите группы, из которых будет забираться контент в массиве `$groups`;
7. Запустите скрипт через браузер или CRON (есть также опция - разрешать запуск не через CRON, или нет). Пример команды для CRON указан в том же файле настроек.

Готово. Теперь посты из других групп будут добавляться на ваш Телеграм канал. В файле настроек (`config.php`) есть ещё много различных опций с описанием их работы. Вы можете изменить их под ваши нужды.
