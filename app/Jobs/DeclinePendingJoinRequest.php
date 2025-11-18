<?php

namespace App\Jobs;

use App\Models\PendingJoinRequest;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeclinePendingJoinRequest implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $pendingJoinRequestId
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $pendingRequest = PendingJoinRequest::find($this->pendingJoinRequestId);

        if (!$pendingRequest || $pendingRequest->processed) {
            return;
        }

        // Отклоняем заявку через Telegram API
        $API_URL = "https://api.telegram.org/bot" . TelegraphBot::query()->first()->token . "/";

        $ch = curl_init($API_URL . 'declineChatJoinRequest');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => [
                'chat_id' => $pendingRequest->chat_id,
                'user_id' => $pendingRequest->user_id,
            ],
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        // Помечаем заявку как обработанную
        $pendingRequest->update(['processed' => true]);

        Log::info("Declined pending join request for user {$pendingRequest->user_id} in chat {$pendingRequest->chat_id}");
    }
}
