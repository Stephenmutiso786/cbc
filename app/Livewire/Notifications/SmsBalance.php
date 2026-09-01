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
    public bool $loading = false;

    public function mount(OlympusSmsService $sms): void
    {
        $this->refreshBalance($sms);
    }

    public function refresh(OlympusSmsService $sms): void
    {
        $this->refreshBalance($sms);
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
}
