<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

<script src="https://unpkg.com/html5-qrcode"></script>

<script>
let scanner = null;

document.addEventListener('livewire:init', () => {

    Livewire.on('startScanner', () => {
        if (scanner) return;

        scanner = new Html5Qrcode("qr-reader");

        scanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            (text) => Livewire.dispatch('qrScanned', text)
        );
    });

    Livewire.on('stopScanner', () => {
        if (!scanner) return;

        scanner.stop().then(() => {
            scanner.clear();
            scanner = null;
        });
    });

});
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
