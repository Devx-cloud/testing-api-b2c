<?php

namespace App\Services;

use App\Models\WaConversation;
use App\Models\WaCustomer;
use App\Models\WaCustomerFact;
use App\Models\WaMessage;
use App\Models\WaMessageEmbedding;
use App\Models\WaSessionState;
use App\Services\Interfaces\WaMemoryServiceInterface;
use App\Services\Interfaces\WhatsappUserServiceInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WaMemoryService implements WaMemoryServiceInterface
{
    private const ROLES = ['user', 'assistant', 'tool', 'system_note'];

    protected $whatsappUserService;

    public function __construct(WhatsappUserServiceInterface $whatsappUserService)
    {
        $this->whatsappUserService = $whatsappUserService;
    }

    public function resolveCustomer(array $input): array
    {
        $phone = !empty($input['phone_number']) ? $this->normalizeLocalPhone($input['phone_number']) : null;
        $jid = !empty($input['jid']) ? trim($input['jid']) : null;

        if (!$phone && !$jid) {
            throw new InvalidArgumentException('Minimal salah satu dari phone_number atau jid wajib diisi.');
        }

        $lookupKey = $phone ?: 'jid:' . $jid;

        /** @var WaCustomer $customer */
        $customer = WaCustomer::firstOrCreate(
            ['lookup_key' => $lookupKey],
            [
                'phone_number' => $phone,
                'display_name' => $input['display_name'] ?? null,
                'user_id' => isset($input['user_id']) ? (int) $input['user_id'] : null,
                'jids' => $jid ? [$jid] : [],
            ]
        );

        $this->backfillCustomer($customer, $phone, $jid, $input);

        $conversation = $this->pickActiveConversation($customer);

        return [
            'customer' => $this->customerArray($customer),
            'conversation' => $this->conversationArray($conversation),
        ];
    }

    public function getContext(int $conversationId, int $charBudget, int $recentTurns, bool $forSummary = false): array
    {
        $conversation = WaConversation::find($conversationId);
        if (!$conversation) {
            throw new NotFoundHttpException("Percakapan {$conversationId} tidak ditemukan.");
        }

        $charBudget = max($charBudget, 500);
        $recentTurns = max($recentTurns, 1);

        $facts = WaCustomerFact::where('customer_id', $conversation->customer_id)
            ->orderBy('fact_key')
            ->get(['fact_key', 'fact_value'])
            ->map(function ($f) {
                return ['key' => $f->fact_key, 'value' => $f->fact_value];
            })
            ->all();

        // Ambil kandidat lebih banyak dari kebutuhan, lalu pangkas berdasarkan anggaran.
        $candidates = WaMessage::where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->limit($recentTurns * 2 + 20)
            ->get();

        [$recent, $truncated, $used] = $this->budgetWindow($candidates, $charBudget, $recentTurns);

        $result = [
            'conversation_id' => $conversation->id,
            'summary' => $conversation->summary,
            'facts' => $facts,
            'recent_messages' => array_map([$this, 'messageArray'], $recent),
            'budget' => [
                'char_budget' => $charBudget,
                'used_estimate' => $used,
                'truncated' => $truncated,
            ],
        ];

        if ($forSummary) {
            $unsummarized = WaMessage::where('conversation_id', $conversationId)
                ->where('summarized', false)
                ->orderBy('id')
                ->get();
            $result['unsummarized_messages'] = $unsummarized->map([$this, 'messageArray'])->all();
        }

        return $result;
    }

    public function appendMessages(int $conversationId, array $messages, bool $touchLastActive = true): array
    {
        $conversation = WaConversation::find($conversationId);
        if (!$conversation) {
            throw new NotFoundHttpException("Percakapan {$conversationId} tidak ditemukan.");
        }

        if (empty($messages)) {
            throw new InvalidArgumentException('Daftar messages kosong.');
        }

        return DB::transaction(function () use ($conversation, $messages, $touchLastActive) {
            $created = [];

            foreach ($messages as $msg) {
                $role = $msg['role'] ?? null;
                if (!in_array($role, self::ROLES, true)) {
                    throw new InvalidArgumentException("role tidak valid: " . json_encode($role));
                }

                $content = $msg['content'] ?? null;

                /** @var WaMessage $row */
                $row = WaMessage::create([
                    'conversation_id' => $conversation->id,
                    'customer_id' => $conversation->customer_id,
                    'role' => $role,
                    'content' => $content,
                    'tool_calls' => $msg['tool_calls'] ?? null,
                    'tool_call_id' => $msg['tool_call_id'] ?? null,
                    'name' => $msg['name'] ?? null,
                    'token_estimate' => isset($msg['token_estimate'])
                        ? (int) $msg['token_estimate']
                        : mb_strlen((string) $content),
                ]);

                $created[] = [
                    'id' => $row->id,
                    'role' => $row->role,
                    'created_at' => optional($row->created_at)->toIso8601String(),
                ];
            }

            if ($touchLastActive) {
                $conversation->last_active_at = now();
                $conversation->save();
            }

            return [
                'created' => $created,
                'conversation' => [
                    'id' => $conversation->id,
                    'message_count' => WaMessage::where('conversation_id', $conversation->id)->count(),
                ],
            ];
        });
    }

    public function putSummary(int $conversationId, string $summary, ?int $upToMessageId): array
    {
        $conversation = WaConversation::find($conversationId);
        if (!$conversation) {
            throw new NotFoundHttpException("Percakapan {$conversationId} tidak ditemukan.");
        }

        return DB::transaction(function () use ($conversation, $summary, $upToMessageId) {
            $marked = 0;

            if ($upToMessageId !== null) {
                $marked = WaMessage::where('conversation_id', $conversation->id)
                    ->where('id', '<=', $upToMessageId)
                    ->where('summarized', false)
                    ->update(['summarized' => true]);
                $conversation->last_summarized_message_id = $upToMessageId;
            }

            $conversation->summary = $summary;
            $conversation->summary_updated_at = now();
            $conversation->save();

            return [
                'conversation_id' => $conversation->id,
                'summary_updated_at' => optional($conversation->summary_updated_at)->toIso8601String(),
                'marked_summarized' => $marked,
            ];
        });
    }

    public function upsertFacts(int $customerId, array $facts): array
    {
        foreach ($facts as $fact) {
            $key = $fact['key'] ?? null;
            if ($key === null || $key === '') {
                throw new InvalidArgumentException('Setiap fact wajib punya key.');
            }

            WaCustomerFact::updateOrCreate(
                ['customer_id' => $customerId, 'fact_key' => (string) $key],
                [
                    'fact_value' => (string) ($fact['value'] ?? ''),
                    'source' => $fact['source'] ?? null,
                ]
            );
        }

        return $this->getFacts($customerId);
    }

    public function getFacts(int $customerId): array
    {
        return WaCustomerFact::where('customer_id', $customerId)
            ->orderBy('fact_key')
            ->get(['fact_key', 'fact_value', 'source', 'updated_at'])
            ->map(function ($f) {
                return [
                    'key' => $f->fact_key,
                    'value' => $f->fact_value,
                    'source' => $f->source,
                    'updated_at' => optional($f->updated_at)->toIso8601String(),
                ];
            })
            ->all();
    }

    public function getSessionState(int $customerId): ?array
    {
        $state = WaSessionState::where('customer_id', $customerId)->first();
        if (!$state) {
            return null;
        }

        return [
            'conversation_id' => $state->conversation_id,
            'checkout_state' => $state->checkout_state,
            'matched_user_cache' => $state->matched_user_cache,
            'matched_user_cached_at' => optional($state->matched_user_cached_at)->toIso8601String(),
            'rate_limit' => $state->rate_limit,
            'extra' => $state->extra,
            'updated_at' => optional($state->updated_at)->toIso8601String(),
        ];
    }

    public function putSessionState(int $customerId, array $blob): array
    {
        $matchedCachedAt = $blob['matched_user_cached_at']
            ?? (array_key_exists('matched_user_cache', $blob) ? now() : null);

        $state = WaSessionState::updateOrCreate(
            ['customer_id' => $customerId],
            [
                'conversation_id' => $blob['conversation_id'] ?? null,
                'checkout_state' => $blob['checkout_state'] ?? null,
                'matched_user_cache' => $blob['matched_user_cache'] ?? null,
                'matched_user_cached_at' => $matchedCachedAt,
                'rate_limit' => $blob['rate_limit'] ?? null,
                'extra' => $blob['extra'] ?? null,
                'updated_at' => now(),
            ]
        );

        return ['updated_at' => optional($state->updated_at)->toIso8601String()];
    }

    public function storeEmbedding(int $customerId, array $data): array
    {
        $vector = array_map('floatval', $data['vector'] ?? []);
        if (empty($vector)) {
            throw new InvalidArgumentException('vector kosong.');
        }

        $attributes = [
            'customer_id' => $customerId,
            'conversation_id' => (int) $data['conversation_id'],
            'kind' => $data['kind'] ?? 'message',
            'model' => (string) $data['model'],
            'dim' => (int) ($data['dim'] ?? count($vector)),
            'vector' => $vector,
            'norm' => $this->l2norm($vector),
            'content_preview' => isset($data['content_preview'])
                ? mb_substr((string) $data['content_preview'], 0, 500)
                : null,
        ];

        $messageId = $data['message_id'] ?? null;

        if ($messageId === null) {
            // kind=summary: tidak ada message_id, selalu insert baris baru.
            $embedding = WaMessageEmbedding::create($attributes);
        } else {
            $embedding = WaMessageEmbedding::updateOrCreate(
                ['message_id' => (int) $messageId, 'model' => $attributes['model']],
                $attributes
            );
        }

        return ['id' => $embedding->id];
    }

    public function queryEmbeddings(int $customerId, array $vector, string $model, int $topK, array $excludeMessageIds, float $minScore): array
    {
        $query = array_map('floatval', $vector);
        $queryNorm = $this->l2norm($query);
        if ($queryNorm == 0.0) {
            return ['results' => []];
        }

        $exclude = array_flip(array_map('intval', $excludeMessageIds));
        $topK = max($topK, 1);

        $rows = WaMessageEmbedding::where('customer_id', $customerId)
            ->where('model', $model)
            ->get(['message_id', 'kind', 'vector', 'norm', 'content_preview', 'created_at']);

        $scored = [];
        foreach ($rows as $row) {
            if ($row->message_id !== null && isset($exclude[(int) $row->message_id])) {
                continue;
            }

            $v = $row->vector ?: [];
            $norm = $row->norm ?: $this->l2norm($v);
            if ($norm == 0.0) {
                continue;
            }

            $dot = 0.0;
            $len = min(count($query), count($v));
            for ($i = 0; $i < $len; $i++) {
                $dot += $query[$i] * (float) $v[$i];
            }

            $score = $dot / ($queryNorm * $norm);
            if ($score < $minScore) {
                continue;
            }

            $scored[] = [
                'message_id' => $row->message_id,
                'kind' => $row->kind,
                'score' => round($score, 6),
                'content_preview' => $row->content_preview,
                'created_at' => optional($row->created_at)->toIso8601String(),
            ];
        }

        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return ['results' => array_slice($scored, 0, $topK)];
    }

    public function pruneMessages(int $days, int $keepMin): int
    {
        $cutoff = now()->subDays(max($days, 0));
        $keepMin = max($keepMin, 0);
        $total = 0;

        WaConversation::orderBy('id')->chunk(100, function ($conversations) use ($cutoff, $keepMin, &$total) {
            foreach ($conversations as $conversation) {
                $keepIds = WaMessage::where('conversation_id', $conversation->id)
                    ->orderByDesc('id')
                    ->limit($keepMin)
                    ->pluck('id')
                    ->all();

                $query = WaMessage::where('conversation_id', $conversation->id)
                    ->where('summarized', true)
                    ->where('created_at', '<', $cutoff);

                if (!empty($keepIds)) {
                    $query->whereNotIn('id', $keepIds);
                }
                if ($conversation->last_summarized_message_id) {
                    $query->where('id', '<=', $conversation->last_summarized_message_id);
                }

                $deletedIds = $query->pluck('id')->all();
                if (empty($deletedIds)) {
                    continue;
                }

                WaMessageEmbedding::whereIn('message_id', $deletedIds)->delete();
                $total += WaMessage::whereIn('id', $deletedIds)->delete();
            }
        });

        return $total;
    }

    // ====================================================================
    // Helper privat
    // ====================================================================

    /**
     * Normalisasi nomor ke format lokal Indonesia ('0...'). Sejalan dengan
     * WhatsappUserService: JID WhatsApp selalu '62...', tabel user pakai '0...'.
     */
    private function normalizeLocalPhone(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') {
            return null;
        }

        if (substr($digits, 0, 2) === '62') {
            return '0' . substr($digits, 2);
        }
        if (substr($digits, 0, 1) === '0') {
            return $digits;
        }

        return '0' . $digits;
    }

    private function backfillCustomer(WaCustomer $customer, ?string $phone, ?string $jid, array $input): void
    {
        $dirty = false;

        if ($jid) {
            $jids = $customer->jids ?: [];
            if (!in_array($jid, $jids, true)) {
                $jids[] = $jid;
                $customer->jids = $jids;
                $dirty = true;
            }
        }

        if ($phone && !$customer->phone_number) {
            $customer->phone_number = $phone;
            $dirty = true;
        }

        if (!empty($input['display_name']) && !$customer->display_name) {
            $customer->display_name = $input['display_name'];
            $dirty = true;
        }

        if (!$customer->user_id && isset($input['user_id'])) {
            $customer->user_id = (int) $input['user_id'];
            $dirty = true;
        }

        // Coba tautkan ke akun Tokodaring lewat nomor telepon kalau belum tertaut.
        if (!$customer->user_id && ($phone || $customer->phone_number)) {
            $matched = $this->whatsappUserService->findByPhoneNumber($phone ?: $customer->phone_number);
            if ($matched) {
                $customer->user_id = $matched->id;
                if (!$customer->display_name && !empty($matched->first_name)) {
                    $customer->display_name = trim($matched->first_name . ' ' . ($matched->last_name ?? ''));
                }
                $customer->last_matched_at = now();
                $dirty = true;
            }
        }

        if ($dirty) {
            $customer->save();
        }
    }

    private function pickActiveConversation(WaCustomer $customer): WaConversation
    {
        /** @var WaConversation|null $conversation */
        $conversation = WaConversation::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if (!$conversation) {
            return WaConversation::create([
                'customer_id' => $customer->id,
                'thread_no' => 1,
                'status' => 'active',
                'last_active_at' => now(),
            ]);
        }

        $gapHours = (int) config('services.wa_memory.gap_hours', 12);
        $isStale = $conversation->last_active_at
            && $conversation->last_active_at->lt(now()->subHours($gapHours));

        if ($isStale && !$this->hasInFlightCheckout($customer->id)) {
            $conversation->status = 'archived';
            $conversation->save();

            $nextThreadNo = (int) WaConversation::where('customer_id', $customer->id)->max('thread_no') + 1;

            return WaConversation::create([
                'customer_id' => $customer->id,
                'thread_no' => $nextThreadNo,
                'status' => 'active',
                'last_active_at' => now(),
            ]);
        }

        return $conversation;
    }

    private function hasInFlightCheckout(int $customerId): bool
    {
        $state = WaSessionState::where('customer_id', $customerId)->first();
        if (!$state || !$state->checkout_state) {
            return false;
        }

        $checkoutState = $state->checkout_state['state'] ?? 'IDLE';

        return $checkoutState !== 'IDLE';
    }

    /**
     * Pilih jendela pesan terakhir dalam anggaran karakter, jaga integritas
     * pasangan assistant(tool_calls) <-> tool, kembalikan urut kronologis.
     *
     * @param \Illuminate\Support\Collection $candidates baris newest-first
     * @return array{0: array, 1: bool, 2: int} [rows, truncated, used]
     */
    private function budgetWindow($candidates, int $charBudget, int $recentTurns): array
    {
        $picked = [];
        $used = 0;
        $truncated = false;
        $maxRows = $recentTurns * 2;

        foreach ($candidates as $row) {
            $cost = $row->token_estimate !== null
                ? (int) $row->token_estimate
                : mb_strlen((string) $row->content);

            if ($picked && ($used + $cost > $charBudget || count($picked) >= $maxRows)) {
                $truncated = true;
                break;
            }

            $picked[] = $row;
            $used += $cost;
        }

        // picked masih newest-first -> balik jadi kronologis.
        $picked = array_reverse($picked);

        // Buang baris 'tool' di depan yang pasangan assistant-nya sudah terpotong.
        while (!empty($picked) && $picked[0]->role === 'tool') {
            $truncated = true;
            array_shift($picked);
        }

        // Buang assistant(tool_calls) di depan yang balasan tool-nya tak lengkap di jendela.
        if (!empty($picked) && $picked[0]->role === 'assistant' && $picked[0]->tool_calls) {
            $expected = count($picked[0]->tool_calls);
            $following = 0;
            for ($i = 1; $i < count($picked) && $picked[$i]->role === 'tool'; $i++) {
                $following++;
            }
            if ($following < $expected) {
                $truncated = true;
                array_shift($picked);
                while (!empty($picked) && $picked[0]->role === 'tool') {
                    array_shift($picked);
                }
            }
        }

        return [$picked, $truncated, $used];
    }

    private function messageArray(WaMessage $row): array
    {
        return [
            'id' => $row->id,
            'role' => $row->role,
            'content' => $row->content,
            'tool_calls' => $row->tool_calls,
            'tool_call_id' => $row->tool_call_id,
            'name' => $row->name,
            'token_estimate' => $row->token_estimate,
            'created_at' => optional($row->created_at)->toIso8601String(),
        ];
    }

    private function customerArray(WaCustomer $customer): array
    {
        return [
            'id' => $customer->id,
            'lookup_key' => $customer->lookup_key,
            'phone_number' => $customer->phone_number,
            'user_id' => $customer->user_id,
            'display_name' => $customer->display_name,
            'jids' => $customer->jids ?: [],
        ];
    }

    private function conversationArray(WaConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'thread_no' => (int) $conversation->thread_no,
            'last_active_at' => optional($conversation->last_active_at)->toIso8601String(),
            'has_summary' => !empty($conversation->summary),
            'unsummarized_count' => WaMessage::where('conversation_id', $conversation->id)
                ->where('summarized', false)
                ->count(),
        ];
    }

    private function l2norm(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $x) {
            $sum += (float) $x * (float) $x;
        }

        return sqrt($sum);
    }
}
