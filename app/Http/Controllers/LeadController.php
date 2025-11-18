<?php

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Lead;
use App\Models\LeadReminder;
use App\Support\AuditLogger;
use App\Support\Auth;

class LeadController extends Controller
{
    public function index(): array
    {
        return ['data' => Lead::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'officer_id' => 'required',
            'name' => 'required',
            'contact' => 'required',
            'status' => 'in:lead,prospect,investor',
        ])) {
            return $response;
        }

        $payload = $request->all();
        $payload['status'] = $payload['status'] ?? 'lead';
        $lead = Lead::create($payload);
        AuditLogger::log(Auth::user(), 'create', 'leads', 'lead', (int)$lead['id'], $payload, $request->ip(), $request->userAgent());
        return $this->json(['data' => $lead], 201);
    }

    public function show(Request $request, array $params)
    {
        $lead = Lead::find((int)$params['id']);
        if (!$lead) {
            return $this->json(['message' => 'Lead not found'], 404);
        }

        return $this->json(['data' => $lead]);
    }

    public function update(Request $request, array $params)
    {
        $lead = Lead::find((int)$params['id']);
        if (!$lead) {
            return $this->json(['message' => 'Lead not found'], 404);
        }

        $payload = array_merge($lead, $request->all());
        $updated = Lead::update((int)$params['id'], $payload);
        AuditLogger::log(Auth::user(), 'update', 'leads', 'lead', (int)$params['id'], $payload, $request->ip(), $request->userAgent());
        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = Lead::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Lead not found'], 404);
        }

        AuditLogger::log(Auth::user(), 'delete', 'leads', 'lead', (int)$params['id'], [], $request->ip(), $request->userAgent());
        return $this->json(['message' => 'Lead deleted']);
    }

    public function convertToProspect(Request $request, array $params)
    {
        $lead = Lead::find((int)$params['id']);
        if (!$lead) {
            return $this->json(['message' => 'Lead not found'], 404);
        }

        $updated = Lead::update((int)$lead['id'], ['status' => 'prospect', 'last_contacted_at' => date('Y-m-d H:i:s')]);
        AuditLogger::log(Auth::user(), 'convert_prospect', 'leads', 'lead', (int)$lead['id'], [], $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    public function convertToInvestor(Request $request, array $params)
    {
        $lead = Lead::find((int)$params['id']);
        if (!$lead) {
            return $this->json(['message' => 'Lead not found'], 404);
        }

        $payload = ['status' => 'investor', 'last_contacted_at' => date('Y-m-d H:i:s')];
        $customerId = $request->input('customer_id') ?? $request->input('investor_id');
        if ($customerId) {
            $payload['customer_id'] = (int)$customerId;
        }

        $updated = Lead::update((int)$lead['id'], $payload);
        AuditLogger::log(Auth::user(), 'convert_investor', 'leads', 'lead', (int)$lead['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    public function upcomingReminders(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT lr.*, l.name as lead_name FROM lead_reminders lr JOIN leads l ON l.id = lr.lead_id WHERE lr.status = "pending" AND lr.remind_at >= :now ORDER BY lr.remind_at ASC');
        $stmt->execute(['now' => date('Y-m-d H:i:s')]);

        return ['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function storeReminder(Request $request, array $params)
    {
        $lead = Lead::find((int)$params['id']);
        if (!$lead) {
            return $this->json(['message' => 'Lead not found'], 404);
        }

        if ($response = $this->validate($request, [
            'remind_at' => 'required|date',
            'note' => 'required',
        ])) {
            return $response;
        }

        $payload = $request->all();
        $payload['lead_id'] = $lead['id'];
        $payload['status'] = $payload['status'] ?? 'pending';
        $reminder = LeadReminder::create($payload);

        AuditLogger::log(Auth::user(), 'create', 'leads', 'lead_reminder', (int)$reminder['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $reminder], 201);
    }

    public function conversionReport(): array
    {
        $pdo = Database::connection();
        $totalLeads = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'lead'")->fetchColumn();
        $prospects = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'prospect'")->fetchColumn();
        $investors = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'investor'")->fetchColumn();

        return ['data' => [
            'total_leads' => $totalLeads,
            'prospects' => $prospects,
            'investors' => $investors,
        ]];
    }

    public function officerSummary(): array
    {
        $pdo = Database::connection();
        $sql = 'SELECT officer_id, COUNT(*) as total_leads, SUM(CASE WHEN status = "investor" THEN 1 ELSE 0 END) as investors FROM leads GROUP BY officer_id';
        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return ['data' => $rows];
    }
}
