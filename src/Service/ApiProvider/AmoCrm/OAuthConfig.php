<?php

namespace App\Service\ApiProvider\AmoCrm;

use AmoCRM\OAuth\OAuthConfigInterface;

class OAuthConfig implements OAuthConfigInterface
{
    public function __construct(
        private string $integrationId,
        private string $secretKey,
        private string $redirectDomain,
    ) {}

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function getRedirectDomain(): string
    {
        return $this->redirectDomain;
    }
}
