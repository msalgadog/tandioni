<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\AddressService;
use Livewire\Attributes\On;
use Livewire\Component;

class AddressLookup extends Component
{
    public string $postalCode = '';
    public string $state = '';
    public string $municipality = '';
    public string $colony = '';
    public array $colonies = [];

    public function updatedPostalCode(AddressService $addressService): void
    {
        if (strlen($this->postalCode) < 5) {
            $this->reset(['state', 'municipality', 'colony', 'colonies']);
            return;
        }

        $data = $addressService->lookupByPostalCode($this->postalCode);
        $this->state = $data['state'];
        $this->municipality = $data['municipality'];
        $this->colonies = $data['colonies'];
        $this->colony = $data['colonies'][0] ?? '';
    }

    public function render()
    {
        return view('livewire.address-lookup');
    }
}
