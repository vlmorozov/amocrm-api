<?php

namespace App\Controller;

use App\Message\WebhookMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class WebhookController extends AbstractController
{
    #[Route('/webhook/amocrm', name: 'app_webhook_amocrm')]
    public function index(
        Request $request,
        LoggerInterface $webhookLogger,
        MessageBusInterface $messageBus,
    ): Response {
        $webhookLogger->info('Incoming request', [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
        ]);

        //todo: validate request

        $message = new WebhookMessage($request->getContent());
        $messageBus->dispatch($message);

        return $this->render('webhook/index.html.twig', [
            'controller_name' => 'WebhookController',
        ]);
    }
}
