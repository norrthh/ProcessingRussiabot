# Простая настройка на VDS (через Cron или Screen)

## Вариант 1: Только через Cron (САМЫЙ ПРОСТОЙ) ⭐

Этот вариант не требует Queue Worker вообще. Просто проверяет истекшие заявки каждую минуту.

### Шаг 1: Запустите миграцию

```bash
cd /path/to/your/project
php artisan migrate
```

### Шаг 2: Настройте Cron

```bash
crontab -e
```

Добавьте строку (замените `/path/to/your/project` на реальный путь):

```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

**Готово!** Система будет проверять истекшие заявки каждую минуту и автоматически отклонять их.

---

## Вариант 2: Queue Worker через Screen (если нужны точные 5 минут)

### Шаг 1: Запустите миграцию

```bash
cd /path/to/your/project
php artisan migrate
```

### Шаг 2: Запустите Queue Worker в Screen

```bash
# Создайте screen сессию
screen -S queue-worker

# Запустите queue worker
cd /path/to/your/project
php artisan queue:work database --sleep=3 --tries=3

# Нажмите Ctrl+A, затем D чтобы отключиться от screen (процесс продолжит работать)
```

### Шаг 3: Проверьте, что процесс работает

```bash
screen -ls
```

### Шаг 4: (Опционально) Настройте Cron как резерв

```bash
crontab -e
```

Добавьте:
```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

### Полезные команды для Screen:

```bash
# Посмотреть список screen сессий
screen -ls

# Подключиться к сессии
screen -r queue-worker

# Убить сессию (если нужно перезапустить)
screen -X -S queue-worker quit
```

---

## Вариант 3: Queue Worker через nohup (еще проще чем screen)

### Шаг 1: Запустите миграцию

```bash
cd /path/to/your/project
php artisan migrate
```

### Шаг 2: Запустите Queue Worker в фоне

```bash
cd /path/to/your/project
nohup php artisan queue:work database --sleep=3 --tries=3 > storage/logs/queue-worker.log 2>&1 &
```

### Шаг 3: Проверьте, что процесс работает

```bash
ps aux | grep "queue:work"
```

### Шаг 4: (Опционально) Настройте Cron как резерв

```bash
crontab -e
```

Добавьте:
```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Какой вариант выбрать?

- **Вариант 1 (только Cron)** - самый простой, не требует дополнительных процессов. Заявки будут отклоняться в течение 1 минуты после истечения (проверка каждую минуту).

- **Вариант 2 (Screen)** - если нужны точные 5 минут. Процесс будет работать в фоне, можно легко подключиться и посмотреть логи.

- **Вариант 3 (nohup)** - еще проще чем screen, но сложнее управлять процессом.

## Настройка .env

Убедитесь, что в `.env` установлено:

```env
QUEUE_CONNECTION=database
```

## Проверка работы

```bash
# Проверка логов
tail -f storage/logs/laravel.log

# Ручной запуск проверки
php artisan join-requests:check-expired

# Проверка очереди (если используете queue worker)
php artisan queue:work database --once
```

