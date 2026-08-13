<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview — {{ $bewijsstuk->naam }}</title>
    {{-- Bewust een losstaand document met eigen opmaak: de inhoud is
         geconverteerde, gebruikersafkomstige HTML en hoort niet in de
         applicatie-layout. De CSP-header (controller) verbiedt scripts en
         externe bronnen. --}}
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            padding: 2rem;
            max-width: 55rem;
            margin-inline: auto;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            line-height: 1.6;
            color: #18181b;
            background: #fff;
        }
        .kop {
            margin: 0 0 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e4e4e7;
            font-size: 0.85rem;
            color: #71717a;
        }
        .kop strong { color: #18181b; }
        table { border-collapse: collapse; }
        th, td { border: 1px solid #d4d4d8; padding: 0.35rem 0.6rem; text-align: left; }
        img { max-width: 100%; height: auto; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
        @media (prefers-color-scheme: dark) {
            body { color: #e4e4e7; background: #18181b; }
            .kop { border-color: #3f3f46; color: #a1a1aa; }
            .kop strong { color: #fafafa; }
            th, td { border-color: #3f3f46; }
        }
    </style>
</head>
<body>
    <p class="kop">
        Preview van <strong>{{ $bewijsstuk->naam }}</strong> ({{ $bewijsstuk->bestandsnaam }}).
        Dit is een weergave ter oriëntatie; het gedownloade bestand blijft het geldende document.
    </p>

    {{-- $inhoud is gesaneerd in Documentpreview (allowlist) en wordt geserveerd
         met een strikte CSP. Daarom bewust ongeëscaped. --}}
    <main>{!! $inhoud !!}</main>
</body>
</html>
