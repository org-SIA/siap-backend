<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AlerteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $sujet, public string $corps)
    {
    }

    public function build(): self
    {
        return $this->subject($this->sujet)->text('emails.alerte', ['corps' => $this->corps]);
    }
}
