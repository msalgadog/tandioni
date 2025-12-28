<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Notifications\PaymentValidatedNotification;
use Livewire\\Attributes\\On;
use Livewire\\Component;

class AdminDashboard extends Component
{
    #[On('validatePayment')]
    public function validatePayment(int $paymentId): void
    {
        $payment = Payment::query()->with('participant.user')->findOrFail($paymentId);

        $payment->update([
            'status' => PaymentStatus::Validated,
            'validated_at' => now(),
        ]);

        $payment->participant->user->notify(new PaymentValidatedNotification($payment));

        $this->dispatch('toast', type: 'success', message: 'Pago validado.');
    }

    public function render()
    {
        $pendingPayments = Payment::query()
            ->with(['participant.user', 'tanda'])
            ->where('status', PaymentStatus::Uploaded)
            ->latest('due_date')
            ->get();

        return view('livewire.admin-dashboard', [
            'pendingPayments' => $pendingPayments,
        ]);
    }
}
