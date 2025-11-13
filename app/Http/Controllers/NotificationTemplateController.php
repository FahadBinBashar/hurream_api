<?php

namespace App\Http\Controllers;

use App\Core\Request;
use App\Models\NotificationTemplate;
use App\Support\AuditLogger;
use App\Support\Auth;
use App\Support\NotificationRenderer;
use App\Support\Validator;

class NotificationTemplateController extends Controller
{
    public function index(): array
    {
        return ['data' => NotificationTemplate::all()];
    }

    public function store(Request $request)
    {
        if ($response = $this->validate($request, [
            'code' => 'required|unique:notification_templates,code',
            'channel' => 'required|in:sms,email',
            'subject' => '',
            'body' => 'required',
            'placeholders' => '',
            'status' => 'in:active,inactive',
        ])) {
            return $response;
        }

        $payload = $request->all();
        $payload['status'] = $payload['status'] ?? 'active';
        $template = NotificationTemplate::create($payload);

        AuditLogger::log(Auth::user(), 'create', 'notification_templates', 'notification_template', (int)$template['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $template], 201);
    }

    public function show(Request $request, array $params)
    {
        $template = NotificationTemplate::find((int)$params['id']);
        if (!$template) {
            return $this->json(['message' => 'Template not found'], 404);
        }

        return $this->json(['data' => $template]);
    }

    public function update(Request $request, array $params)
    {
        $template = NotificationTemplate::find((int)$params['id']);
        if (!$template) {
            return $this->json(['message' => 'Template not found'], 404);
        }

        if ($response = $this->validate($request, [
            'code' => 'required|unique:notification_templates,code,' . $template['id'],
            'channel' => 'required|in:sms,email',
            'subject' => '',
            'body' => 'required',
            'placeholders' => '',
            'status' => 'in:active,inactive',
        ])) {
            return $response;
        }

        $payload = array_merge($template, $request->all());
        $updated = NotificationTemplate::update((int)$params['id'], $payload);

        AuditLogger::log(Auth::user(), 'update', 'notification_templates', 'notification_template', (int)$params['id'], $payload, $request->ip(), $request->userAgent());

        return $this->json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params)
    {
        $deleted = NotificationTemplate::delete((int)$params['id']);
        if (!$deleted) {
            return $this->json(['message' => 'Template not found'], 404);
        }

        AuditLogger::log(Auth::user(), 'delete', 'notification_templates', 'notification_template', (int)$params['id'], [], $request->ip(), $request->userAgent());

        return $this->json(['message' => 'Template deleted']);
    }

    public function render(Request $request, array $params)
    {
        $template = NotificationTemplate::find((int)$params['id']);
        if (!$template) {
            return $this->json(['message' => 'Template not found'], 404);
        }

        $data = $request->all();
        $body = NotificationRenderer::render($template['body'], $data['data'] ?? []);

        return $this->json([
            'subject' => $template['subject'],
            'body' => $body,
        ]);
    }
}
