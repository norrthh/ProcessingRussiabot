<?php

namespace App;

use DefStudio\Telegraph\DTO\CallbackQuery;
use DefStudio\Telegraph\DTO\ChatJoinRequest;
use DefStudio\Telegraph\DTO\InlineQuery;
use DefStudio\Telegraph\DTO\PreCheckoutQuery;
use DefStudio\Telegraph\DTO\Reaction;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use DefStudio\Telegraph\DTO\Message;
use Illuminate\Http\Request;
use Throwable;
use App\Models\PendingJoinRequest;
use App\Jobs\DeclinePendingJoinRequest;
use Carbon\Carbon;

class TelegraphHandler extends WebhookHandler
{
    /**
     */
    public function handle(Request $request, TelegraphBot $bot): void
    {
        try {
            $this->bot = $bot;
            $this->request = $request;

//            Log::info(print_r($request->all(), true));

            if ($this->request->has('inline_query')) {
                $this->handleInlineQuery(InlineQuery::fromArray($this->request->input('inline_query')));

                return;
            }

            if ($this->request->has('pre_checkout_query')) {
                $this->handlePreCheckoutQuery(PreCheckoutQuery::fromArray($this->request->input('pre_checkout_query')));

                return;
            }

            // setup data
            $this->message = match (true) {
                $this->request->has('message') => Message::fromArray($this->request->input('message')),
                $this->request->has('edited_message') => Message::fromArray($this->request->input('edited_message')),
                $this->request->has('channel_post') => Message::fromArray($this->request->input('channel_post')),
                default => null,
            };

            $this->reaction = match (true) {
                $this->request->has('message_reaction') => Reaction::fromArray($this->request->input('message_reaction')),
                default => null,
            };

            $this->callbackQuery = match (true) {
                $this->request->has('callback_query') => CallbackQuery::fromArray($this->request->input('callback_query')),
                default => null,
            };

            $this->chatJoinRequest = match (true) {
                $this->request->has('chat_join_request') => ChatJoinRequest::fromArray($this->request->input('chat_join_request')),
                default => null,
            };

            // setup chat
            $this->setupChat();

            // run handlers
            match (true) {
                isset($this->message) => $this->handleMessage(),
                isset($this->callbackQuery) => $this->handleCallbackQuery(),
                isset($this->chatJoinRequest) => $this->handleChatJoinRequest($this->chatJoinRequest),
                isset($this->reaction) => $this->handleReaction(),
                default => null,
            };
        } catch (Throwable $throwable) {
            $this->onFailure($throwable);
        }
    }

    public function handleChatJoinRequest(ChatJoinRequest $chatJoinRequest): void
    {
        $payload = request()->all();

        $r = $payload['chat_join_request'];
        $userChatId = $r['user_chat_id'] ?? null;    // ЛС пользователю
        $userId = $r['from']['id'] ?? null;
        $chatId = $r['chat']['id'] ?? null;
        $name = trim(($r['from']['first_name'] ?? '') . ' ' . ($r['from']['last_name'] ?? ''));

        if ($userChatId && $userId && $chatId) {
            // Пишем в ЛС правила и даём кнопку "Вступить"
            $text = "Привет, {$name}!\n" .
                "Перед входом — короткие правила:\n" .
                "• Рекламу и ссылки согласовывать заранее\n" .
                "• Обнал, заливы, чернуха — бан сразу\n" .
                "• О продаже/покупке — до 4 строк\n" .
                "• По рекламе к @LaptevaP1\n" .
                "• Тимлид и модератор @zorinP1\n" .
                "• Если нужна платежка @P1agent\n" .
                "• Гарант @zorinP1\n" .
                "• В случае возникновение ошибок с ботом - @wtfnonamedw\n" .
                "\nНажми кнопку ниже, чтобы войти.";

            $messageResult = $this->telegramRequest('sendMessage', [
                'chat_id' => $userChatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => 'Вступить в форум', 'callback_data' => 'action:inviteForum']],
                    ]
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $messageId = $messageResult['result']['message_id'] ?? null;

            // Сохраняем заявку в базу данных
            $pendingRequest = PendingJoinRequest::create([
                'user_id' => $userId,
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'expires_at' => Carbon::now()->addMinutes(5), // 5 минут на ответ
                'processed' => false,
            ]);

            // Запускаем Job для автоматического отклонения через 5 минут
            DeclinePendingJoinRequest::dispatch($pendingRequest->id)
                ->delay(now()->addMinutes(5));
        }
    }

    public function handleCallbackQuery(): void
    {
        parent::handleCallbackQuery();
    }

    public function inviteForum()
    {
        $userId = $this->callbackQuery->from()->id();
        $chatId = '-1002013150763';

        // Находим заявку (включая обработанные, чтобы проверить срок)
        $pendingRequest = PendingJoinRequest::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        // Проверяем, истекла ли заявка или была ли она уже обработана
        if (!$pendingRequest || $pendingRequest->processed || $pendingRequest->expires_at < now()) {
            $this->reply('❌ Время действия заявки истекло. Пожалуйста, отправьте заявку на вступление заново.');
            
            if ($this->callbackQuery->message()) {
                $this->chat->deleteMessage($this->callbackQuery->message()->id())->send();
            }
            
            return;
        }

        // Помечаем заявку как обработанную
        $pendingRequest->update(['processed' => true]);
        // Используем chat_id из заявки, если он есть
        $chatId = $pendingRequest->chat_id ?? $chatId;

        $this->telegramRequest('approveChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);

        $this->reply('Готово ✅');

        if ($this->callbackQuery->message()) {
            $this->chat->deleteMessage($this->callbackQuery->message()->id())->send();
        }

    }

    public function telegramRequest($method, $params)
    {
        $API_URL = "https://api.telegram.org/bot" . TelegraphBot::query()->first()->token . "/";

        $ch = curl_init($API_URL . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $params,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res, true);
    }
}
