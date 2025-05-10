<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Department;
use App\Models\Message;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

class TicketService
{
    public function createTicket(array $data, $userId)
    {
        $data['user_id'] = $userId;
        $data['attachment'] = $data['attachment'] ?? null;

        if (isset($data['attachment'])) {
            $data['attachment'] = $data['attachment']->store('tickets', 'public');
        }

        $ticket = Ticket::create($data);

        $this->notifySupport($ticket);

        return $ticket;
    }

    public function updateTicket($id, array $data)
    {
        $ticket = Ticket::findOrFail($id);

        if (isset($data['attachment'])) {
            if ($ticket->attachment) {
                Storage::disk('public')->delete($ticket->attachment);
            }
            $data['attachment'] = $data['attachment']->store('tickets', 'public');
        }

        $ticket->update($data);

        $this->notifySupport($ticket);

        return $ticket;
    }

    public function assignTicket($id, $userId)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->update(['assigned_to' => $userId]);
        $this->notifyAssignedUser($ticket);
        return $ticket;
    }

    public function sendMessage($ticketId, $userId, $content)
    {
        $ticket = Ticket::findOrFail($ticketId);
        $message = Message::create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'content' => $content,
        ]);

        $this->notifyParticipants($ticket);

        return $message;
    }

    protected function notifySupport($ticket)
    {
        Mail::raw("New ticket #{$ticket->id} updated: {$ticket->title}", function ($message) use ($ticket) {
            $message->to('support@yourdomain.com')->subject('Ticket Update');
        });
    }

    protected function notifyAssignedUser($ticket)
    {
        if ($ticket->assignedUser) {
            Mail::raw("You are assigned to ticket #{$ticket->id}: {$ticket->title}", function ($message) use ($ticket) {
                $message->to($ticket->assignedUser->email)->subject('Ticket Assignment');
            });
        }
    }

    protected function notifyParticipants($ticket)
    {
        $emails = [$ticket->user->email];
        if ($ticket->assignedUser) {
            $emails[] = $ticket->assignedUser->email;
        }
        Mail::raw("New message in ticket #{$ticket->id}: {$ticket->title}", function ($message) use ($emails) {
            $message->to($emails)->subject('Ticket Message');
        });
    }
}