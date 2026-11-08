<?php

namespace App\Services\CalendarMail;

use DateTimeImmutable;
use DateTimeZone;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property\ICalendar\DateTime as CalendarDate;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;
use Sabre\VObject\TimeZoneUtil;
use Throwable;

class InvitationParser
{
    public function parse(string $ics, string $sender): array
    {
        try {
            return $this->read($ics, $sender);
        } catch (RejectedMail $e) {
            throw $e;
        } catch (Throwable) {
            throw new RejectedMail('invalid_calendar');
        }
    }

    private function read(string $ics, string $sender): array
    {
        if (strlen($ics) > 262144 || substr_count($ics, "\n") > 5000) {
            throw new RejectedMail('invalid_calendar');
        }
        $calendar = Reader::read($ics);
        if (! $calendar instanceof VCalendar) {
            throw new RejectedMail('invalid_calendar');
        }
        $method = strtoupper((string) ($calendar->METHOD ?? 'PUBLISH'));
        if (! in_array($method, ['REQUEST', 'PUBLISH', 'CANCEL'], true)) {
            throw new RejectedMail('invalid_calendar');
        }
        $events = array_values($calendar->select('VEVENT'));
        if (count($events) < 1 || count($events) > 100) {
            throw new RejectedMail('invalid_calendar');
        }
        $master = null;
        $identity = null;
        $seen = [];
        foreach ($events as $event) {
            $uid = (string) $event->UID;
            $organizer = strtolower((string) ($event->ORGANIZER ?? 'mailto:'.$sender));
            if ($uid === '' || strlen($uid) > 1024 || ! str_starts_with($organizer, 'mailto:') || ! filter_var(substr($organizer, 7), FILTER_VALIDATE_EMAIL)) {
                throw new RejectedMail('invalid_calendar');
            }
            $key = hash('sha256', $uid."\0".$organizer);
            if ($identity !== null && $identity !== $key) {
                throw new RejectedMail('invalid_calendar');
            }
            $identity = $key;
            foreach (['UID', 'DTSTART', 'DTEND', 'DTSTAMP', 'SEQUENCE', 'ORGANIZER', 'RECURRENCE-ID', 'RRULE'] as $property) {
                if (count($event->select($property)) > 1) {
                    throw new RejectedMail('invalid_calendar');
                }
            }
            foreach (['DTSTART', 'DTEND', 'DTSTAMP', 'RECURRENCE-ID', 'EXDATE', 'RDATE'] as $property) {
                foreach ($event->select($property) as $date) {
                    if (isset($date['TZID'])) {
                        TimeZoneUtil::getTimeZone((string) $date['TZID'], $calendar, true);
                    }
                }
            }
            if (isset($event->{'RECURRENCE-ID'}['RANGE']) || isset($event->EXRULE) || isset($event->RDATE)) {
                throw new RejectedMail('unsupported_recurrence');
            }
            $occurrence = $this->occurrence($event);
            if (isset($seen[$occurrence])) {
                throw new RejectedMail('invalid_calendar');
            }
            $seen[$occurrence] = true;
            if ($occurrence === 'master') {
                $master = $event;
            }
            $this->item($event, $method);
        }
        $items = [];
        $recurring = $master && isset($master->RRULE);
        if ($recurring && $method !== 'CANCEL' && strtoupper((string) $master->STATUS) !== 'CANCELLED') {
            $rule = $master->RRULE->getParts();
            $this->validateRule($rule);
            if (isset($rule['BYMONTH']) && ! isset($rule['BYDAY']) && ! isset($rule['BYMONTHDAY']) && (int) $master->DTSTART->getDateTime()->format('d') > 28) {
                throw new RejectedMail('unsupported_recurrence');
            }
            if (! in_array($rule['FREQ'] ?? '', ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)
                || array_diff(array_keys($rule), ['FREQ', 'COUNT', 'UNTIL', 'INTERVAL', 'BYDAY', 'BYMONTHDAY', 'BYMONTH', 'BYSETPOS', 'WKST'])
                || (! isset($rule['COUNT']) && ! isset($rule['UNTIL']))
                || (isset($rule['COUNT']) && ((int) $rule['COUNT'] < 1 || (int) $rule['COUNT'] > 366))
                || (isset($rule['INTERVAL']) && ((int) $rule['INTERVAL'] < 1 || (int) $rule['INTERVAL'] > 366))) {
                throw new RejectedMail('unsupported_recurrence');
            }
            if (isset($rule['UNTIL']) && new DateTimeImmutable($rule['UNTIL']) > $master->DTSTART->getDateTime(new DateTimeZone('Europe/Zurich'))->modify('+5 years')) {
                throw new RejectedMail('unsupported_recurrence');
            }
            $iterator = new EventIterator($calendar, (string) $master->UID, new DateTimeZone('Europe/Zurich'));
            while ($iterator->valid()) {
                if (count($items) >= 366) {
                    throw new RejectedMail('unsupported_recurrence');
                }
                $item = $this->item($iterator->getEventObject(), $method);
                $items[$item['key']] = $item;
                $iterator->next();
            }
        } else {
            foreach ($events as $event) {
                $item = $this->item($event, $method);
                $items[$item['key']] = $item;
            }
        }
        if ($recurring) {
            // Keep explicit cancelled exceptions, which recurrence iterators can omit.
            foreach ($events as $event) {
                if (isset($event->{'RECURRENCE-ID'}) && strtoupper((string) $event->STATUS) === 'CANCELLED') {
                    $item = $this->item($event, $method);
                    $items[$item['key']] = $item;
                }
            }
        }

        return ['series_key' => $identity, 'master' => $master ? $this->item($master, $method) : null, 'recurring' => $recurring, 'items' => array_values($items)];
    }

    private function item(VEvent $event, string $method): array
    {
        $sequence = (string) ($event->SEQUENCE ?? '0');
        if (! ctype_digit($sequence) || (float) $sequence > 2147483647 || ! isset($event->DTSTAMP) || ! preg_match('/^\d{8}T\d{6}Z$/', (string) $event->DTSTAMP)) {
            throw new RejectedMail('invalid_calendar');
        }
        $cancelled = $method === 'CANCEL' || strtoupper((string) $event->STATUS) === 'CANCELLED';
        $data = null;
        if (! $cancelled) {
            if (! $event->DTSTART instanceof CalendarDate || (isset($event->DTEND) && isset($event->DURATION))) {
                throw new RejectedMail('invalid_calendar');
            }
            $start = $this->date($event->DTSTART);
            $allDay = ! $event->DTSTART->hasTime();
            $end = isset($event->DTEND) ? $this->date($event->DTEND) : ($allDay ? $start->modify('+1 day') : $start);
            if (isset($event->DURATION)) {
                $end = $start->add($event->DURATION->getDateInterval());
            }
            if ($end < $start || ($allDay && $end <= $start) || (isset($event->DTEND) && $allDay === $event->DTEND->hasTime())) {
                throw new RejectedMail('invalid_calendar');
            }
            $title = MessageParser::plainText((string) $event->SUMMARY, 255);
            if ($title === '') {
                throw new RejectedMail('invalid_calendar');
            }
            $data = [
                'title' => $title, 'description' => MessageParser::plainText((string) $event->DESCRIPTION),
                'starts_at' => $start->format('Y-m-d H:i:s'), 'ends_at' => $end->format('Y-m-d H:i:s'),
                'all_day' => $allDay, 'location' => MessageParser::plainText((string) $event->LOCATION, 255),
                'status' => strtoupper((string) $event->STATUS) === 'TENTATIVE' ? 'planned' : 'confirmed',
            ];
        }

        // Compare revision stamps in UTC, including during the repeated hour at DST fall-back.
        return ['key' => $this->occurrence($event), 'sequence' => (int) $sequence, 'revision_at' => $this->date($event->DTSTAMP)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'cancelled' => $cancelled, 'data' => $data];
    }

    /** Restrict combinations before expanding untrusted rules; some legal but impossible rules never converge. */
    private function validateRule(array $rule): void
    {
        $frequency = $rule['FREQ'] ?? '';
        foreach (['FREQ', 'COUNT', 'UNTIL', 'INTERVAL', 'WKST'] as $scalar) {
            if (isset($rule[$scalar]) && ! is_scalar($rule[$scalar])) {
                throw new RejectedMail('unsupported_recurrence');
            }
        }
        $byKeys = array_values(array_filter(array_keys($rule), fn ($key) => str_starts_with($key, 'BY')));
        if (($frequency === 'DAILY' && ($byKeys && ($byKeys !== ['BYDAY'] || (int) ($rule['INTERVAL'] ?? 1) !== 1)))
            || ($frequency === 'WEEKLY' && array_diff($byKeys, ['BYDAY']))
            || ($frequency === 'MONTHLY' && isset($rule['BYMONTH']))
            || (isset($rule['BYDAY'], $rule['BYMONTHDAY']))
            || (isset($rule['BYSETPOS']) && ! isset($rule['BYDAY']))
            || ($frequency === 'YEARLY' && $byKeys && ! isset($rule['BYMONTH']))) {
            throw new RejectedMail('unsupported_recurrence');
        }
        foreach (['COUNT' => [1, 366], 'INTERVAL' => [1, 366], 'BYMONTH' => [1, 12], 'BYMONTHDAY' => [-28, 28], 'BYSETPOS' => [-4, 4]] as $key => [$min, $max]) {
            if (! isset($rule[$key])) {
                continue;
            }
            foreach ((array) $rule[$key] as $value) {
                $value = (string) $value;
                if (! preg_match('/^-?\d{1,3}$/', $value) || (int) $value === 0 || (int) $value < $min || (int) $value > $max) {
                    throw new RejectedMail('unsupported_recurrence');
                }
            }
        }
        if (isset($rule['BYDAY'])) {
            foreach ((array) $rule['BYDAY'] as $day) {
                if (! preg_match('/^(-?[1-4])?(MO|TU|WE|TH|FR|SA|SU)$/', $day, $match)
                    || (! empty($match[1]) && (in_array($frequency, ['DAILY', 'WEEKLY'], true) || isset($rule['BYSETPOS'])))) {
                    throw new RejectedMail('unsupported_recurrence');
                }
            }
        }
        if (isset($rule['WKST']) && ! in_array($rule['WKST'], ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'], true)) {
            throw new RejectedMail('unsupported_recurrence');
        }
    }

    private function occurrence(VEvent $event): string
    {
        if (! isset($event->{'RECURRENCE-ID'})) {
            return 'master';
        }
        $date = $event->{'RECURRENCE-ID'};

        return hash('sha256', $this->date($date)->format($date->hasTime() ? 'Y-m-d H:i:sP' : 'Y-m-d'));
    }

    private function date(CalendarDate $date): DateTimeImmutable
    {
        $value = (string) $date;
        if (! preg_match('/^(\d{4})(\d{2})(\d{2})(?:T([0-2]\d)([0-5]\d)([0-5]\d)Z?)?$/', $value, $parts)
            || $date->hasTime() !== str_contains($value, 'T')
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) || (isset($parts[4]) && (int) $parts[4] > 23)
            || (int) $parts[1] < 1900 || (int) $parts[1] > 2200) {
            throw new RejectedMail('invalid_calendar');
        }

        return $date->getDateTime(new DateTimeZone('Europe/Zurich'))->setTimezone(new DateTimeZone('Europe/Zurich'));
    }
}
