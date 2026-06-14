<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;

class NewCommentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $articleTranslation;
    public $actionUrl;

    public function __construct(
        public User $recipient,
        public User $triggerUser,
        public string $type,
        public string $commentContent,
        public Article $article
    ) {
        $this->articleTranslation = $article->translate($recipient->locale);
        $this->actionUrl = config('app.url') . '/' . $recipient->locale . '/suggestions';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address', 'noreply@mail.devsense.work'),
                config('mail.from.name', 'DevSense')
            ),
            subject: __('emails.new_comment.subject', [], $this->recipient->locale),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-comment',
        );
    }
}
