<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class VerifyCalendarSender extends Mailable
{
    public function __construct(public string $code) {}

    public function build(): self
    {
        return $this->subject('FamilyManager: Absenderadresse bestätigen')
            ->text('mail.verify-calendar-sender');
    }
}
