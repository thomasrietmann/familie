<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Drucken</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=3" sizes="any">
    <style>
        :root {
            color-scheme: light;
        }

        body {
            margin: 0;
            background: #f6f7f4;
            color: #1c1917;
            font-family: Arial, Helvetica, sans-serif;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            max-width: 277mm;
            margin: 0 auto;
            padding: 16px;
        }

        .toolbar-hint {
            color: #57534e;
            font-size: 13px;
            line-height: 1.35;
        }

        .toolbar-actions {
            display: flex;
            flex: 0 0 auto;
            gap: 8px;
        }

        .button {
            border: 1px solid #d6d3d1;
            border-radius: 6px;
            background: white;
            color: #1c1917;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 14px;
            text-decoration: none;
        }

        .button-primary {
            background: #1c1917;
            border-color: #1c1917;
            color: white;
        }

        .page {
            box-sizing: border-box;
            width: 277mm;
            height: 190mm;
            margin: 0 auto 24px;
            overflow: hidden;
            background: white;
            padding: 8mm;
            box-shadow: 0 16px 40px rgb(28 25 23 / 14%);
        }

        .page-header {
            border-bottom: 1px solid #d6d3d1;
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 8mm;
            margin-bottom: 3.5mm;
            padding-bottom: 3mm;
        }

        .page-title {
            font-size: 14pt;
            font-weight: 700;
            line-height: 1.1;
            margin: 0;
        }

        .page-subtitle {
            color: #57534e;
            font-size: 8pt;
            margin: 1.5mm 0 0;
        }

        .generated-at {
            color: #57534e;
            font-size: 7.5pt;
            white-space: nowrap;
        }

        .event-list {
            column-count: 2;
            column-gap: 8mm;
            column-fill: auto;
            font-size: 8pt;
            height: 163mm;
            overflow: hidden;
        }

        .event {
            break-inside: avoid;
            border-bottom: 1px solid #e7e5e4;
            display: inline-block;
            margin: 0 0 2.2mm;
            padding: 0 0 1.8mm;
            width: 100%;
        }

        .event-main {
            display: flex;
            gap: 1.8mm;
        }

        .owner-dot {
            border-radius: 9999px;
            display: inline-block;
            flex: 0 0 auto;
            height: 2.5mm;
            margin-top: 0.7mm;
            width: 2.5mm;
        }

        .owner-dot-rainbow {
            background: conic-gradient(#ef4444, #f59e0b, #eab308, #22c55e, #06b6d4, #3b82f6, #8b5cf6, #ec4899, #ef4444);
        }

        .event-date {
            color: #0f766e;
            font-size: 7.5pt;
            font-weight: 700;
            margin-bottom: 0.5mm;
        }

        .event-title {
            font-size: 8pt;
            font-weight: 700;
            line-height: 1.15;
        }

        .event-meta {
            color: #57534e;
            font-size: 7.5pt;
            line-height: 1.2;
            margin-top: 0.5mm;
        }

        .empty {
            color: #57534e;
            font-size: 8pt;
        }

        @page {
            margin: 10mm;
            size: 297mm 210mm;
        }

        @media print {
            html,
            body {
                height: 190mm;
                width: 277mm;
            }

            body {
                background: white;
            }

            .toolbar {
                display: none;
            }

            .page {
                box-shadow: none;
                margin: 0;
                padding: 0;
                height: 190mm;
                width: 277mm;
            }

            .event-list {
                height: 174mm;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a class="button" href="{{ route('dashboard') }}">Zurück</a>
        <div class="toolbar-hint">A4 Querformat. Im Druckdialog bei Bedarf Papierformat A4 und Ausrichtung Querformat wählen.</div>
        <div class="toolbar-actions">
            <a class="button" href="{{ route('calendar') }}">Kalender</a>
            <button class="button button-primary" type="button" onclick="window.print()">Drucken</button>
        </div>
    </div>

    <main class="page">
        <header class="page-header">
            <div>
                <h1 class="page-title">Drucken</h1>
                <p class="page-subtitle">{{ $family?->name ?? 'Noch keine Familie verbunden' }} · nächste Termine ab heute</p>
            </div>
            <p class="generated-at">{{ now('Europe/Zurich')->format('d.m.Y H:i') }}</p>
        </header>

        @if($events->isEmpty())
            <p class="empty">Keine kommenden Termine vorhanden.</p>
        @else
            <section class="event-list">
                @foreach($events as $event)
                    <article class="event">
                        <div class="event-main">
                            <x-owner-dot :event="$event" />
                            <div>
                                <div class="event-date">{{ $event->printDateLabel() }}</div>
                                <div class="event-title">{{ $event->title }}</div>
                                <div class="event-meta">
                                    {{ $event->ownerDisplayName() }}
                                    @if($event->location)
                                        · {{ $event->location }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
    </main>
</body>
</html>
