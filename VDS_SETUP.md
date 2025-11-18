# Инструкция по настройке на VDS

## 1. Запуск миграции базы данных

```bash
cd /path/to/your/project
php artisan migrate
```

## 2. Настройка очередей (Queue Worker)

### Вариант А: Использование Supervisor (рекомендуется)

Supervisor будет автоматически перезапускать queue worker, если он упадет.

#### Шаг 1: Установите Supervisor (если не установлен)

```bash
sudo apt-get update
sudo apt-get install supervisor
```

#### Шаг 2: Создайте конфигурационный файл

Скопируйте файл `supervisor-queue-worker.conf` в директорию Supervisor:

```bash
sudo cp supervisor-queue-worker.conf /etc/supervisor/conf.d/laravel-queue-worker.conf
```

#### Шаг 3: Отредактируйте конфигурацию

```bash
sudo nano /etc/supervisor/conf.d/laravel-queue-worker.conf
```

**ВАЖНО:** Замените `/path/to/your/project` на реальный путь к вашему проекту!

Например, если проект находится в `/var/www/ProcessingRussiabot`:

```ini
[program:laravel-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ProcessingRussiabot/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/ProcessingRussiabot/storage/logs/queue-worker.log
stopwaitsecs=3600
```

#### Шаг 4: Обновите конфигурацию Supervisor и запустите

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-queue-worker:*
```

#### Шаг 5: Проверьте статус

```bash
sudo supervisorctl status
```

### Вариант Б: Использование systemd (альтернатива)

Создайте файл `/etc/systemd/system/laravel-queue-worker.service`:

```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/your/project/artisan queue:work database --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Затем:

```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-queue-worker
sudo systemctl start laravel-queue-worker
sudo systemctl status laravel-queue-worker
```

### Вариант В: Ручной запуск (только для тестирования)

```bash
php artisan queue:work database --sleep=3 --tries=3
```

⚠️ **Не рекомендуется для продакшена!** Процесс остановится при закрытии терминала.

## 3. Настройка Cron для проверки истекших заявок (резервный вариант)

Laravel использует свой собственный планировщик задач. Добавьте в crontab:

```bash
crontab -e
```

Добавьте строку (замените `/path/to/your/project` на реальный путь):

```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

Это будет запускать Laravel Scheduler каждую минуту, который в свою очередь запустит команду `join-requests:check-expired` для проверки истекших заявок.

## 4. Проверка работы

### Проверка Queue Worker:

```bash
# Если используете Supervisor
sudo supervisorctl status laravel-queue-worker:*

# Если используете systemd
sudo systemctl status laravel-queue-worker

# Проверка логов
tail -f storage/logs/queue-worker.log
```

### Проверка Cron:

```bash
# Проверка логов Laravel
tail -f storage/logs/laravel.log

# Ручной запуск команды проверки
php artisan join-requests:check-expired
```

## 5. Настройка .env

Убедитесь, что в файле `.env` установлено:

```env
QUEUE_CONNECTION=database
```

## Важные замечания

1. **Queue Worker** - основной механизм для автоматического отклонения заявок через 5 минут
2. **Cron задача** - резервный механизм, который проверяет истекшие заявки каждую минуту (на случай, если queue worker не сработал)
3. Оба механизма работают параллельно для надежности
4. Логи находятся в `storage/logs/`

## Устранение проблем

### Queue Worker не работает:

```bash
# Перезапуск Supervisor
sudo supervisorctl restart laravel-queue-worker:*

# Проверка логов
tail -f storage/logs/queue-worker.log
```

### Cron не работает:

```bash
# Проверка, запущен ли Laravel Scheduler
php artisan schedule:list

# Ручной запуск
php artisan schedule:run
```

