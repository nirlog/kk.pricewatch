# Task 027 — External Collector API

**Репозиторий:** `nirlog/kk.pricewatch`  
**Целевая версия:** `0.17.0`  
**Предыдущая версия:** `0.16.0`

## Контекст

В модуле уже существует стабильная граница коллектора:

```php
interface CollectorInterface
{
    public function collect(CollectorRequest $request): CollectorResponse;
}
```

Также уже существуют:

- `CollectorRequest` / `CollectorResponse`;
- `CollectorItem` / `CollectorItemResult`;
- `CollectorError`;
- contract schema `1.0`;
- `MockCollector`;
- обычный HTML `HttpHtmlCollector`;
- `CollectorType::EXTERNAL`;
- поля конкурента `COLLECTOR_HANDLER` и `COLLECTOR_OPTIONS`;
- группировка batch-запросов по конкуренту в `PriceUpdateService`;
- monitoring/history/notifications поверх результата коллектора.

Согласно `AGENTS.md` и `ADR-0001`, сложный сбор данных должен выполняться отдельным внешним сервисом. В будущем это будет универсальный Python Collector. Bitrix-модуль остаётся владельцем orchestration, состояния, истории, мониторинга и уведомлений.

`COLLECTOR_HANDLER` изначально задуман как implementation-independent path, например:

```text
/api/collectors/browser
/api/collectors/dns
```

В Task 027 Python Collector **не реализуется**. Задача — реализовать в `kk.pricewatch` полноценный HTTP client adapter для `CollectorType::EXTERNAL`, безопасную конфигурацию подключения и строгую проверку ответа внешнего сервиса.

---

## Цель

После Task 027 конфигурация конкурента:

```text
COLLECTOR_TYPE = external
COLLECTOR_HANDLER = /api/collectors/browser
```

должна приводить к следующей цепочке:

```text
PriceUpdateService
    ↓
DefaultCollectorFactory
    ↓
ExternalCollector
    ↓ POST JSON
external collector service
    ↓ JSON
strict response validation
    ↓
CollectorResponse
    ↓
существующий persistence / monitoring / history / notifications
```

При этом `PriceUpdateService` не должен знать, что используется внешний HTTP-сервис.

---

## 1. Архитектурные инварианты

Сохранить следующие правила:

1. Все collectors реализуют один `CollectorInterface`.
2. `PriceUpdateService` не содержит ветвлений по внешнему сервису или handler.
3. В core запрещены competitor-specific условия вида:
   ```php
   if ($competitor === 'dns') { ... }
   ```
4. `COLLECTOR_OPTIONS` передаётся внешнему collector как opaque JSON-compatible map.
5. `COLLECTOR_HANDLER` определяет только path внешнего endpoint.
6. Global service URL и authentication не хранятся в записи конкретного конкурента.
7. `MockCollector` и `HttpHtmlCollector` продолжают работать независимо от внешнего сервиса.
8. Модуль должен работать, если External Collector вообще не настроен.
9. Не менять schema version `1.0` в этой задаче.
10. Не менять semantics persistence/history/monitoring/notifications.

---

## 2. External Collector settings

Добавить глобальную конфигурацию внешнего сервиса через module options.

Рекомендуемые ключи:

```text
external_collector_enabled
external_collector_base_url
external_collector_token
external_collector_connect_timeout
external_collector_request_timeout
```

### Defaults

```text
enabled         = N
base_url        = ''
token           = ''
connect_timeout = 5 seconds
request_timeout = 60 seconds
```

### Validation

`connect_timeout`:

```text
1..30 seconds
```

`request_timeout`:

```text
5..300 seconds
```

`request_timeout` не должен быть меньше `connect_timeout`.

При `enabled = Y` обязательны:

- valid base URL;
- non-empty API token;
- valid timeouts.

При `enabled = N` модуль не должен выполнять external network calls.

---

## 3. Административная страница

Добавить страницу, например:

```text
KK PriceWatch → Внешний коллектор
```

Пример UI:

```text
Внешний коллектор

[ ] Использовать внешний коллектор

URL сервиса:
[ https://collector.example.com ]

API token:
[                              ]
Оставьте пустым, чтобы не менять сохранённый токен.

Статус токена: Настроен / Не настроен

Таймаут соединения:
[ 5 ] секунд

Таймаут запроса:
[ 60 ] секунд
```

### Token UI

Сохранённый token:

- никогда не выводить в HTML;
- не подставлять в `value` password input;
- не показывать частично;
- пустое поле при сохранении означает «оставить текущий token»;
- новый non-empty token заменяет старый.

Не добавлять token в URL или `COLLECTOR_OPTIONS`.

### Access

Страница доступна для изменения только пользователю с module permission `W`.

POST требует:

```php
check_bitrix_sessid()
```

Добавить admin proxy в `install/admin/` и корректный install/uninstall lifecycle.

---

## 4. Base URL validation

Base URL считается trusted configuration only after explicit validation.

Разрешить:

```text
https://collector.example.com
https://collector.example.com:8443
https://collector.example.com/prefix
```

HTTP без TLS разрешить только для loopback development endpoints:

```text
http://localhost:8000
http://127.0.0.1:8000
http://[::1]:8000
```

Для любого non-loopback host требуется `https`.

Запретить:

- URL без scheme/host;
- username/password в URL;
- query string в base URL;
- fragment;
- unsupported scheme;
- control characters;
- пустое значение при enabled mode.

Trailing slash должен обрабатываться детерминированно.

Не выполнять DNS/network lookup во время простой валидации настроек.

---

## 5. `COLLECTOR_HANDLER`

Для `CollectorType::EXTERNAL` handler обязателен.

Пример:

```text
/api/collectors/browser
```

Handler должен быть только path, а не URL.

### Valid

```text
/api/collectors/browser
/api/v1/collect
```

### Invalid

```text
https://evil.example/api
//evil.example/api
api/collectors/browser
/api/../admin
/api/collect#fragment
/api/collect?x=1
```

Требования:

- начинается с `/`;
- не начинается с `//`;
- не содержит scheme/host/user/pass/query/fragment;
- не содержит `..` path segments;
- не содержит backslash/NUL/control characters;
- укладывается в существующий лимит `512` символов.

Endpoint собирается только как:

```text
normalized_base_url + normalized_handler_path
```

Handler никогда не должен иметь возможность заменить host/scheme base URL.

Добавить reusable Bitrix-independent validator/value object для handler/endpoint resolution.

---

## 6. HTTP request contract

External Collector использует существующий request contract `1.0` без изменений.

Пример POST body:

```json
{
  "schema_version": "1.0",
  "request_id": "6e51b8a2-...",
  "items": [
    {
      "id": "12",
      "url": "https://competitor.example/product/1"
    },
    {
      "id": "13",
      "url": "https://competitor.example/product/2?region=spb"
    }
  ],
  "options": {
    "region": "Санкт-Петербург"
  }
}
```

Требования:

- HTTP method: `POST`;
- UTF-8 JSON;
- `JSON_THROW_ON_ERROR`;
- не превращать money в float;
- URLs передавать exactly as stored;
- item IDs передавать без преобразования semantics;
- request ID round-trip остаётся обязательным;
- options передаются без competitor-specific interpretation в module core.

Headers минимум:

```text
Content-Type: application/json
Accept: application/json
Authorization: Bearer <token>
```

Допускается добавить safe tracing header с request ID, но body contract остаётся source of truth.

---

## 7. Transport abstraction

Не помещать `Bitrix\Main\Web\HttpClient` непосредственно внутрь business parsing logic.

Добавить узкую abstraction, например:

```text
ExternalCollectorTransportInterface
BitrixExternalCollectorTransport
ExternalCollectorHttpResult
```

Точные имена могут отличаться.

Transport отвечает только за HTTP I/O:

- POST;
- headers;
- connect/request timeout;
- HTTP status;
- Content-Type;
- response body;
- network/timeout classification.

`ExternalCollector` отвечает за collector semantics и mapping transport result → `CollectorResponse`.

---

## 8. HTTP transport security

Production transport должен:

- использовать explicit connect/request timeouts;
- отключать redirects;
- не пересылать bearer token на redirected host;
- иметь response body size limit;
- не логировать request Authorization header;
- не логировать token;
- не вставлять token в exception text;
- корректно работать с HTTPS;
- поддерживать явно разрешённый loopback HTTP development endpoint.

Response body limit:

```text
2 MiB
```

или меньше, если обосновано тестами/контрактом.

Если используемая Bitrix HTTP-защита private IP блокирует явно разрешённый loopback endpoint, разрешить private access только в рамках уже валидированного configured endpoint; не превращать transport в generic SSRF proxy.

---

## 9. HTTP status и Content-Type

Успешный protocol response принимается только для HTTP `2xx`.

Для non-2xx:

```text
COLLECTOR_ERROR
```

Не включать response body удалённого сервиса в сообщение ошибки пользователю/БД.

Для 2xx ответ должен иметь JSON Content-Type:

```text
application/json
```

Допустим charset:

```text
application/json; charset=utf-8
```

Допустим `application/*+json`, если реализация делает это строго и тестируемо.

HTML/text response при 2xx считается:

```text
INVALID_RESPONSE
```

---

## 10. External response decoder

Нельзя доверять JSON внешнего сервиса только потому, что `json_decode()` завершился успешно.

Добавить отдельный strict decoder/mapper, например:

```text
CollectorResponseDecoder
ExternalCollectorResponseDecoder
```

Он должен преобразовать JSON в существующие domain objects:

```text
CollectorResponse
CollectorItemResult
CollectorError
```

### Global success

```json
{
  "schema_version": "1.0",
  "request_id": "UUID",
  "success": true,
  "items": [
    {
      "id": "12",
      "success": true,
      "price": "129990.00",
      "currency": "RUB"
    }
  ]
}
```

### Item failure

```json
{
  "id": "13",
  "success": false,
  "error": {
    "code": "PRICE_NOT_FOUND",
    "message": "Price was not found"
  }
}
```

### Global failure

External service должен возвращать protocol-level global failure в нормальном JSON contract, например с HTTP 200:

```json
{
  "schema_version": "1.0",
  "request_id": "UUID",
  "success": false,
  "items": [],
  "error": {
    "code": "COLLECTOR_ERROR",
    "message": "Collector failed"
  }
}
```

### Decoder requirements

Обязательно проверять:

- top-level JSON object;
- `schema_version === "1.0"`;
- non-empty string `request_id`;
- boolean `success`;
- `items` является JSON list;
- item `id` non-empty string;
- item `success` boolean;
- duplicate item IDs запрещены;
- successful item имеет string `price` + string `currency`;
- `Money::assertPrice()`;
- `Money::assertCurrency()`;
- failed item имеет valid `error` object;
- failed global response имеет valid global `error`;
- global failure не содержит item results;
- conflicting required fields считаются invalid response.

Unknown extra fields должны игнорироваться для forward compatibility, если они не конфликтуют с required semantics.

Malformed response не должен частично применяться.

---

## 11. Error code/message validation

Для ответа внешнего сервиса error code должен быть пригоден для существующего поля `ERROR_CODE`.

Рекомендуемое правило:

```text
^[A-Z0-9_]{1,64}$
```

Error message:

- non-empty string;
- разумный верхний лимит, например `4096` символов;
- не должен содержать transport secrets, которые добавляет Bitrix module.

Invalid error object → весь protocol response считается `INVALID_RESPONSE`.

Не делать application logic зависимой от localized message text.

---

## 12. Error mapping внутри `ExternalCollector`

Expected transport/protocol failures не должны пробрасываться наружу как необработанные exceptions.

Использовать существующий global failure model.

Минимальное mapping:

| Ситуация | Result code |
|---|---|
| connection/request timeout | `COLLECTOR_TIMEOUT` |
| DNS/connect/TLS/network failure | `COLLECTOR_ERROR` |
| HTTP non-2xx | `COLLECTOR_ERROR` |
| response body too large | `INVALID_RESPONSE` |
| invalid Content-Type | `INVALID_RESPONSE` |
| malformed JSON | `INVALID_RESPONSE` |
| invalid schema/fields/money/currency/error shape | `INVALID_RESPONSE` |
| valid remote `success=false` | сохранить remote global error |
| valid item `success=false` | сохранить remote item error |

Сообщения для local transport failures должны быть generic и не раскрывать token/internal stack trace.

Примеры:

```text
External collector request timed out.
External collector request failed.
External collector returned an invalid response.
```

---

## 13. Correlation

Не переносить correlation responsibility из `PriceUpdateService` без необходимости.

Существующая проверка должна продолжать ловить:

- другой `request_id`;
- missing requested item;
- unexpected item ID.

Duplicate IDs decoder обязан отклонить раньше, потому что `CollectorResponse` уже запрещает duplicates.

Не ослаблять существующий `PriceUpdateService::isCorrelated()`.

---

## 14. `DefaultCollectorFactory`

Добавить поддержку:

```php
CollectorType::EXTERNAL
```

Factory должен:

1. получить validated global External Collector settings;
2. убедиться, что subsystem enabled;
3. validate `COLLECTOR_HANDLER`;
4. resolve endpoint;
5. создать `ExternalCollector`;
6. передать transport/decoder dependency;
7. не выполнять network call во время `create()`.

Invalid/missing configuration → typed `InvalidConfigurationException` или существующий эквивалент.

Не менять behavior `mock` и `http`.

Предусмотреть dependency injection для unit tests, чтобы тесты не выполняли сеть.

---

## 15. Competitor admin validation

На сохранении конкурента с:

```text
COLLECTOR_TYPE = external
```

проверять `COLLECTOR_HANDLER`.

Invalid/missing handler должен давать понятную административную ошибку и не сохранять запись.

Для `mock`/`http` не вводить новую обязательность handler.

`COLLECTOR_OPTIONS` остаётся JSON object и не получает competitor-specific schema в этой задаче.

---

## 16. Не менять collector contract v1

Task 027 не должен добавлять в body обязательные поля вроде:

```text
handler
competitor_id
site_id
product_id
secret
```

Routing делается endpoint path (`COLLECTOR_HANDLER`).

External service получает только существующий collector request:

```text
schema_version
request_id
items
options
```

Это позволит Python Collector реализовать тот же protocol без зависимости от Bitrix ORM.

---

## 17. Secret safety

API token запрещено:

- выводить в HTML;
- помещать в GET/query string;
- помещать в collector request body;
- помещать в `COLLECTOR_OPTIONS` автоматически;
- писать в module logs;
- писать в monitoring/history/error message;
- включать в exception message;
- показывать в debug dump.

Unit/source tests должны проверять хотя бы основные secret-safety invariants.

---

## 18. Version / update package

Повысить module version до:

```text
0.17.0
```

Добавить:

```text
install/updates/0.17.0/
├── updater.php
├── description.ru
└── description.en
```

Если schema change не требуется, updater должен оставаться минимальным.

Он может:

- установить новый admin proxy/menu/runtime files штатным update mechanism;
- инициализировать safe defaults module options, если это действительно требуется.

Updater не должен:

- включать External Collector автоматически;
- делать network requests;
- проверять доступность внешнего сервиса;
- запускать collection;
- изменять competitor/product link data;
- менять monitoring/history/notification baseline;
- отправлять mail.

Fresh install также должен создавать subsystem disabled by default.

---

## 19. Документация

Обновить `docs/collectors.md` и/или добавить отдельный документ для External Collector.

Документировать:

- global service settings;
- Bearer authentication;
- handler path semantics;
- request/response examples;
- global vs per-item errors;
- timeout/network mapping;
- HTTP 200 для protocol-level global failure;
- non-2xx как transport failure;
- TLS requirement;
- loopback HTTP exception;
- отсутствие redirects;
- secret-safety rules.

Документация должна быть достаточной, чтобы следующий Task мог реализовать Python Collector без чтения PHP internals.

---

## 20. Automated tests

Добавить тесты минимум на следующие сценарии.

### Settings

- disabled by default;
- enabled requires base URL/token;
- timeout boundaries;
- request timeout >= connect timeout;
- HTTPS URL accepted;
- loopback HTTP accepted;
- non-loopback HTTP rejected;
- credentials/query/fragment rejected;
- token не возвращается в renderable state/UI.

### Handler / endpoint resolver

Valid:

```text
/api/collectors/browser
/api/v1/collect
```

Invalid:

```text
''
api/collect
//evil.example/x
https://evil.example/x
/api/../x
/api/x?y=1
/api/x#fragment
```

Проверить корректное объединение base URL с path prefix + handler.

### Response decoder

- valid batch success;
- mixed success/failure;
- valid global failure;
- unknown extra fields ignored;
- wrong schema version;
- empty/missing request ID;
- `success` wrong type;
- items not list;
- duplicate IDs;
- missing item fields;
- invalid money;
- invalid currency;
- malformed item error;
- malformed global error;
- too-long/invalid error code;
- malformed JSON.

### ExternalCollector

С fake transport, без сети:

- sends POST payload matching `CollectorRequest::toArray()`;
- sends Authorization Bearer header;
- preserves full URLs/query parameters;
- 2xx valid JSON → valid `CollectorResponse`;
- timeout → `COLLECTOR_TIMEOUT`;
- network error → `COLLECTOR_ERROR`;
- non-2xx → `COLLECTOR_ERROR`;
- invalid content type → `INVALID_RESPONSE`;
- too-large response → `INVALID_RESPONSE`;
- malformed response → `INVALID_RESPONSE`;
- token не попадает в returned error/message.

### Factory

- `external` создаёт `ExternalCollector` при valid settings;
- disabled subsystem → configuration failure without network call;
- missing/invalid handler → configuration failure;
- `mock` regression;
- `http` regression.

### Admin

- permission `W`;
- CSRF;
- saved token not rendered;
- blank token input preserves existing token;
- external competitor requires valid handler.

### Update package

- version `0.17.0`;
- updater present;
- descriptions present;
- admin proxy/runtime/lang files included;
- updater contains no network call / collector execution / mail/business-state mutation.

---

## 21. Integration regression

Существующие тесты `PriceUpdateService` должны продолжать подтверждать, что:

- mixed batch сохраняется корректно;
- global collector error применяется ко всей группе;
- invalid correlation превращается в `INVALID_RESPONSE`;
- success persistence/history не меняется;
- monitoring state продолжает строиться из persisted result;
- notification subsystem не вызывается collector-ом напрямую.

Не дублировать orchestration внутри `ExternalCollector`.

---

## 22. Manual smoke test

До появления Python Collector real remote smoke необязателен, но UI/configuration smoke на Bitrix обязателен.

Проверить:

1. После update `0.17.0` External Collector выключен.
2. Страница настроек открывается без ошибок.
3. Invalid non-TLS external URL не сохраняется.
4. Loopback HTTP URL сохраняется.
5. Token после сохранения не отображается в HTML.
6. Повторное сохранение с пустым password field сохраняет ранее установленный token.
7. External competitor без handler не сохраняется.
8. Mock и HTTP competitors продолжают собираться как раньше.
9. При `external` competitor и disabled global subsystem collection завершается controlled `COLLECTOR_ERROR`, а не fatal error.

Полный real HTTP E2E выполняется в следующем этапе после появления protocol-compatible Python service/stub.

---

## Acceptance Criteria

Task считается выполненным, если одновременно выполняются все условия:

- module version `0.17.0`;
- `CollectorType::EXTERNAL` реально поддерживается factory;
- есть `ExternalCollector`, реализующий `CollectorInterface`;
- transport отделён от protocol parsing;
- используется существующий request schema `1.0`;
- strict response validation преобразует JSON в существующие domain objects;
- global/per-item errors различаются;
- timeout/network/invalid response имеют стабильное mapping;
- handler безопасно ограничен path и не может сменить host;
- base URL безопасно валидируется;
- HTTPS обязателен для non-loopback;
- redirects отключены;
- Bearer token не раскрывается;
- global subsystem disabled by default;
- admin settings и competitor handler validation работают;
- MockCollector и HttpHtmlCollector не регрессировали;
- updater `0.17.0` не выполняет network/business actions;
- полный PHPUnit suite и CI проходят;
- manual Bitrix configuration smoke проходит.

---

## Out of scope

Не реализовывать в Task 027:

- Python Collector service;
- FastAPI/Flask;
- Selenium/Playwright;
- browser automation;
- competitor-specific Python handlers;
- CAPTCHA bypass;
- anti-bot bypass;
- proxy pool;
- retry/backoff;
- async queue;
- parallel external requests;
- circuit breaker;
- health-check protocol;
- automatic service discovery;
- per-competitor API tokens;
- automatic repricing;
- new collector contract version;
- changes to monitoring notification semantics.

Следующий этап после Task 027:

```text
Task 028 — Python Collector foundation + protocol-compatible stub
```
