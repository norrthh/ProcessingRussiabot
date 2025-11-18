<?php

namespace App\Console\Commands;

use App\Models\PendingJoinRequest;
use App\Jobs\DeclinePendingJoinRequest;
use Illuminate\Console\Command;

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

        foreach ($expiredRequests as $request) {
            DeclinePendingJoinRequest::dispatch($request->id);
            $this->line("Заявка пользователя {$request->user_id} помечена на отклонение");
        }

        $this->info('Все истекшие заявки обработаны.');
        return 0;
    }
}
