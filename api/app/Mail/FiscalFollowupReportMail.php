<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FiscalFollowupReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $pdfContent;
    public $filename;

    /**
     * Create a new message instance.
     */
    public function __construct($pdfContent, $filename)
    {
        $this->pdfContent = $pdfContent;
        $this->filename = $filename;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Follow-Up Reminder: Close Fiscal Day')
            ->markdown('emails.fiscal_followup')
            ->attachData($this->pdfContent, $this->filename, [
                'mime' => 'application/pdf',
            ]);
    }
}
