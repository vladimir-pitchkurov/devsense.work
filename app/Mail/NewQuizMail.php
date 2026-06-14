<?php

namespace App\Mail;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;

class NewQuizMail extends Mailable
{
    use Queueable, SerializesModels;

    public $quizTranslation;
    public $quizUrl;

    public function __construct(public Quiz $quiz, public User $user)
    {
        $this->quizTranslation = $quiz->translate($user->locale);
        $this->quizUrl = config('app.url') . '/' . $user->locale . '/quizzes/' . $quiz->slug;
    }

    public function envelope(): Envelope
    {
        $title = $this->quizTranslation?->title ?? $this->quiz->slug;
        return new Envelope(
            from: new Address(
                config('mail.from.address', 'noreply@mail.devsense.work'),
                config('mail.from.name', 'DevSense')
            ),
            subject: __('emails.new_quiz.subject', ['title' => $title], $this->user->locale),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-quiz',
        );
    }
}
