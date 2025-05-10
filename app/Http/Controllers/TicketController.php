<?php

namespace App\Http\Controllers;

use App\Services\TicketService;
use App\Models\Department;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    protected $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    public function index()
    {
        $tickets = Ticket::with(['user', 'department', 'assignedUser', 'messages'])->get();
        return response()->json($tickets);
    }

    public function departments()
    {
        $departments = Department::all();
        return response()->json($departments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high',
            'attachment' => 'nullable|file',
        ]);

        $ticket = $this->ticketService->createTicket($data, $request->user()->id);
        return response()->json(['message' => 'Ticket created', 'ticket' => $ticket], 201);
    }

    public function show($id)
    {
        $ticket = Ticket::with(['user', 'department', 'assignedUser', 'messages'])->findOrFail($id);
        return response()->json($ticket);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'department_id' => 'sometimes|exists:departments,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'priority' => 'sometimes|in:low,medium,high',
            'status' => 'sometimes|in:open,in_progress,closed',
            'attachment' => 'nullable|file',
        ]);

        $ticket = $this->ticketService->updateTicket($id, $data);
        return response()->json(['message' => 'Ticket updated', 'ticket' => $ticket]);
    }

    public function assign(Request $request, $id)
    {
        $data = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket = $this->ticketService->assignTicket($id, $data['assigned_to']);
        return response()->json(['message' => 'Ticket assigned', 'ticket' => $ticket]);
    }

    public function sendMessage(Request $request, $id)
    {
        $data = $request->validate([
            'content' => 'required|string',
        ]);

        $message = $this->ticketService->sendMessage($id, $request->user()->id, $data['content']);
        return response()->json(['message' => 'Message sent', 'messages' => $message], 201);
    }

    public function destroy($id)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->delete();
        return response()->json(['message' => 'Ticket deleted']);
    }
}