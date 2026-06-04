<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Mail\SupportTicketReplyMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminTicketsController extends Controller
{
    /**
     * Display a listing of support tickets.
     */
    public function index(Request $request)
    {
        $status = $request->query('status'); // open, answered
        $type = $request->query('type'); // complaint, suggestion, general

        $query = SupportTicket::query();

        if (in_array($status, ['open', 'answered'])) {
            $query->where('status', $status);
        }

        if (in_array($type, ['complaint', 'suggestion', 'general'])) {
            $query->where('type', $type);
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return view('admin.tickets.index', compact('tickets'));
    }

    /**
     * Show the ticket and the reply form.
     */
    public function show(SupportTicket $ticket)
    {
        return view('admin.tickets.show', compact('ticket'));
    }

    /**
     * Submit a reply to the ticket and send the email.
     */
    public function reply(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'reply_message' => ['required', 'string', 'min:5'],
        ]);

        $ticket->update([
            'reply_message' => $request->reply_message,
            'status'        => 'answered',
            'replied_at'    => now(),
        ]);

        // Send the email reply using Resend
        Mail::to($ticket->sender_email)->send(new SupportTicketReplyMail($ticket));

        return redirect()->route('admin.tickets.index', ['locale' => app()->getLocale()])
            ->with('success', 'Reply sent successfully to ' . $ticket->sender_email);
    }
}
