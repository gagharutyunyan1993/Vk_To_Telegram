<?php


class Telegram
{
    private $token;
    private $url = 'https://api.telegram.org/bot';
    private $chatId = TELEGRAM_CHAT_ID; // Default chat id

    public function __construct($token) {
        $this->token = $token;
    }

    /**
     * Make url
     *
     * @param $method
     * @param array $params
     * @return string
     */
    private function makeUrl($method, $params = [])
    {
        $url = $this->url . $this->token . "/$method";
        if (!isset($params["chat_id"])) {
            $params["chat_id"] = $this->chatId;
        }

        return $url . "?" . http_build_query($params);
    }

    /**
     * Request telegram
     *
     * @param $method
     * @param array $params
     * @param bool $isFile
     * @return mixed
     */
    private function Request($method, $params = [], $isFile = false) {
        $get = $params['GET'] ?? [];
        $post = $params['POST'] ?? [];

        $url = $this->makeUrl($method, $get);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (!empty($post)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        if ($isFile) {
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
        }

        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result);

    }

    /**
     * Send simple text message
     *
     * @param $text
     * @return mixed
     */
    public function sendMessage($text)
    {
        return $this->Request("sendMessage", [
            "GET" => [
                "text" => $text
            ]
        ]);
    }

    /**
     * Send message with photo
     *
     * @param $photo
     * @param string $caption
     * @return mixed
     */
    public function sendPhoto($photo, $caption = ""){
        $data = $this->Request("sendPhoto", [
            "GET" => [
                "caption"  => $caption
            ],
            "POST" => [
                "photo" => new CURLFile(realpath($photo)),
            ]
        ], true);
        return $data;
    }

    /**
     * Change chat to send messages
     *
     * @param $chatId
     */
    public function setChat($chatId)
    {
        $this->chatId = $chatId;
    }
}