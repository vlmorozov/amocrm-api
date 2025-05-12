<?php

namespace App\Controller;

use AmoCRM\Client\AmoCRMApiClient;
use App\Message\WebhookMessage;
use App\Service\ApiProvider\AmoCrm\OAuthService;
use App\Service\ExternalApi;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController
{
    #[Route('/amocrm/webhook', name: 'amocrm_webhook')]
    public function index(
        Request $request,
        LoggerInterface $webhookLogger,
        MessageBusInterface $messageBus,
    ): Response {
        $webhookLogger->info('WEBHOOK Incoming request', [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
        ]);

        //todo: validate request

        $message = new WebhookMessage($request->getContent());
        $messageBus->dispatch($message);

        return new Response('Webhook received', Response::HTTP_OK);
    }

    #[Route('/amocrm/oauth/init', name: 'amocrm_oauth_init')]
    public function oauthInit(Request $request, LoggerInterface $webhookLogger, AmoCRMApiClient $amoClient): Response
    {
        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('oauth2state', $state);

        $amoClient->getOAuthClient()->setBaseDomain('amocrm.ru');
        $authorizationUrl = $amoClient->getOAuthClient()->getAuthorizeUrl([
            'state' => $state,
            'mode' => 'post_message',
        ]);
        return $this->redirect($authorizationUrl);
    }

    #[Route('/amocrm/oauth/callback', name: 'amocrm_oauth_callback')]
    public function oauthCallback(
        Request $request,
        LoggerInterface $webhookLogger,
        AmoCRMApiClient $amoClient,
        OAuthService $authService,
    ): Response {
        $state = $request->get('state');

        $webhookLogger->info('OAuth Incoming request', [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
        ]);

        $sessionState = $request->getSession()->get('oauth2state');
        if (empty($state) || $state !== $sessionState) {
            $webhookLogger->error('Invalid state parameter', [
                'received_state' => $state,
                'expected_state' => $sessionState,
            ]);
            throw new \RuntimeException('Invalid state parameter');
        }

        $code = $request->query->get('code');
        if (empty($code)) {
            $webhookLogger->error('Authorization code not received', ['query' => $request->query->all()]);
            throw $this->createNotFoundException('Authorization code not received');
        }

        $domain = $request->query->get('referer');
        if (!$domain) {
            $webhookLogger->error('Invalid referer', ['referer' => $domain]);
            throw new \RuntimeException('Invalid account domain');
        }

        try {
            $webhookLogger->info('OAuth Incoming request', [
                'domain' => $domain,
                'code' => $code,
            ]);

            $accessToken = $amoClient->getOAuthClient()->getAccessTokenByCode($code);

            $authService->saveOAuthToken($accessToken, $domain);

            $webhookLogger->info('Successfully authenticated with AmoCRM', [
                'domain' => $domain,
                'expires' => $accessToken->getExpires()
            ]);

            return new Response('Success! Token saved.', Response::HTTP_OK);
        } catch (\Exception $e) {
            $webhookLogger->error('AmoCRM OAuth error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $this->createAccessDeniedException('Failed to authenticate with AmoCRM: '.$e->getMessage());
        }
    }

    #[Route('/amocrm/contacts', name: 'amocrm_contacts')]
    public function contacts(ExternalApi $externalApi): Response
    {
        $items = $externalApi->getEvents();

        return new Response(print_r($items, true), Response::HTTP_OK);
    }
}
