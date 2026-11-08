<?php

namespace App\Services\CalendarMail;

use RuntimeException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;

class ImapMailbox
{
    private ?Client $client = null;

    private ImapProtocol $connection;

    public function connect(): string
    {
        $this->client = (new ClientManager)->make([
            'host' => config('calendar_mail.host'), 'port' => config('calendar_mail.port'),
            'username' => config('calendar_mail.username'), 'password' => config('calendar_mail.password'),
            'protocol' => 'imap', 'encryption' => 'ssl', 'validate_cert' => true, 'timeout' => 15,
        ]);
        $this->client->connect();
        $connection = $this->client->getConnection();
        if (! $connection instanceof ImapProtocol) {
            throw new RuntimeException('Unsupported mailbox transport');
        }
        $this->connection = $connection;
        // EXAMINE and BODY.PEEK: no read flags, moves, deletions or automatic invitation replies.
        $info = $this->connection->examineFolder(config('calendar_mail.folder'))->validatedData();
        if (empty($info['uidvalidity'])) {
            throw new RuntimeException('Mailbox identity missing');
        }

        return hash('sha256', implode('|', [config('calendar_mail.host'), config('calendar_mail.username'), config('calendar_mail.folder'), $info['uidvalidity']]));
    }

    public function uids(): array
    {
        $uids = $this->connection->search(['SINCE', now()->subDays(config('calendar_mail.lookback_days'))->format('d-M-Y')])->validatedData();
        $uids = array_map('intval', $uids);
        sort($uids, SORT_NUMERIC);

        return $uids;
    }

    public function read(int $uid): string
    {
        $sizes = $this->connection->sizes([$uid])->validatedData();
        if (! isset($sizes[$uid])) {
            throw new RuntimeException('Message unavailable');
        }
        if ((int) $sizes[$uid] > config('calendar_mail.max_bytes')) {
            throw new RejectedMail('too_large');
        }
        // Multiple requested attributes preserve the response map, including BODY[] after PEEK.
        $data = $this->connection->fetch(['UID', 'BODY.PEEK[]'], [$uid])->validatedData();
        $raw = $data[$uid]['BODY[]'] ?? null;
        if (! is_string($raw)) {
            throw new RuntimeException('Message unavailable');
        }

        return $raw;
    }

    public function disconnect(): void
    {
        $this->client?->disconnect();
    }
}
