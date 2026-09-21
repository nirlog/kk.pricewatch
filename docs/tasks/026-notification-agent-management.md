# Task 026 — Управление автоматическим запуском уведомлений

**Репозиторий:** `nirlog/kk.pricewatch`  
**Целевая версия:** `0.16.0`  
**Предыдущая версия:** `0.15.1`

## Контекст

В `kk.pricewatch` уже реализована подсистема e-mail-уведомлений мониторинга:

- `NotificationRunner` — единая бизнес-реализация обработки переходов состояний и отправки digest;
- `NotificationAgent` — Bitrix Agent, вызывающий `NotificationRunner`;
- `bin/pricewatch-notify.php` — CLI-обёртка над тем же `NotificationRunner`;
- `NotificationAgentInstaller` — установка Bitrix Agent;
- `BitrixDbNotificationRunLock` — защита от параллельного запуска;
- отдельное состояние доставленных уведомлений и baseline;
- уведомления о проблемах и восстановлениях;
- настройки получателей, сайта и recovery-уведомлений;
- mail event/template `KK_PRICEWATCH_ALERT_DIGEST`.

Подсистема проверена на реальном Bitrix-сайте:

- `OK → ERROR` создаёт transition и отправляет письмо;
- повторный запуск без изменений не дублирует письмо;
- `ERROR → OK` создаёт recovery transition и отправляет письмо;
- baseline продвигается только после успешной доставки.

Сейчас `NotificationAgent` устанавливается с интервалом `3600` секунд, но неактивным:

```php
CAgent::AddAgent(
    NotificationAgent::INVOCATION,
    self::MODULE_ID,
    'N',
    3600,
    '',
    'N'
);
```

Администратору приходится управлять Agent вручную через системный список агентов Bitrix.

## Цель

Довести подсистему уведомлений до полноценной эксплуатации:

1. дать администратору возможность включать/выключать `NotificationAgent` из настроек `kk.pricewatch`;
2. дать возможность задавать интервал запуска;
3. показывать фактическое состояние Agent;
4. сохранить безопасную семантику fresh install/update;
5. не менять уже проверенную бизнес-логику `NotificationRunner`.

---

## 1. Настройки Agent в административном интерфейсе

На существующей странице:

```text
KK PriceWatch → Настройки уведомлений
```

добавить отдельный блок:

```text
Автоматический запуск

[✓] Запускать уведомления агентом

Интервал запуска:
[ 60 ] минут

Статус агента: Активен
Последний запуск: 21.09.2026 09:00:55
Следующий запуск: 21.09.2026 10:00:55
```

Для выключенного Agent:

```text
Статус агента: Отключён
```

Если дата последнего/следующего запуска отсутствует:

```text
Последний запуск: —
Следующий запуск: —
```

Страница не должна падать из-за `null`/неполных данных Agent.

## 2. Не связывать `notifications_enabled` и состояние Agent

Существующая настройка `notifications_enabled` означает, разрешена ли отправка уведомлений вообще.

Новая настройка Agent означает, должен ли Bitrix автоматически запускать `NotificationRunner`.

Это независимые состояния.

Допустимо:

```text
Уведомления: выключены
Agent: включён
```

В этом случае Agent запускается, но `NotificationRunner` корректно завершает выполнение как disabled и ничего не отправляет.

### Запрещено

- автоматически деактивировать Agent при `notifications_enabled = N`;
- автоматически активировать Agent при `notifications_enabled = Y`;
- связывать эти настройки скрытой логикой.

## 3. Интервал запуска

Добавить настройку интервала в минутах.

```text
минимум:      5 минут
по умолчанию: 60 минут
максимум:     1440 минут
```

Для `CAgent` хранить секунды:

```php
$intervalSeconds = $minutes * 60;
```

Не принимать:

- `0`;
- отрицательные значения;
- дробные значения;
- нечисловые строки;
- значения `< 5`;
- значения `> 1440`.

Ошибку показывать как обычное административное сообщение, без stack trace и внутренних деталей.

## 4. Управление существующим Agent

Идентификатор Agent остаётся:

```php
NotificationAgent::INVOCATION
```

и:

```php
MODULE_ID = 'kk.pricewatch'
```

В базе должна существовать максимум одна запись этого Agent.

`NotificationAgentInstaller` должен оставаться единым местом работы с `CAgent`.

Допускается расширить API класса, например:

```php
install(): void

getState(): NotificationAgentState

configure(
    bool $active,
    int $intervalSeconds
): void

uninstall(): void
```

Конкретные названия методов/DTO могут отличаться, если архитектура остаётся чистой.

Не размазывать прямые вызовы `CAgent::*` по admin-page и другим слоям.

Админ-страница должна работать через отдельный service/installer abstraction.

## 5. Fresh install

После чистой установки:

```text
Agent существует
ACTIVE = N
AGENT_INTERVAL = 3600
```

Сохранить текущую безопасную модель: новый модуль не должен сам начинать отправку уведомлений.

## 6. Update с `0.15.1`

При обновлении:

- существующий Agent сохранить;
- `ACTIVE` не сбрасывать;
- текущий валидный `AGENT_INTERVAL` не сбрасывать;
- дубликаты Agent удалить;
- отсутствующий Agent создать неактивным;
- updater не должен сам запускать Agent;
- updater не должен запускать `NotificationRunner`;
- updater не должен отправлять письмо.

Если существующий интервал некорректен/пуст, допускается привести его к безопасному значению `3600`.

## 7. Состояние Agent

В UI показывать минимум:

- активен / отключён;
- интервал;
- последний запуск;
- следующий запуск.

Использовать данные реальной записи `CAgent`.

Нельзя считать Agent активным только по сохранённой Option-настройке, если состояние в `CAgent` другое.

`CAgent` является источником истины для runtime-состояния Agent.

## 8. Работа с датами

Нормализовать значения дат до безопасных строк до передачи в legacy Bitrix admin UI.

Не передавать напрямую в UI:

- `DateTimeImmutable`;
- `DateTimeInterface`;
- `Bitrix\Main\Type\DateTime`.

Ранее аналогичная проблема уже возникала в monitoring dashboard.

Формат отображения использовать в стиле текущей административной части модуля.

## 9. Права доступа

Изменение настроек автоматического запуска доступно только пользователю с правом модуля `W`.

Сохранить текущую модель `Access::canWrite()`.

Для POST обязателен:

```php
check_bitrix_sessid()
```

Пользователь без `W` не должен иметь возможности изменить состояние Agent через прямой POST.

## 10. Обработка ошибок

Если изменение `CAgent` не удалось:

- не показывать ложное сообщение «сохранено»;
- вывести понятную административную ошибку;
- не показывать stack trace;
- не показывать SQL;
- не показывать внутренние исключения;
- не выводить чувствительные данные.

Изменения связанных настроек должны быть согласованы: страница не должна сообщать об успешном сохранении Agent, если фактическое изменение Agent не произошло.

## 11. CLI не менять

Сохранить текущую команду:

```bash
php bin/pricewatch-notify.php
```

CLI должен работать независимо от `ACTIVE` Bitrix Agent.

Например:

```text
Agent = OFF
notifications_enabled = Y
```

не должно мешать ручному/cron запуску CLI.

Не добавлять проверку активности Agent внутрь:

- `NotificationRunner`;
- `NotificationRunnerFactory`;
- `bin/pricewatch-notify.php`.

## 12. Существующий lock сохранить

Не удалять и не обходить `BitrixDbNotificationRunLock`.

Agent и CLI потенциально могут стартовать одновременно.

В этом случае второй runner должен корректно завершиться как locked согласно существующему контракту и не отправлять duplicate digest.

## 13. Не менять бизнес-логику `NotificationRunner`

В рамках Task 026 запрещено менять без отдельной необходимости:

- `problem_started`;
- `problem_changed`;
- `recovered`;
- fingerprint;
- notification baseline;
- `send_recovery`;
- состав digest;
- mail transport;
- monitoring health semantics;
- collector;
- current monitoring state;
- price history;
- алгоритм дедупликации.

Эта часть уже проверена E2E и не относится к задаче управления Agent.

## 14. Локализация

Добавить RU/EN строки минимум для:

```text
Автоматический запуск
Запускать уведомления агентом
Интервал запуска
минут
Статус агента
Активен
Отключён
Последний запуск
Следующий запуск
Некорректный интервал запуска
Не удалось изменить состояние агента
```

Использовать существующую систему `Loc`.

Пути `lang/...` должны точно совпадать по регистру с путями PHP-файлов.

Это необходимо для Linux и предотвращения повторения дефекта `0.15.0`.

## 15. Документация

Обновить:

```text
docs/monitoring-notifications.md
```

Описать два поддерживаемых режима запуска.

### Вариант A — Bitrix Agent

Администратор:

1. включает автоматический запуск;
2. задаёт интервал;
3. Bitrix вызывает `NotificationAgent`;
4. Agent вызывает общий `NotificationRunner`.

### Вариант B — cron / CLI

```bash
php bin/pricewatch-notify.php
```

Указать, что обычно следует выбрать один основной scheduler.

Также указать, что существующий DB-lock защищает от конкурентного запуска Agent + CLI, но намеренно запускать оба scheduler одновременно без необходимости не рекомендуется.

## 16. Версия и update package

Повысить версию модуля до:

```text
0.16.0
```

Добавить:

```text
install/updates/0.16.0/
├── updater.php
├── description.ru
└── description.en
```

Updater должен быть idempotent.

### Updater может

- проверить наличие notification Agent;
- создать отсутствующий Agent неактивным;
- удалить дубликаты;
- нормализовать структуру/интервал Agent;
- обновить runtime/admin/lang файлы штатным механизмом update package.

### Updater не должен

- включать Agent без явного действия администратора;
- запускать `NotificationRunner`;
- запускать collector;
- выполнять сбор цен;
- отправлять e-mail;
- изменять monitoring state;
- изменять price history;
- сбрасывать notification baseline;
- менять существующих получателей уведомлений.

## 17. Тесты

Добавить автоматические тесты минимум для следующих сценариев.

### `NotificationAgentInstaller` / service

1. Fresh install создаёт Agent.
2. Fresh install создаёт Agent с `ACTIVE = N`, `AGENT_INTERVAL = 3600`.
3. Повторный `install()` не создаёт второй Agent.
4. Дубликаты удаляются.
5. Существующий active Agent после `install()`/update остаётся active.
6. Существующий inactive Agent остаётся inactive.
7. Валидный существующий interval сохраняется.
8. Изменение interval работает.
9. Enable Agent работает.
10. Disable Agent работает.
11. `uninstall()` удаляет Agent.

### Validation

```text
5      → valid
60     → valid
1440   → valid
0      → invalid
4      → invalid
1441   → invalid
-1     → invalid
1.5    → invalid
abc    → invalid
```

### Admin page

Проверить:

- требуется `W`;
- используется `check_bitrix_sessid()`;
- нет несогласованных прямых операций с `CAgent`, если принят service abstraction;
- корректно отображаются active/inactive;
- корректно отображается отсутствие дат;
- даты приводятся к scalar/string до UI.

### Independence

Проверить:

- CLI не проверяет состояние Agent;
- `NotificationRunner` не проверяет состояние Agent;
- Agent использует тот же `NotificationRunnerFactory`;
- lock не удалён.

### Localization

Проверить:

- RU path;
- EN path;
- exact case-sensitive paths;
- отсутствие старых lowercase duplicate paths.

### Update package

Проверить `0.16.0`:

- наличие updater;
- наличие descriptions;
- наличие изменённых runtime/admin/lang файлов;
- отсутствие запуска collector;
- отсутствие запуска runner;
- отсутствие отправки e-mail;
- отсутствие изменения business data.

## 18. Реальный smoke test после реализации

После установки `0.16.0` на Bitrix-сайт:

1. Открыть `KK PriceWatch → Настройки уведомлений`.
2. Убедиться:
   ```text
   Уведомления: включены
   Автоматический запуск: выключен
   Интервал: 60 минут
   ```
3. Включить автоматический запуск.
4. Сохранить.
5. Проверить `Статус агента: Активен`.
6. Убедиться в административном списке Bitrix Agent, что Agent ровно один, `MODULE_ID = kk.pricewatch`, имя соответствует `NotificationAgent::INVOCATION`, `ACTIVE = Y`, interval соответствует настройке.
7. Получить новый переход `OK → ERROR`.
8. Не запускать CLI вручную.
9. Дождаться автоматического запуска Agent.
10. Проверить, что письмо пришло, transition обработан, baseline обновлён, `Последний запуск` обновился, `Следующий запуск` отображается.
11. Следующий автоматический запуск без изменений не должен отправить duplicate mail.
12. Получить `ERROR → OK`.
13. Проверить автоматический recovery digest.

## Acceptance Criteria

Task считается выполненным, если одновременно выполняются все условия:

- модуль имеет версию `0.16.0`;
- notification Agent управляется из настроек `kk.pricewatch`;
- состояние Agent берётся из `CAgent`;
- можно включить/выключить Agent;
- можно задать интервал `5..1440` минут;
- fresh install оставляет Agent неактивным;
- update не меняет существующий `ACTIVE` без необходимости;
- update не сбрасывает валидный interval;
- дубликаты Agent устраняются;
- CLI работает независимо от Agent;
- существующий DB-lock сохранён;
- `NotificationRunner` business semantics не изменены;
- RU/EN localization работает на Linux с корректным case;
- update package `0.16.0` idempotent и не запускает business actions;
- automated tests проходят;
- реальный Bitrix smoke подтверждает автоматическую отправку problem/recovery digest без ручного CLI.

## Out of scope

Не реализовывать в этой задаче:

- External Collector API;
- Python Collector;
- browser/JS collector;
- Playwright/Selenium;
- очереди внешнего collector;
- retry внешнего collector;
- изменение HTTP collector;
- новые типы notification rules;
- Telegram/Slack уведомления;
- изменение mail digest layout, кроме строго необходимого;
- cron installer/manager;
- отдельный daemon.

Следующий архитектурный этап:

```text
Task 027 — External Collector API
```
