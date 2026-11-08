<?php

namespace App\Services\CalendarMail;

use App\Models\CalendarMailEvent;
use App\Models\CalendarMailReceipt;
use App\Models\CalendarSenderAddress;
use App\Models\Family;
use App\Models\FamilyEvent;
use Illuminate\Support\Facades\DB;

class InvitationImporter
{
    public function __construct(private MessageParser $messages, private InvitationParser $calendars, private PersonAssigner $assigner) {}

    public function import(string $raw, CalendarMailReceipt $receipt): string
    {
        $message = $this->messages->parse($raw);
        $email = $this->messages->sender($message);
        $replyTo = $this->messages->replyTo($message);
        $candidates = CalendarSenderAddress::whereIn('email', array_filter([$email, $replyTo]))->whereNotNull('verified_at')->get();
        if ($candidates->map(fn ($address) => $address->user_id.':'.$address->family_id)->unique()->count() > 1) {
            throw new RejectedMail('ambiguous_sender');
        }
        $sender = $candidates->firstWhere('email', $email) ?? $candidates->firstWhere('email', $replyTo);
        if (! $sender || ! $sender->user->activeFamilies()->whereKey($sender->family_id)->exists()) {
            throw new RejectedMail('unknown_sender');
        }
        $this->messages->authenticate($message, $email);
        // DMARC authenticates From, not Reply-To. Cross-address routing needs an explicitly trusted provider.
        if ($sender->email !== $email && config('calendar_mail.require_authentication', true)
            && ! in_array($email, config('calendar_mail.reply_to_senders', []), true)) {
            throw new RejectedMail('authentication_failed');
        }
        $receipt->update(['family_id' => $sender->family_id, 'user_id' => $sender->user_id]);
        $mail = $this->messages->invitation($message);
        $invitation = $this->calendars->parse($mail['calendar'], $email);
        $family = $sender->family->load(['activeParents', 'children']);
        $records = CalendarMailEvent::where('family_id', $family->id)->where('series_key', $invitation['series_key'])->get()->keyBy('occurrence_key');
        if ($records->contains(fn ($record) => $record->user_id !== $sender->user_id)) {
            return 'unchanged';
        }
        $master = $records->get('master');
        $gateMaster = $invitation['master'] && $this->mayUpdate($master, null, $invitation['master'], $sender->user_id) ? null : $master;
        $assignments = [];
        $assignment = null;
        foreach ($invitation['items'] as $item) {
            $existing = $records->get($item['key']);
            if ($item['cancelled'] || ! $this->mayUpdate($existing, $gateMaster, $item, $sender->user_id)) {
                continue;
            }
            // One invitation/UID has one assignment, including its recurring occurrences.
            // This bounds external requests to one per mail regardless of series size.
            $assignment ??= $this->assigner->assign($family, $mail['subject'], $mail['body'], $invitation['master']['data'] ?? $item['data']);
            $assignments[$item['key']] = $assignment;
        }

        return DB::transaction(function () use ($sender, $family, $invitation, $assignments): string {
            Family::whereKey($family->id)->lockForUpdate()->firstOrFail();
            $currentSender = CalendarSenderAddress::whereKey($sender->id)->lockForUpdate()->first();
            if (! $currentSender?->verified_at || ! $currentSender->user->activeFamilies()->whereKey($family->id)->exists()) {
                throw new RejectedMail('unknown_sender');
            }
            $records = CalendarMailEvent::where('family_id', $family->id)->where('series_key', $invitation['series_key'])->lockForUpdate()->get()->keyBy('occurrence_key');
            if ($records->contains(fn ($record) => $record->user_id !== $sender->user_id)) {
                return 'unchanged';
            }
            $master = $records->get('master');
            $gateMaster = $invitation['master'] && $this->mayUpdate($master, null, $invitation['master'], $sender->user_id) ? null : $master;
            $changed = false;
            $cancelled = false;
            $incomingMaster = $invitation['master'];
            // A full-series revision also cancels removed instances; newer exceptions win.
            if ($incomingMaster && $this->mayUpdate($master, null, $incomingMaster, $sender->user_id)) {
                $keys = array_column($invitation['items'], 'key');
                foreach ($records as $record) {
                    if ($record->occurrence_key === 'master') {
                        continue;
                    }
                    if (($incomingMaster['cancelled'] || ! in_array($record->occurrence_key, $keys, true)) && $this->newer($incomingMaster, $record)) {
                        $this->saveItem($record, $sender, $invitation['series_key'], array_replace($incomingMaster, ['key' => $record->occurrence_key, 'cancelled' => true]), null);
                        $changed = $cancelled = true;
                    }
                }
            }
            foreach ($invitation['items'] as $item) {
                $existing = $records->get($item['key']);
                if (! $this->mayUpdate($existing, $gateMaster, $item, $sender->user_id)) {
                    continue;
                }
                $assignment = $assignments[$item['key']] ?? null;
                if (! $item['cancelled'] && $assignment === null) {
                    continue;
                }
                if ($assignment) {
                    // Membership may have changed while OpenAI was running.
                    $valid = match ($assignment['owner_type']) {
                        'user' => $family->activeParents()->whereKey($assignment['owner_id'])->exists(),
                        'child' => $family->children()->whereKey($assignment['owner_id'])->exists(),
                        default => true,
                    };
                    if (! $valid) {
                        $assignment = ['owner_type' => 'family', 'owner_id' => null];
                    }
                }
                $this->saveItem($existing, $sender, $invitation['series_key'], $item, $assignment);
                $changed = true;
                $cancelled = $cancelled || $item['cancelled'];
            }
            if ($invitation['recurring'] && $incomingMaster && $this->mayUpdate($master, null, $incomingMaster, $sender->user_id)) {
                // If a single appointment becomes a series, retire its previous standalone event.
                $master?->event?->update(['status' => 'cancelled']);
                $marker = array_replace($incomingMaster, ['data' => null]);
                $this->saveItem($master, $sender, $invitation['series_key'], $marker, null);
                $changed = true;
            }

            return ! $changed ? 'unchanged' : ($cancelled ? 'cancelled' : 'imported');
        });
    }

    private function mayUpdate(?CalendarMailEvent $record, ?CalendarMailEvent $master, array $item, int $userId): bool
    {
        if (($record && $record->user_id !== $userId) || ($master && $master->user_id !== $userId)) {
            return false;
        }
        if ($master && $item['key'] !== 'master' && ($master->cancelled || ! $this->newer($item, $master))) {
            return false;
        }

        return ! $record || $this->newer($item, $record);
    }

    private function newer(array $item, CalendarMailEvent $record): bool
    {
        return $item['sequence'] > $record->sequence
            || ($item['sequence'] === $record->sequence && $item['revision_at'] > $record->revision_at->format('Y-m-d H:i:s'));
    }

    private function saveItem(?CalendarMailEvent $record, CalendarSenderAddress $sender, string $seriesKey, array $item, ?array $assignment): void
    {
        $record ??= new CalendarMailEvent(['family_id' => $sender->family_id, 'user_id' => $sender->user_id, 'series_key' => $seriesKey, 'occurrence_key' => $item['key']]);
        $event = $record->event;
        if ($item['cancelled']) {
            $event?->update(['status' => 'cancelled']);
        } elseif ($item['data'] && $assignment) {
            if ($event) {
                $event->update($item['data'] + $assignment);
            } elseif (! $record->was_imported) {
                $event = FamilyEvent::create($item['data'] + $assignment + [
                    'family_id' => $sender->family_id, 'source' => 'email', 'category' => 'other', 'visibility' => 'parents_only',
                ]);
                $record->was_imported = true;
            }
            // A deliberately deleted event is a tombstone and is not recreated by re-delivery.
        }
        $record->fill(['family_event_id' => $event?->id, 'sequence' => $item['sequence'], 'revision_at' => $item['revision_at'], 'cancelled' => $item['cancelled']])->save();
    }
}
