<?php

namespace App\Mail;

use App\Models\Article;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;

class NewArticleMail extends Mailable
{
    use Queueable, SerializesModels;

    public $articleTranslation;
    public $articleUrl;

    public function __construct(public Article $article, public User $user)
    {
        $this->articleTranslation = $article->translate($user->locale);
        $this->articleUrl = config('app.url') . '/' . $user->locale . '/' . ($article->category?->slug ?? 'articles') . '/' . $article->slug;
    }

    public function envelope(): Envelope
    {
        $title = $this->articleTranslation?->title ?? $this->article->slug;
        return new Envelope(
            from: new Address(
                config('mail.from.address', 'noreply@mail.devsense.work'),
                config('mail.from.name', 'DevSense')
            ),
            subject: __('emails.new_article.subject', ['title' => $title], $this->user->locale),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-article',
        );
    }
}
