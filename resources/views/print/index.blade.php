<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $family?->name ?? 'Drucken' }} · {{ $month->translatedFormat('F Y') }} – {{ $month->copy()->addMonth()->translatedFormat('F Y') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=3" sizes="any">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; background: #f6f7f4; color: #1c1917; font-family: Arial, Helvetica, sans-serif; }
        .toolbar { max-width: 210mm; margin: 0 auto; padding: 16px 0; }
        .toolbar-row, .month-picker, .toolbar-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .toolbar-row { justify-content: space-between; margin-bottom: 12px; }
        .toolbar-hint { color: #57534e; font-size: 13px; line-height: 1.5; margin: 12px 0 0; }
        .month-picker label { font-size: 14px; font-weight: 600; }
        .button, input { border: 1px solid #d6d3d1; border-radius: 6px; background: white; color: #1c1917; font: inherit; font-size: 14px; padding: 8px 12px; }
        .button { cursor: pointer; font-weight: 600; text-decoration: none; }
        .button-primary { background: #1c1917; border-color: #1c1917; color: white; }
        :focus-visible { outline: 2px solid #0f766e; outline-offset: 3px; }
        .preview { overflow-x: auto; padding: 0 16px 24px; }
        .page { width: 210mm; min-height: 297mm; margin: 0 auto 24px; background: white; padding: 10mm; box-shadow: 0 8px 24px rgb(28 25 23 / 12%); }
        .page-header { display: flex; align-items: end; justify-content: space-between; gap: 5mm; margin-bottom: 5mm; }
        .month-title { margin: 0; font-size: 22pt; line-height: 1.1; }
        .page-number { margin: 0; color: #57534e; font-size: 8pt; white-space: nowrap; }
        .calendar { border-collapse: collapse; table-layout: fixed; width: 100%; height: 247mm; }
        .calendar th { height: 8mm; padding: 2mm 0; background: #f0f3ef; border: 1px solid #b9c2b7; font-size: 8pt; text-align: center; }
        .calendar td { width: 14.2857%; border: 1px solid #c9cec6; padding: 1.7mm; vertical-align: top; }
        .calendar .weekend { background: #f8f9f6; }
        .calendar .outside-month { background: #f0f1ee; }
        .day-number { display: block; margin-bottom: 2mm; font-size: 11pt; font-weight: 700; line-height: 1; }
        .event { margin-top: 2mm; border-top: 1px solid #e0e3dc; padding-top: 1.5mm; break-inside: avoid; overflow-wrap: anywhere; }
        .event:first-of-type { border-top: 0; padding-top: 0; }
        .event-time { font-size: 7pt; font-weight: 700; line-height: 1.25; color: #0f766e; }
        .event-title { margin-top: 0.7mm; font-size: 8pt; font-weight: 600; line-height: 1.25; }
        .event-meta { margin-top: 0.7mm; color: #57534e; font-size: 7pt; line-height: 1.25; }
        .cancelled .event-title { text-decoration: line-through; }
        .owner-dot { display: inline-block; width: 2mm; height: 2mm; margin-right: 0.5mm; border-radius: 50%; vertical-align: baseline; }
        .owner-dot-rainbow { background: conic-gradient(#ef4444, #f59e0b, #eab308, #22c55e, #06b6d4, #3b82f6, #8b5cf6, #ec4899, #ef4444); }
        .page-footer { margin: 3mm 0 0; color: #57534e; font-size: 7pt; }
        .empty { max-width: 210mm; margin: 24px auto; padding: 24px; background: white; }
        @media screen and (max-width: 850px) { .toolbar { padding: 16px; } }
        @page { size: A4 portrait; margin: 10mm; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .preview { overflow: visible; padding: 0; }
            .page { width: 190mm; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .page + .page { break-before: page; }
            .calendar tr { break-inside: avoid; }
        }
    </style>
    @vite('resources/js/app.js')
</head>
<body>
    <div class="toolbar">
        <div class="toolbar-row">
            <a class="button" href="{{ route('dashboard') }}">Zurück</a>
            <div class="toolbar-actions">
                <a class="button" href="{{ route('calendar', ['month' => $month->format('Y-m')]) }}">Kalender</a>
                @if($family)
                    <button class="button button-primary" type="button" data-print>Zwei Monate drucken</button>
                @endif
            </div>
        </div>
        @if($family)
            <div class="toolbar-row">
                <a class="button" href="{{ route('print', ['month' => $previousMonth]) }}" aria-label="Vorherige zwei Monate">← Zurück</a>
                <form class="month-picker" method="GET" action="{{ route('print') }}">
                    <label for="print-month">Startmonat</label>
                    <input id="print-month" type="month" name="month" value="{{ $month->format('Y-m') }}" required>
                    <button class="button" type="submit">Anzeigen</button>
                </form>
                <a class="button" href="{{ route('print', ['month' => $nextMonth]) }}" aria-label="Nächste zwei Monate">Weiter →</a>
            </div>
            <p class="toolbar-hint">{{ $month->translatedFormat('F Y') }} und {{ $month->copy()->addMonth()->translatedFormat('F Y') }} · Je Monat eine A4-Seite im Hochformat. Im Druckdialog Skalierung 100 % wählen und Kopf- und Fusszeilen ausschalten.</p>
        @endif
    </div>

    @unless($family)
        <main class="empty">
            <h1>Noch keine Familie verbunden</h1>
            <p>Erstelle zuerst eine Familie, danach kannst du den Kalender drucken.</p>
            <a class="button" href="{{ route('families.create') }}">Familie erstellen</a>
        </main>
    @else
        <main class="preview">
            @foreach($months as $calendarMonth)
                <section class="page" aria-label="{{ $calendarMonth['date']->translatedFormat('F Y') }}">
                    <header class="page-header">
                        <h1 class="month-title">{{ $calendarMonth['date']->translatedFormat('F Y') }}</h1>
                        <p class="page-number">Monat {{ $loop->iteration }} / 2</p>
                    </header>
                    <table class="calendar" aria-label="Kalender {{ $calendarMonth['date']->translatedFormat('F Y') }}">
                        <thead>
                            <tr>
                                @foreach(['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'] as $weekday)
                                    <th scope="col">{{ $weekday }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($calendarMonth['weeks'] as $week)
                                <tr>
                                    @foreach($week as $day)
                                        <td class="{{ ! $day['is_current_month'] ? 'outside-month' : ($day['date']->isWeekend() ? 'weekend' : '') }}">
                                            @if($day['is_current_month'])
                                                <time class="day-number" datetime="{{ $day['date']->toDateString() }}">{{ $day['date']->day }}</time>
                                                @foreach($day['events'] as $event)
                                                    <article class="event {{ $event->status === 'cancelled' ? 'cancelled' : '' }}">
                                                        @unless($event->all_day)
                                                            <div class="event-time">
                                                                @if($event->starts_at->isSameDay($day['date']))
                                                                    {{ $event->starts_at->format('H:i') }}@if($event->ends_at && $event->ends_at->isSameDay($day['date']))–{{ $event->ends_at->format('H:i') }}@endif
                                                                @elseif($event->ends_at && $event->ends_at->isSameDay($day['date']))
                                                                    bis {{ $event->ends_at->format('H:i') }}
                                                                @else
                                                                    Fortsetzung
                                                                @endif
                                                            </div>
                                                        @endunless
                                                        <div class="event-title">{{ $event->title }}</div>
                                                        <div class="event-meta">
                                                            <span class="owner-dot {{ $event->isForFamily() ? 'owner-dot-rainbow' : '' }}" @unless($event->isForFamily()) style="background-color: {{ $eventOwners[$event->id]['color'] }}" @endunless></span>{{ $eventOwners[$event->id]['name'] }}
                                                        </div>
                                                        @if($event->status === 'cancelled')
                                                            <div class="event-meta">Abgesagt</div>
                                                        @endif
                                                    </article>
                                                @endforeach
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="page-footer">Stand: {{ now('Europe/Zurich')->format('d.m.Y H:i') }}</p>
                </section>
            @endforeach
        </main>
    @endunless
</body>
</html>
