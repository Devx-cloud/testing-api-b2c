<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaConversation;
use App\Models\WaCustomer;
use App\Services\Interfaces\WaMemoryServiceInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Endpoint memori percakapan untuk WhatsApp AI Agent. Semua di bawah middleware
 * 'auth.apikey' (header 'Token'). Klien satu-satunya adalah proses agent Python.
 */
class WaMemoryController extends Controller
{
    protected $waMemoryService;

    public function __construct(WaMemoryServiceInterface $waMemoryService)
    {
        $this->waMemoryService = $waMemoryService;
    }

    /**
     * POST /api/wa/customers/resolve
     */
    public function resolveCustomer(Request $request)
    {
        $data = $request->validate([
            'phone_number' => 'required_without:jid|nullable|string|max:32',
            'jid' => 'required_without:phone_number|nullable|string|max:191',
            'user_id' => 'nullable|integer',
            'display_name' => 'nullable|string|max:255',
        ]);

        try {
            return response()->json($this->waMemoryService->resolveCustomer($data));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Error saat resolve wa_customer: ' . $e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    /**
     * GET /api/wa/conversations/{conversation}/context
     */
    public function context(Request $request, WaConversation $conversation)
    {
        $request->validate([
            'char_budget' => 'nullable|integer|min:500|max:200000',
            'recent_turns' => 'nullable|integer|min:1|max:200',
            'for_summary' => 'nullable|boolean',
        ]);

        return response()->json($this->waMemoryService->getContext(
            $conversation->id,
            (int) $request->input('char_budget', config('services.wa_memory.default_char_budget', 6000)),
            (int) $request->input('recent_turns', config('services.wa_memory.default_recent_turns', 12)),
            $request->boolean('for_summary')
        ));
    }

    /**
     * POST /api/wa/conversations/{conversation}/messages
     */
    public function appendMessages(Request $request, WaConversation $conversation)
    {
        $request->validate([
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|string|in:user,assistant,tool,system_note',
            'messages.*.content' => 'nullable|string',
            'messages.*.tool_calls' => 'nullable|array',
            'messages.*.tool_call_id' => 'nullable|string|max:64',
            'messages.*.name' => 'nullable|string|max:64',
            'messages.*.token_estimate' => 'nullable|integer|min:0',
            'touch_last_active' => 'nullable|boolean',
        ]);

        try {
            $result = $this->waMemoryService->appendMessages(
                $conversation->id,
                $request->input('messages'),
                $request->boolean('touch_last_active', true)
            );

            return response()->json($result, 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Error saat append wa_message: ' . $e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    /**
     * PUT /api/wa/conversations/{conversation}/summary
     */
    public function putSummary(Request $request, WaConversation $conversation)
    {
        $request->validate([
            'summary' => 'required|string',
            'up_to_message_id' => 'nullable|integer|min:1',
        ]);

        try {
            $result = $this->waMemoryService->putSummary(
                $conversation->id,
                $request->input('summary'),
                $request->input('up_to_message_id') !== null ? (int) $request->input('up_to_message_id') : null
            );

            return response()->json($result);
        } catch (Exception $e) {
            Log::error('Error saat menyimpan ringkasan percakapan: ' . $e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    /**
     * GET /api/wa/customers/{customer}/facts
     */
    public function facts(WaCustomer $customer)
    {
        return response()->json(['facts' => $this->waMemoryService->getFacts($customer->id)]);
    }

    /**
     * POST /api/wa/customers/{customer}/facts
     */
    public function upsertFacts(Request $request, WaCustomer $customer)
    {
        $request->validate([
            'facts' => 'required|array|min:1',
            'facts.*.key' => 'required|string|max:64',
            'facts.*.value' => 'present|string',
            'facts.*.source' => 'nullable|string|max:24',
        ]);

        try {
            return response()->json([
                'facts' => $this->waMemoryService->upsertFacts($customer->id, $request->input('facts')),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Error saat upsert wa_customer_fact: ' . $e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    /**
     * GET /api/wa/customers/{customer}/session-state
     */
    public function getSessionState(WaCustomer $customer)
    {
        $state = $this->waMemoryService->getSessionState($customer->id);

        return response()->json($state ?? [
            'conversation_id' => null,
            'checkout_state' => null,
            'matched_user_cache' => null,
            'matched_user_cached_at' => null,
            'rate_limit' => null,
            'extra' => null,
            'updated_at' => null,
        ]);
    }

    /**
     * PUT /api/wa/customers/{customer}/session-state
     */
    public function putSessionState(Request $request, WaCustomer $customer)
    {
        $data = $request->validate([
            'conversation_id' => 'nullable|integer',
            'checkout_state' => 'nullable|array',
            'matched_user_cache' => 'nullable|array',
            'matched_user_cached_at' => 'nullable|date',
            'rate_limit' => 'nullable|array',
            'extra' => 'nullable|array',
        ]);

        try {
            return response()->json($this->waMemoryService->putSessionState($customer->id, $data));
        } catch (Exception $e) {
            Log::error('Error saat menyimpan wa_session_state: ' . $e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    /**
     * POST /api/wa/customers/{customer}/embeddings
     */
    public function storeEmbedding(Request $request, WaCustomer $customer)
    {
        $data = $request->validate([
            'message_id' => 'nullable|integer',
            'conversation_id' => 'required|integer',
            'kind' => 'required|string|in:message,summary',
            'model' => 'required|string|max:64',
            'dim' => 'required|integer|min:1',
            'vector' => 'required|array|min:1',
            'vector.*' => 'numeric',
            'content_preview' => 'nullable|string',
        ]);

        try {
            return response()->json($this->waMemoryService->storeEmbedding($customer->id, $data), 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Error saat menyimpan wa_message_embedding: ' . $e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    /**
     * POST /api/wa/customers/{customer}/embeddings/query
     */
    public function queryEmbeddings(Request $request, WaCustomer $customer)
    {
        $data = $request->validate([
            'vector' => 'required|array|min:1',
            'vector.*' => 'numeric',
            'model' => 'required|string|max:64',
            'top_k' => 'nullable|integer|min:1|max:50',
            'exclude_message_ids' => 'nullable|array',
            'exclude_message_ids.*' => 'integer',
            'min_score' => 'nullable|numeric|min:-1|max:1',
        ]);

        try {
            return response()->json($this->waMemoryService->queryEmbeddings(
                $customer->id,
                $data['vector'],
                $data['model'],
                (int) ($data['top_k'] ?? 4),
                $data['exclude_message_ids'] ?? [],
                (float) ($data['min_score'] ?? 0.5)
            ));
        } catch (Exception $e) {
            Log::error('Error saat query wa_message_embedding: ' . $e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }
}
