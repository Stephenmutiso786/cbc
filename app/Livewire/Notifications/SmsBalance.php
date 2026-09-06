<?php

namespace App\Livewire\Notifications;

use App\Services\OlympusSmsService;
use Livewire\Component;
use Throwable;

class SmsBalance extends Component
{
    public mixed $units = null;
    public string $checkedAt = '';
    public string $error = '';
    public string $messagesError = '';
    public array $messages = [];
    public int $messagesPage = 1;
    public int $messagesLastPage = 1;
    public int $messagesTotal = 0;
    public bool $messagesLoading = false;
    public bool $loading = false;

    public function mount(OlympusSmsService $sms): void
    {
        $this->refreshBalance($sms);
        $this->refreshMessages($sms);
    }

    public function refresh(OlympusSmsService $sms): void
    {
        $this->refreshBalance($sms);
        $this->refreshMessages($sms);
    }

    public function refreshMessagesNow(OlympusSmsService $sms): void
    {
        $this->refreshMessages($sms, $this->messagesPage);
    }

    public function nextMessagesPage(OlympusSmsService $sms): void
    {
        if ($this->messagesPage >= $this->messagesLastPage) {
            return;
        }

        $this->refreshMessages($sms, $this->messagesPage + 1);
    }

    public function previousMessagesPage(OlympusSmsService $sms): void
    {
        if ($this->messagesPage <= 1) {
            return;
        }

        $this->refreshMessages($sms, $this->messagesPage - 1);
    }

    public function render()
    {
        return view('livewire.notifications.sms-balance')
            ->layout('layouts.admin');
    }

    private function refreshBalance(OlympusSmsService $sms): void
    {
        $this->loading = true;
        $this->error = '';

        try {
            $balance = $sms->getBalance();
            $this->units = $balance['units'];
            $this->checkedAt = $balance['checked_at'];
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    private function refreshMessages(OlympusSmsService $sms, int $page = 1): void
    {
        $this->messagesLoading = true;

        try {
            $result = $sms->getMessages($page);
            $this->messages = $result['items'];
            $this->messagesPage = $result['meta']['current_page'];
            $this->messagesLastPage = max(1, $result['meta']['last_page']);
            $this->messagesTotal = $result['meta']['total'];
        } catch (Throwable $exception) {
            report($exception);
            $this->messagesError = $exception->getMessage();
        } finally {
            $this->messagesLoading = false;
        }
    }
}
