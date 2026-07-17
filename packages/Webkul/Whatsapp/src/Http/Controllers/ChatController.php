<?php

namespace Webkul\Whatsapp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\PipelineProxy;
use Webkul\Lead\Models\SourceProxy;
use Webkul\Lead\Models\TypeProxy;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Product\Models\ProductProxy;
use Webkul\Whatsapp\Models\ConversationProxy;
use Webkul\Whatsapp\Models\MessageProxy;

/**
 * Standalone WhatsApp page: the conversation list pane. The thread itself is
 * served by InboxController (shared with the person/lead embedded inbox).
 */
class ChatController extends Controller
{
    /**
     * The two-pane chat page.
     */
    public function index()
    {
        return view('whatsapp::chat');
    }

    /**
     * Conversation list: searchable, filterable, ordered by recency.
     */
    public function conversations(Request $request): JsonResponse
    {
        $messageModel = MessageProxy::modelClass();

        $query = ConversationProxy::modelClass()::query()
            ->with('person:id,name')
            ->select('whatsapp_conversations.*')
            // One row per real contact. Old conversations from a decommissioned
            // gateway can carry the same number in another format (with/without
            // country code), so identity is the LAST 8 DIGITS of wa_phone (the
            // local number). Keep only the best: active gateway first, linked to
            // a person first, most recent first.
            ->joinSub(
                DB::table('whatsapp_conversations')->selectRaw(
                    "id as ranked_id, ROW_NUMBER() OVER (
                        PARTITION BY COALESCE(
                            NULLIF(RIGHT(REGEXP_REPLACE(COALESCE(wa_phone, ''), '[^0-9]', ''), 8), ''),
                            CONCAT('p', person_id),
                            CONCAT('c', id)
                        )
                        ORDER BY (gateway = ?) DESC, (person_id IS NOT NULL) DESC, last_message_at DESC, id DESC
                    ) as rn",
                    [(string) config('whatsapp.gateway')]
                ),
                'ranked',
                fn ($join) => $join->on('ranked.ranked_id', '=', 'whatsapp_conversations.id')
            )
            ->where('ranked.rn', 1)
            ->selectSub(
                $messageModel::query()->select('body')
                    ->whereColumn('conversation_id', 'whatsapp_conversations.id')
                    ->orderByDesc('id')->limit(1),
                'last_body'
            )
            ->selectSub(
                $messageModel::query()->select('direction')
                    ->whereColumn('conversation_id', 'whatsapp_conversations.id')
                    ->orderByDesc('id')->limit(1),
                'last_direction'
            );

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('wa_name', 'like', "%{$search}%")
                    ->orWhere('wa_phone', 'like', "%{$search}%")
                    ->orWhereHas('person', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        match ($request->input('filter')) {
            'unassigned' => $query->whereNull('person_id')->whereNull('lead_id'),
            'unread'     => $query->where('unread_count', '>', 0),
            default      => null,
        };

        $conversations = $query
            ->orderByRaw('last_message_at is null')
            ->orderByDesc('last_message_at')
            ->paginate(30);

        return response()->json([
            'data' => collect($conversations->items())->map(fn ($c) => [
                'id'           => $c->id,
                'name'         => $c->person->name ?? $c->wa_name ?? $c->wa_phone ?? 'Sin nombre',
                'phone'        => $c->wa_phone,
                'gateway'      => $c->gateway,
                'person_id'    => $c->person_id,
                'lead_id'      => $c->lead_id,
                'unread_count' => (int) $c->unread_count,
                'ai_enabled'   => $c->aiEffective(),
                'preview'      => $c->last_direction
                    ? ($c->last_direction === 'outbound' ? '↩ ' : '').Str::limit((string) $c->last_body, 60)
                    : null,
                'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            ]),
            'current_page' => $conversations->currentPage(),
            'last_page'    => $conversations->lastPage(),
        ]);
    }

    /**
     * Full client detail for the profile drawer: contact data, organization
     * and their leads, plus deep links into the CRM.
     */
    public function person(string $id): JsonResponse
    {
        $person = PersonProxy::modelClass()::with([
            'organization',
            'user',
            'tags',
            'leads' => fn ($q) => $q->with('stage')->orderByDesc('id')->limit(10),
        ])->findOrFail($id);

        // WhatsApp footprint of this contact (across all their conversations).
        $conversationIds = ConversationProxy::modelClass()::where('person_id', $person->id)->pluck('id');
        $messageModel = MessageProxy::modelClass();

        $firstMessageAt = $conversationIds->isNotEmpty()
            ? $messageModel::whereIn('conversation_id', $conversationIds)->min('created_at')
            : null;

        $lastInboundAt = $conversationIds->isNotEmpty()
            ? ConversationProxy::modelClass()::whereIn('id', $conversationIds)->max('last_inbound_at')
            : null;

        return response()->json([
            'person' => [
                'id'              => $person->id,
                'name'            => $person->name,
                'job_title'       => $person->job_title,
                'organization'    => $person->organization->name ?? null,
                'owner'           => $person->user->name ?? null,
                'tags'            => $person->tags->map(fn ($tag) => [
                    'name'  => $tag->name,
                    'color' => $tag->color ?? null,
                ])->values(),
                'emails'          => collect($person->emails ?? [])->pluck('value')->filter()->values(),
                'contact_numbers' => collect($person->contact_numbers ?? [])->pluck('value')->filter()->values(),
                'created_at'      => optional($person->created_at)->format('d/m/Y'),
                'view_url'        => route('admin.contacts.persons.view', $person->id),
                'whatsapp'        => [
                    'messages'      => $conversationIds->isNotEmpty()
                        ? $messageModel::whereIn('conversation_id', $conversationIds)->count()
                        : 0,
                    'first_contact' => $firstMessageAt ? \Illuminate\Support\Carbon::parse($firstMessageAt)->format('d/m/Y') : null,
                    'last_inbound'  => $lastInboundAt ? \Illuminate\Support\Carbon::parse($lastInboundAt)->format('d/m/Y H:i') : null,
                ],
                'leads_total'     => number_format((float) $person->leads->sum('lead_value'), 2),
                'leads'           => $person->leads->map(fn ($lead) => [
                    'id'         => $lead->id,
                    'title'      => $lead->title,
                    'stage'      => $lead->stage->name ?? null,
                    'value'      => $lead->lead_value ? number_format((float) $lead->lead_value, 2) : null,
                    'created_at' => optional($lead->created_at)->format('d/m/Y'),
                    'url'        => route('admin.leads.view', $lead->id),
                ])->values(),
            ],
        ]);
    }

    /**
     * Quick lead creation from the client profile drawer. Sensible defaults:
     * default pipeline + its first stage, "WhatsApp" as the source, first type.
     */
    public function storeLead(Request $request, string $personId, LeadRepository $leads): JsonResponse
    {
        $data = $request->validate([
            'title'      => 'required|string|max:191',
            'lead_value' => 'nullable|numeric|min:0',
        ]);

        $person = PersonProxy::modelClass()::findOrFail($personId);

        $pipeline = PipelineProxy::modelClass()::where('is_default', 1)->first()
            ?? PipelineProxy::modelClass()::firstOrFail();

        $stage = $pipeline->stages()->orderBy('sort_order')->firstOrFail();

        $source = SourceProxy::modelClass()::firstOrCreate(['name' => 'WhatsApp']);
        $type = TypeProxy::modelClass()::firstOrFail();

        Event::dispatch('lead.create.before');

        $lead = $leads->create([
            'entity_type'            => 'leads',
            'title'                  => $data['title'],
            'lead_value'             => $data['lead_value'] ?? 0,
            'person'                 => ['id' => $person->id],
            'lead_pipeline_id'       => $pipeline->id,
            'lead_pipeline_stage_id' => $stage->id,
            'lead_source_id'         => $source->id,
            'lead_type_id'           => $type->id,
            'status'                 => 1,
        ]);

        Event::dispatch('lead.create.after', $lead);

        return response()->json([
            'message' => 'Lead creado y vinculado al cliente.',
            'lead'    => ['id' => $lead->id, 'title' => $lead->title],
        ]);
    }

    /**
     * Product search for the {{producto}} / {{precio}} quick-reply variables.
     */
    public function products(Request $request): JsonResponse
    {
        $products = ProductProxy::modelClass()::query()
            ->when(trim((string) $request->input('search')), function ($q, $search) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'sku', 'price']);

        return response()->json(['products' => $products]);
    }

    /**
     * Create a Person from an unassigned conversation and link it, so the
     * "Sin asignar" tray can convert unknown numbers into CRM contacts.
     */
    public function linkPerson(Request $request, string $id, PersonRepository $persons): JsonResponse
    {
        $conversation = ConversationProxy::modelClass()::findOrFail($id);

        if ($conversation->person_id) {
            return response()->json(['message' => 'La conversación ya tiene un contacto asignado.'], 422);
        }

        $data = $request->validate(['name' => 'nullable|string|max:120']);

        $name = $data['name'] ?? $conversation->wa_name ?? $conversation->wa_phone;
        $digits = preg_replace('/\D+/', '', (string) $conversation->wa_phone);

        $person = $persons->create([
            'entity_type'     => 'persons',
            'name'            => $name,
            'emails'          => [],
            'contact_numbers' => $digits ? [['label' => 'work', 'value' => $digits]] : [],
        ]);

        $conversation->update(['person_id' => $person->id]);

        return response()->json([
            'message'   => 'Contacto creado y vinculado.',
            'person_id' => $person->id,
        ]);
    }
}
