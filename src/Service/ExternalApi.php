<?php

namespace App\Service;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\Exceptions\AmoCRMApiErrorResponseException;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMApiNoContentException;
use AmoCRM\Filters\BaseRangeFilter;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Filters\LeadsFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\EventModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\NoteType\CommonNote;
use App\Service\ApiProvider\AmoCrm\OAuthService;
use Psr\Log\LoggerInterface;

class ExternalApi
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private LoggerInterface $logger,
        private AmoCRMApiClient $client,
        private OAuthService $oauthService,
    ) {
        $token = $this->oauthService->getOAuthToken($this->client->getAccountBaseDomain());

        $this->client->setAccessToken($token);
    }

    public function addNoteOnLeadCreate(array $leadInfo): void
    {
        $lead = LeadModel::fromArray($leadInfo);

        $message = $this->buildMessageOnLeadCreate($lead);
        $this->addNote(EntityTypesInterface::LEADS, $lead->getId(), $message);
    }

    public function addNoteOnLeadUpdate(array $leadInfo): void
    {
        $lead = LeadModel::fromArray($leadInfo);

        $message = $this->buildMessageOnLeadUpdate($lead);
        $this->addNote(EntityTypesInterface::LEADS, $lead->getId(), $message);
    }

    public function addNoteOnContactCreate(array $contactInfo): void
    {
        $contact = ContactModel::fromArray($contactInfo);

        $message = $this->buildMessageOnContactCreate($contact);
        $this->addNote(EntityTypesInterface::CONTACTS, $contact->getId(), $message);

        $contactLeads = $contact->getLeads();
        if ($contactLeads) {
            foreach ($contactLeads as $lead) {
                $this->addNote(EntityTypesInterface::LEADS, $lead->getId(), $message);
            }
        }
    }

    public function addNoteOnContactUpdate(array $contactInfo): void
    {
        $contact = ContactModel::fromArray($contactInfo);

        $message = $this->buildMessageOnContactUpdate($contact);
        $this->addNote(EntityTypesInterface::CONTACTS, $contact->getId(), $message);

        $contactLeads = $contact->getLeads();
        if ($contactLeads) {
            foreach ($contactLeads as $lead) {
                $this->addNote(EntityTypesInterface::LEADS, $lead->getId(), $message);
            }
        }
    }

    private function buildMessageOnContactCreate(ContactModel $contact): string
    {
        $text = [];
        $text[] = 'Создан контакт: ' . $contact->getId();
        $text[] = 'Имя: ' . $contact->getName();
        $text[] = 'Ответственный: ' . $this->getResponsibleUserInfo($contact->getResponsibleUserId());

        return implode(PHP_EOL, $text);
    }

    private function buildMessageOnContactUpdate(ContactModel $contact): string
    {
        $text = [];
        $text[] = 'Изменен контакт: ' . $contact->getId();

        try {
            $changes = $this->getContactChanges($contact);
            if (!empty($changes)) {
                $text[] = 'Изменения: ';
                /** @var EventModel $change */
                foreach ($changes as $change) {
                    $text[] = '    Старое значение: ' . print_r($change->getValueBefore(), true);
                    $text[] = '    Новое значение: ' . print_r($change->getValueAfter(), true);
                }
            } else {
                $text[] = 'Нет изменений';
            }
        } catch (AmoCRMApiException $e) {
            $this->logger->error($e);
            $text[] = 'Ошибка при получении изменений';
        }

        return implode(PHP_EOL, $text);
    }

    private function addNote(string $entityType, int $entityId, string $message): void
    {
        $notesCollection = new NotesCollection();
        $serviceMessageNote = new CommonNote();
        $serviceMessageNote
            ->setEntityId($entityId)
            ->setText($message)
        ;

        $notesCollection->add($serviceMessageNote);

        try {
            $leadNotesService = $this->client->notes($entityType);
            $leadNotesService->add($notesCollection);
        } catch (AmoCRMApiErrorResponseException $e) {
            $errorDetails = $e->getValidationErrors();
            $this->logger->error(print_r($errorDetails, 1));
        } catch (AmoCRMApiException $e) {
            $this->logger->error($e);
        }
    }

    private function buildMessageOnLeadCreate(LeadModel $lead): string
    {
        $text = [];
        $text[] = 'Создана сделка: ' . $lead->getId();
        $text[] = 'Название: ' . $lead->getName();
        $text[] = 'Ответственный: ' . $this->getResponsibleUserInfo($lead->getResponsibleUserId());
        $text[] = 'Время добавления: ' . (new \DateTimeImmutable('@'.$lead->getCreatedAt()))->format(self::DATE_FORMAT);

        return implode(PHP_EOL, $text);
    }

    private function buildMessageOnLeadUpdate(LeadModel $lead): string
    {
        $text = [];
        $text[] = 'Обновлена сделка: ' . $lead->getId();
        $text[] = 'Название: ' . $lead->getName();
        $text[] = 'Время изменения: ' . (new \DateTimeImmutable('@'.$lead->getUpdatedAt()))->format(self::DATE_FORMAT);

        try {
            $leadChanges = $this->getLeadChanges($lead);
            if (!empty($leadChanges)) {

                $text[] = 'Изменения: ';
                /** @var EventModel $leadChange */
                foreach ($leadChanges as $leadChange) {
                    $text[] = '    Старое значение: ' . print_r($leadChange->getValueBefore(), true);
                    $text[] = '    Новое значение: ' . print_r($leadChange->getValueAfter(), true);
                }
            } else {
                $text[] = 'Нет изменений';
            }
        } catch (AmoCRMApiException $e) {
            $this->logger->error($e);
            $text[] = 'Ошибка при получении изменений';
        }

        return implode(PHP_EOL, $text);
    }

    private function getLeadChanges(LeadModel $lead): iterable
    {
        $filter = new LeadsFilter();
        $filter->setIds($lead->getId());

        $range = (new BaseRangeFilter())
            ->setFrom($lead->getUpdatedAt())
            ->setTo($lead->getUpdatedAt()+1)
        ;
        $filter->setCreatedAt($range);

        try {
            return $this->client->events()->get($filter);
        } catch (AmoCRMApiNoContentException $e) {
            $this->logger->error($e);
        }

        return [];
    }

    private function getContactChanges(ContactModel $contact): iterable
    {
        $filter = new ContactsFilter();
        $filter->setIds($contact->getId());

        $range = (new BaseRangeFilter())
            ->setFrom($contact->getUpdatedAt())
            ->setTo($contact->getUpdatedAt()+1)
        ;
        $filter->setCreatedAt($range);

        try {
            return $this->client->events()->get($filter);
        } catch (AmoCRMApiNoContentException $e) {
            $this->logger->error($e);
        }

        return [];
    }

    private function getResponsibleUserInfo(int $userId): string
    {
        try {
            $responsibleUser = $this->client->users()->getOne($userId);
            return \sprintf("%s [%s]", $responsibleUser?->getName(), $userId);
        } catch (AmoCRMApiException $e) {
            $this->logger->error($e);
        }

        return (string)$userId;
    }

    public function getContacts()
    {
        return $this->client->contacts()->get();
    }

    public function getLeads()
    {
        return $this->client->leads()->get();
    }

    public function getEvents()
    {
        return $this->client->events()->get();
    }
}
