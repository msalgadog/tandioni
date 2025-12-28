<div class="space-y-6">
    <x-mary-card title="Validación de Pagos">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Participante</th>
                        <th>Tanda</th>
                        <th>Fecha</th>
                        <th>Monto</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingPayments as $payment)
                        <tr>
                            <td>{{ $payment->participant->user->first_name }}</td>
                            <td>{{ $payment->tanda->name }}</td>
                            <td>{{ $payment->due_date->format('d/m/Y') }}</td>
                            <td>${{ number_format((float) $payment->amount_snapshot, 2) }}</td>
                            <td>
                                <x-mary-button
                                    class="btn-success"
                                    onclick="confirmPayment({{ $payment->id }})"
                                >
                                    Validar
                                </x-mary-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-base-content/60">Sin pagos pendientes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div wire:loading class="mt-4 flex items-center gap-2 text-sm text-base-content/70">
            <span class="loading loading-spinner loading-sm"></span>
            Procesando...
        </div>
    </x-mary-card>
</div>

@push('scripts')
<script>
    function confirmPayment(paymentId) {
        Swal.fire({
            title: '¿Confirmar pago?'
            , icon: 'question'
            , showCancelButton: true
            , confirmButtonText: 'Sí, validar'
        }).then((result) => {
            if (result.isConfirmed) {
                Livewire.dispatch('validatePayment', { paymentId: paymentId });
            }
        });
    }
</script>
@endpush
