<?php

namespace App\Handler;

use App\Message\WebhookMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class WebhookHandler
{
    public function __invoke(WebhookMessage $message): void
    {
        // Логика обработки сообщения
        echo 'Message content: ' . $message->getContent();
    }
}