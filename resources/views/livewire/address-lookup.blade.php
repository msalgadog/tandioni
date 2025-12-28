<div class="space-y-4">
    <div class="grid gap-4 md:grid-cols-2">
        <x-mary-input
            label="Código Postal"
            wire:model.debounce.500ms="postalCode"
            placeholder="Ingresa CP"
        />
        <x-mary-input label="Estado" wire:model="state" disabled />
        <x-mary-input label="Municipio" wire:model="municipality" disabled />
        <x-mary-select
            label="Colonia"
            wire:model="colony"
            :options="$colonies"
        />
    </div>

    <div wire:loading class="flex items-center gap-2 text-sm text-base-content/70">
        <span class="loading loading-spinner loading-sm"></span>
        Consultando COPOMEX...
    </div>
</div>
