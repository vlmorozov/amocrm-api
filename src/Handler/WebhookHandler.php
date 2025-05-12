<?php

namespace App\Handler;

use App\Message\WebhookMessage;
use App\Service\ExternalApi;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class WebhookHandler
{
    public function __construct(
        private ExternalApi $externalApi
    ) {}

    public function __invoke(WebhookMessage $message): void
    {
        parse_str($message->getContent(), $data);
        if (isset($data['leads']['add'])) {
            $leads = $data['leads']['add'];
            foreach ($leads as $lead) {
                $this->externalApi->addNoteOnLeadCreate($lead);
            }
        }
        if (isset($data['leads']['update'])) {
            $leads = $data['leads']['update'];
            foreach ($leads as $lead) {
                $this->externalApi->addNoteOnLeadUpdate($lead);
            }
        }
        if (isset($data['contacts']['add'])) {
            $contacts = $data['contacts']['add'];
            foreach ($contacts as $contact) {
                $this->externalApi->addNoteOnContactCreate($contact);
            }
        }
        if (isset($data['contacts']['update'])) {
            $contacts = $data['contacts']['update'];
            foreach ($contacts as $contact) {
                $this->externalApi->addNoteOnContactUpdate($contact);
            }
        }
    }
}
