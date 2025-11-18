<?php

namespace App\Console\Commands;

use App\Models\PendingJoinRequest;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiredJoinRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'join-requests:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверяет и отклоняет истекшие заявки на вступление';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredRequests = PendingJoinRequest::where('processed', false)
            ->where('expires_at', '<=', now())
            ->get();

        if ($expiredRequests->isEmpty()) {
            $this->info('Нет истекших заявок.');
            return 0;
        }

        $this->info("Найдено истекших заявок: {$expiredRequests->count()}");

        $bot = TelegraphBot::query()->first();
        if (!$bot) {
            $this->error('Бот не найден в базе данных!');
            return 1;
        }

        $API_URL = "https://api.telegram.org/bot" . $bot->token . "/";
        $successCount = 0;
        $errorCount = 0;

        foreach ($expiredRequests as $request) {
            try {
                // Отклоняем заявку через Telegram API
                $ch = curl_init($API_URL . 'declineChatJoinRequest');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POSTFIELDS => [
                        'chat_id' => $request->chat_id,
                        'user_id' => $request->user_id,
                    ],
                ]);
                $res = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $response = json_decode($res, true);

                if ($httpCode === 200 && isset($response['ok']) && $response['ok']) {
                    // Помечаем заявку как обработанную
                    $request->update(['processed' => true]);
                    $this->line("✓ Заявка пользователя {$request->user_id} отклонена");
                    $successCount++;
                    Log::info("Declined pending join request for user {$request->user_id} in chat {$request->chat_id}");
                } else {
                    $errorMsg = $response['description'] ?? 'Unknown error';
                    $this->error("✗ Ошибка при отклонении заявки пользователя {$request->user_id}: {$errorMsg}");
                    $errorCount++;
                    Log::error("Failed to decline join request for user {$request->user_id}: {$errorMsg}", [
                        'response' => $response
                    ]);
                }
            } catch (\Exception $e) {
                $this->error("✗ Исключение при обработке заявки пользователя {$request->user_id}: " . $e->getMessage());
                $errorCount++;
                Log::error("Exception while declining join request for user {$request->user_id}", [
                    'exception' => $e->getMessage()
                ]);
            }
        }

        $this->info("Обработка завершена. Успешно: {$successCount}, Ошибок: {$errorCount}");
        return 0;
    }
}
