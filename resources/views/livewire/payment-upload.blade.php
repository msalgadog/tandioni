<div class="space-y-6">
    <x-mary-card title="Carga de Comprobante">
        <x-mary-file
            label="Comprobante (PDF/JPG)"
            wire:model="receipt"
            accept="application/pdf,image/*"
        />

        <div class="mt-4 flex items-center gap-3">
            <x-mary-button class="btn-primary" wire:click="save">Enviar</x-mary-button>
            <span wire:loading class="loading loading-spinner loading-sm"></span>
        </div>
    </x-mary-card>
</div>
