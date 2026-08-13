<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview niet beschikbaar — {{ $bewijsstuk->naam }}</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0; padding: 2rem; max-width: 40rem; margin-inline: auto;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            line-height: 1.6; color: #18181b; background: #fff;
        }
        @media (prefers-color-scheme: dark) {
            body { color: #e4e4e7; background: #18181b; }
        }
    </style>
</head>
<body>
    <h1>Preview niet beschikbaar</h1>
    <p>
        Van <strong>{{ $bewijsstuk->naam }}</strong> ({{ $bewijsstuk->bestandsnaam }})
        kon geen preview worden gemaakt. Download het bestand om het te bekijken;
        de download blijft het geldende document.
    </p>
</body>
</html>
