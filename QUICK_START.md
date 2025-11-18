# Быстрый старт на VDS

## Самый простой способ (только Cron) ⭐

### Шаг 1: Миграция

```bash
php artisan migrate
```

### Шаг 2: Настройка Cron

```bash
crontab -e
```

Добавьте (замените `/path/to/your/project` на реальный путь):
```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

**Готово!** ✅ Система будет автоматически проверять и отклонять истекшие заявки каждую минуту.

---

## Альтернатива: Queue Worker через Screen

Если нужны точные 5 минут (а не проверка каждую минуту):

```bash
# Запустите в screen
screen -S queue-worker
cd /path/to/your/project
php artisan queue:work database --sleep=3 --tries=3
# Нажмите Ctrl+A, затем D для отключения
```

---

Подробные инструкции: см. `SIMPLE_SETUP.md` (простой способ) или `VDS_SETUP.md` (через Supervisor)

