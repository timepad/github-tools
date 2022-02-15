<?php

namespace TpReleaseNotes\Telegram;

class Client
{
    /**
     * @var \TelegramBot\Api\BotApi
     */
    protected $bot_api;

    /** @var callable|\Closure */
    protected $logger;

    public function __construct($tg_token, $tg_proxy = null, $logger = null) {
        $this->bot_api        = new \TelegramBot\Api\BotApi($tg_token);

        if ($tg_proxy) {
            $this->bot_api->setCurlOption(CURLOPT_PROXY, $tg_proxy);
        }

        if ($logger && is_callable($logger)) {
            $this->logger = $logger;
        }
    }

    protected function log($message) {
        if ($this->logger && is_callable($this->logger)) {
            ($this->logger)($message);
        }
    }

    /**
     * @param string $message
     * @param int[] $tg_chats
     * @return void
     */
    public function sendMessage($message, $tg_chats) {
        foreach ($tg_chats as $tg_chat) {
            $this->log("Sending TG message to $tg_chat");
            try {
                $this->bot_api->sendMessage("-{$tg_chat}", $message);
            } catch (\Exception $e) {
                $this->log("TG to $tg_chat failed: {$e->getMessage()}");
            }
        }
    }
}