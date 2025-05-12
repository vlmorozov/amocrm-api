<?php

namespace App\Service\ApiProvider\AmoCrm;

use AmoCRM\OAuth\OAuthServiceInterface;
use App\Entity\ApiToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;

class OAuthService implements OAuthServiceInterface
{
    public function __construct(
        private ServiceEntityRepositoryInterface $repository
    ) {}

    public function saveOAuthToken(AccessTokenInterface $accessToken, string $baseDomain): void
    {
        /** @var ApiToken|null $tokenEntity */
        $tokenEntity = $this->repository->findOneBy(['baseDomain' => $baseDomain]);

        if (!$tokenEntity) {
            $tokenEntity = new ApiToken();
            $tokenEntity->setBaseDomain($baseDomain);
            $tokenEntity->setCreatedAt(new \DateTimeImmutable());
        }

        $tokenEntity->setAccessToken($accessToken->getToken());
        $tokenEntity->setRefreshToken($accessToken->getRefreshToken());
        $tokenEntity->setExpires($accessToken->getExpires());

        $this->repository->save($tokenEntity, true);
    }

    public function getOAuthToken(string $baseDomain): AccessTokenInterface
    {
        /** @var ApiToken|null $savedToken */
        $savedToken = $this->repository->findOneBy(['baseDomain' => $baseDomain]);

        if (!$savedToken) {
            throw new \ValueError('No token found for the given base domain');
        }

        return new AccessToken([
            'access_token' => $savedToken->getAccessToken(),
            'refresh_token' => $savedToken->getRefreshToken(),
            'expires' => $savedToken->getExpires(),
            'baseDomain' => $savedToken->getBaseDomain(),
        ]);
    }
}
