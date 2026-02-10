<?php

use Livewire\Volt\Component;
use App\Models\Member\Card;

new class extends Component {

    public string $uid = '';
    public ?int $balance = null;
    public ?string $message = null;
    public ?string $status = null;
    public string $mode = 'uid';

    public function lookup(): void
    {
        $this->balance = null;
        $this->message = null;
        $this->status = null;

        $uid = trim($this->uid);

        if ($uid === '') {
            $this->message = 'Bitte Kartennummer eingeben.';
            return;
        }

        $card = Card::with('loyaltyAccount')->where('uuid', $uid)->first();

        if (!$card) {
            $this->message = 'Karte nicht gefunden.';
            return;
        }

        if (!$card->active) {
            $this->status = 'gesperrt';
            $this->message = 'Die Karte ist gesperrt.';
            return;
        }

        if (!$card->loyaltyAccount) {
            $this->message = 'Kein Guthabenkonto vorhanden.';
            return;
        }

        $this->balance = $card->loyaltyAccount->balance();
        $this->status = 'aktiv';
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->message = null;
        $this->balance = null;
        $this->status = null;

        if ($mode === 'qr') {
            $this->dispatch('qr-mode');
        }
    }

};
?>
<section class="w-full max-w-2xl mx-auto p-6 text-center">
    <flux:heading size="xl">Treuepunkteguthaben</flux:heading>
    <flux:text class="mt-2">Kartennummer eingeben oder QR-Code scannen.</flux:text>

    <div class="mt-6 space-y-4">
        <div class="flex gap-2">
            <flux:button variant="{{ $mode === 'uid' ? 'primary' : 'ghost' }}" wire:click="setMode('uid')">Kartennummer</flux:button>
            <flux:button variant="{{ $mode === 'qr' ? 'primary' : 'ghost' }}" wire:click="setMode('qr')">QR-Code</flux:button>
        </div>

        @if($mode === 'uid')
            <flux:input
                wire:model.live="uid"
                placeholder="Kartennummer"
                label="Kartennummer"
            />
            @if(!is_null($balance))
            <div class="my-3">

                <div class="text-center">
                <flux:text>Treuepunkte</flux:text>
                <flux:heading size="xl" class="mb-1">{{ $balance }}</flux:heading>
                </div>
            </div>
            @endif
             @if($message)
            <flux:callout variant="danger" class="mt-4">{{ $message }}</flux:callout>
            @endif
            <div class="flex gap-2">
                <flux:button wire:click="lookup" class="w-full">Guthaben prüfen</flux:button>
            </div>
        @endif

        @if($mode === 'qr')
            <div class="mt-4" wire:ignore>
                <div id="qr-reader" class="border rounded-lg p-3 bg-white shadow-sm"></div>
                <div class="text-xs text-gray-500 mt-2">Kamera-Zugriff erlauben, um QR-Code zu scannen.</div>
            </div>
        @endif

       

        
    </div>

    <script src="https://unpkg.com/html5-qrcode" defer></script>
    <script>
        document.addEventListener('livewire:init', () => {
            const initQr = async () => {
                const el = document.getElementById('qr-reader');
                if (!el) return;

                if (window.__qrInstance) {
                    try {
                        await window.__qrInstance.stop();
                    } catch (e) {}
                    try {
                        await window.__qrInstance.clear();
                    } catch (e) {}
                    window.__qrInstance = null;
                }

                window.__qrInstance = new Html5Qrcode('qr-reader');
                window.__qrInstance.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: 250 },
                    (decodedText) => {
                        const uid = decodedText.trim();
                        if (!uid) return;
                        const component = Livewire.first();
                        if (!component) return;
                        component.set('uid', uid);
                        component.call('lookup');
                    }
                ).catch(() => {
                    // ignore camera errors
                });
            };

            Livewire.on('qr-mode', () => {
                setTimeout(initQr, 0);
            });
        });
    </script>
</section>
