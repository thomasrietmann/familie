<?php

namespace App\Services\CalendarMail;

use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\MailMimeParser;

class MessageParser
{
    public function parse(string $raw): IMessage
    {
        if (strlen($raw) > config('calendar_mail.max_bytes')) {
            throw new RejectedMail('too_large');
        }

        return (new MailMimeParser(null, [
            'maxMimePartDepth' => 12, 'maxMessagePartCount' => 50,
            'maxHeaderCount' => 100, 'maxHeaderSizeBytes' => 65536,
            'maxMessageHeaderCount' => 500, 'maxMessageHeaderSizeBytes' => 131072,
            'maxHeaderTokenCount' => 2000, 'maxMessageHeaderTokenCount' => 10000,
        ]))->parse($raw, false);
    }

    public function sender(IMessage $message): string
    {
        $headers = $message->getAllHeadersByName('From');
        if (count($headers) !== 1 || ! $headers[0] instanceof AddressHeader || count($headers[0]->getAddresses()) !== 1) {
            throw new RejectedMail('authentication_failed');
        }
        $sender = strtolower($headers[0]->getEmail() ?? '');
        if (! filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            throw new RejectedMail('authentication_failed');
        }

        return $sender;
    }

    public function replyTo(IMessage $message): ?string
    {
        $headers = $message->getAllHeadersByName('Reply-To');
        if ($headers === []) {
            return null;
        }
        if (count($headers) !== 1 || ! $headers[0] instanceof AddressHeader || count($headers[0]->getAddresses()) !== 1) {
            throw new RejectedMail('ambiguous_sender');
        }
        $email = strtolower($headers[0]->getEmail() ?? '');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RejectedMail('ambiguous_sender');
        }

        return $email;
    }

    public function authenticate(IMessage $message, string $sender): void
    {
        if (! config('calendar_mail.require_authentication', true)) {
            return;
        }
        $trusted = array_map('strtolower', config('calendar_mail.trusted_authserv_ids', []));
        $results = [];
        foreach ($message->getAllHeadersByName('Authentication-Results') as $header) {
            $clauses = $this->clauses($header->getRawValue());
            $server = strtolower(trim(array_shift($clauses) ?? ''));
            if (in_array($server, $trusted, true)) {
                $results[] = $clauses;
            }
        }
        // Multiple local assertions are ambiguous; never fall back to SPF alone or an untrusted header.
        if (count($results) !== 1) {
            throw new RejectedMail('authentication_failed');
        }
        $dmarc = array_values(array_filter($results[0], fn ($clause) => preg_match('/^dmarc\s*=/i', $clause)));
        if (count($dmarc) === 1 && preg_match('/^dmarc\s*=\s*pass(?:\s|$)/i', $dmarc[0])) {
            preg_match_all('/([a-z][a-z0-9_.-]*)\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^\s]+)/i', $dmarc[0], $properties, PREG_SET_ORDER);
            $domains = [];
            foreach ($properties as $property) {
                if (strtolower($property[1]) === 'header.from') {
                    $domains[] = strtolower(trim($property[2], '"'));
                }
            }
            if ($domains === [substr(strrchr($sender, '@'), 1)]) {
                return;
            }
        }
        throw new RejectedMail('authentication_failed');
    }

    /** Split at semicolons outside comments and quoted values (RFC 8601). */
    private function clauses(string $value): array
    {
        $clauses = [];
        $buffer = '';
        $depth = 0;
        $quoted = false;
        $escaped = false;
        foreach (str_split($value) as $char) {
            if ($escaped) {
                if ($depth === 0) {
                    $buffer .= '\\'.$char;
                }
                $escaped = false;
            } elseif ($char === '\\' && ($quoted || $depth > 0)) {
                $escaped = true;
            } elseif ($char === '"' && $depth === 0) {
                $quoted = ! $quoted;
                $buffer .= $char;
            } elseif (! $quoted && $char === '(') {
                $depth++;
                $buffer .= ' ';
            } elseif (! $quoted && $char === ')' && $depth > 0) {
                $depth--;
            } elseif ($depth === 0 && ! $quoted && $char === ';') {
                $clauses[] = trim($buffer);
                $buffer = '';
            } elseif ($depth === 0) {
                $buffer .= $char;
            }
        }
        if ($depth !== 0 || $quoted || $escaped) {
            throw new RejectedMail('authentication_failed');
        }
        $clauses[] = trim($buffer);

        return $clauses;
    }

    public function invitation(IMessage $message): array
    {
        $calendars = [];
        foreach ($message->getAllParts() as $part) {
            $type = strtolower($part->getContentType() ?? '');
            $filename = $part->getFilename() ?? '';
            if (in_array($type, ['text/calendar', 'application/ics', 'application/icalendar'], true) || ($type === 'application/octet-stream' && str_ends_with(strtolower($filename), '.ics'))) {
                $content = $part->getContent();
                if (is_string($content)) {
                    $calendars[hash('sha256', $content)] = $content;
                }
            }
        }
        if ($calendars === []) {
            throw new RejectedMail('no_calendar');
        }
        if (count($calendars) !== 1) {
            throw new RejectedMail('invalid_calendar');
        }

        return [
            'calendar' => array_values($calendars)[0],
            'subject' => mb_substr($message->getHeaderValue('Subject', ''), 0, 500),
            'body' => self::plainText($message->getTextContent() ?? $message->getHtmlContent() ?? '', 12000),
        ];
    }

    public static function plainText(string $text, int $limit = 12000): string
    {
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $text) ?? '';
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? ''), 0, $limit);
    }
}
