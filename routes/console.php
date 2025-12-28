<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $daysBefore = (int) config('tandas.reminder_days', 3);
    $targetDate = now()->addDays($daysBefore)->toDateString();

    Payment::query()
        ->with(['participant.user', 'tanda'])
        ->whereDate('due_date', $targetDate)
        ->where('status', PaymentStatus::Pending)
        ->get()
        ->each(function (Payment $payment) {
            $message = "Recordatorio: tu próxima vuelta es el {$payment->due_date->format('d/m/Y')}.";
            WhatsAppService::fromConfig()->sendText($payment->participant->user->phone, $message);
        });
})->dailyAt('09:00')->name('tandas:reminder');

Schedule::call(function () {
    Payment::query()
        ->with(['participant.user'])
        ->whereDate('due_date', now()->toDateString())
        ->where('status', PaymentStatus::Pending)
        ->get()
        ->each(function (Payment $payment) {
            $message = 'Hoy corresponde tu pago. Sube tu comprobante desde el panel de usuario.';
            WhatsAppService::fromConfig()->sendText($payment->participant->user->phone, $message);
        });
})->dailyAt('08:00')->name('tandas:collection');

Schedule::call(function () {
    $targetDate = now()->subDay()->toDateString();

    Payment::query()
        ->with(['participant.user'])
        ->whereDate('due_date', $targetDate)
        ->where('status', PaymentStatus::Pending)
        ->get()
        ->each(function (Payment $payment) {
            $message = 'Detectamos retraso en tu pago. Por favor ponte al corriente.';
            WhatsAppService::fromConfig()->sendText($payment->participant->user->phone, $message);
        });
})->dailyAt('10:00')->name('tandas:late-alert');
