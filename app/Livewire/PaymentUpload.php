<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentUploadedNotification;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class PaymentUpload extends Component
{
    use WithFileUploads;

    public Payment $payment;

    #[Rule('required|file|mimes:pdf,jpg,jpeg,png|max:5120')]
    public $receipt;

    public function save(): void
    {
        $this->validate();

        $path = $this->receipt->store('receipts', 'private');

        $this->payment->update([
            'receipt_path' => $path,
            'status' => PaymentStatus::Uploaded,
        ]);

        $this->payment->participant->user->notify(new PaymentUploadedNotification($this->payment));
        if ($this->payment->recipient) {
            $this->payment->recipient->notify(new PaymentUploadedNotification($this->payment));
        }
        User::query()
            ->where('role', 'admin')
            ->get()
            ->each->notify(new PaymentUploadedNotification($this->payment));

        $this->dispatch('toast', type: 'success', message: 'Comprobante recibido.');
    }

    public function render()
    {
        return view('livewire.payment-upload');
    }
}
