<?php

namespace App\Message;

class WebhookMessage
{
    public function __construct(
        private string $content
    ) {}

    public function getContent(): string
    {
        return $this->content;
    }
}