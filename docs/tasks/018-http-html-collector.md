# Task 018 — HTTP HTML price collector v1 (RoyalPC first production source)

## Goal

Add the first real network-backed collector to `kk.pricewatch` while preserving the collector/orchestration boundaries already verified through Tasks 014–017.

The target flow is:

```text
Manual admin check / Scheduled runner
                |
                v
        PriceUpdateService
                |
        DefaultCollectorFactory
                |
       CollectorInterface
                |
        HttpHtmlCollector
                |
        HttpTransportInterface
                |
        BitrixHttpTransport
                |
                v
        public HTTP/HTTPS page
```

RoyalPC (`royal-computers.ru`) is the first real smoke target because its product pages expose the current build price in server-rendered HTML. The implementation must remain generic: there must be no `if RoyalPC` / domain-specific branch in the collector core.

Target module version: `0.9.0`.

## Baseline

Task 017 / module `0.8.0` is verified on real Bitrix for:

- direct scheduled runner;
- deterministic batching;
- CLI/cron bootstrap and exit semantics;
- Bitrix Agent execution;
- DB named-lock contention and release;
- Task 016 manual-check regression;
- uninstall/reinstall with preserved monitoring data.

Task 018 must not regress those paths.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/architecture/0001-collector-boundary.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/014-price-update-service-mock-orchestration.md`
- `docs/tasks/016-manual-price-check-admin.md`
- `docs/tasks/017-scheduled-price-update-runner.md`
- `lib/Collector/*`
- `lib/Service/DefaultCollectorFactory.php`
- `lib/Service/PriceUpdateService.php`
- `lib/Model/CollectorType.php`
- `lib/Model/CollectorOptions.php`
- competitor admin CRUD and localization files
- current installer/update-package/version files

Use `$implement-task`.

Before relying on Bitrix HTTP APIs, verify the exact methods against the supported real Bitrix runtime/source. The supported runtime provides `Bitrix\Main\Web\HttpClient`, including GET, timeouts, redirect control, response status/content type, response body limits, and private-IP protection.

## Architectural decisions

### 1. Add a generic `http` collector type

Extend the known collector types with:

```text
http
```

`mock` remains unchanged. `external` remains reserved for a future external/browser collector service.

`DefaultCollectorFactory` must create an HTTP HTML collector only for `COLLECTOR_TYPE=http` and continue to create the existing mock collector for `mock`.

Do not reinterpret `external` as HTTP.

Do not instantiate a collector from an arbitrary PHP class name stored in `COLLECTOR_HANDLER`.

For `http` v1, `COLLECTOR_HANDLER` is not an executable class/callback and should normally remain empty.

### 2. Use an explicit HTTP transport boundary

Do not let HTML extraction tests perform real network requests.

Introduce a narrow transport abstraction conceptually equivalent to:

```php
interface HttpTransportInterface
{
    public function fetch(string $url): HttpFetchResult;
}
```

Exact naming may vary for repository conventions.

`HttpFetchResult` should expose only the data needed by the collector, for example:

```text
success
status
content_type
body
effective_url
```

and a generic transport failure classification where needed.

The production implementation must use the supported Bitrix HTTP client rather than `file_get_contents()`, raw sockets, shell `curl`, `exec()`, or a new framework dependency.

The transport boundary must permit deterministic unit tests with an in-memory fake/stub transport.

### 3. Bitrix HTTP transport safety defaults

The default production HTTP transport must be intentionally conservative.

Required behavior:

- only absolute `http://` and `https://` URLs are accepted;
- embedded URL credentials (`user:pass@host`) are rejected;
- empty/malformed hosts are rejected;
- private/local IP access is disabled through the supported Bitrix `HttpClient` mechanism (`privateIp=false` / `setPrivateIp(false)` as appropriate for the verified runtime);
- SSL certificate verification remains enabled;
- do not call `disableSslVerification()`;
- connection timeout is bounded;
- stream/read timeout is bounded;
- response body size is bounded;
- send a stable module User-Agent and normal HTML Accept header;
- no cookies/session persistence is introduced in this task;
- no proxy credentials are added in this task;
- no POST requests are added in this task.

Recommended defaults unless the real runtime requires a justified adjustment:

```text
socket timeout: 10 s
stream timeout: 15 s
response body max: 2 MiB
redirects: disabled in v1
```

Redirects are intentionally disabled for v1 so a persisted competitor URL cannot silently cross to another host. A 3xx response is handled as an item-level HTTP status failure. Redirect support can be added later with explicit destination validation.

Do not expose `privateIp=true`, SSL verification disablement, arbitrary proxy configuration, or arbitrary request headers through `COLLECTOR_OPTIONS` in Task 018.

### 4. Enforce competitor-domain ownership

The HTTP collector must not become a generic SSRF/fetch endpoint merely because an administrator can edit competitor links.

When the factory constructs the HTTP collector, provide the persisted competitor `DOMAIN` as its allowed host boundary.

For every requested item URL before any network request:

- parse the host;
- compare it case-insensitively to the competitor domain;
- allow the exact configured host;
- optionally allow subdomains only if the implementation makes this rule explicit and tests it;
- never accept a suffix-confusion host such as `royal-computers.ru.attacker.example`;
- reject IP-literal product URLs for normal competitor configuration;
- preserve the complete stored product URL bytes/query string when sending the request; do not normalize/rebuild query parameters.

For RoyalPC the competitor domain is expected to be configured as:

```text
royal-computers.ru
```

A URL/domain mismatch is an item-level safe failure and no request is sent.

### 5. HTML extraction boundary

Keep HTTP retrieval separate from HTML-to-price extraction.

Introduce a narrow extractor conceptually equivalent to:

```php
interface HtmlPriceExtractorInterface
{
    public function extract(string $html, HttpPriceExtractionOptions $options): string;
}
```

The extractor must not know about ORM, competitor IDs, ProductCompetitor rows, scheduled execution, or persistence.

### 6. XPath in v1; no runtime Composer dependency

Task 018 v1 uses PHP's built-in DOM support (`DOMDocument` / `DOMXPath`) for extraction.

Do **not** add `symfony/css-selector` or another runtime Composer dependency solely to support CSS selectors. The current Bitrix module runtime and Marketplace update-package path do not depend on a shipped Composer `vendor/` tree, and Task 018 must not expand scope into dependency packaging.

Use an option such as:

```json
{
  "price_selector": {
    "type": "xpath",
    "value": "//..."
  },
  "currency": "RUB"
}
```

Exact field naming may vary only for a clear repository reason.

Design the extraction boundary so a future `css` selector implementation can be added without changing `CollectorInterface`, `CollectorRequest`, `CollectorResponse`, or ORM schema.

Do not implement an ad-hoc full CSS parser in Task 018.

### 7. HTTP collector options

`COLLECTOR_OPTIONS` remains a JSON object stored per competitor.

For `http` v1, support only a small validated configuration surface. At minimum:

```json
{
  "price_selector": {
    "type": "xpath",
    "value": "//..."
  },
  "currency": "RUB"
}
```

Optional bounded timeout overrides are acceptable only if they have strict numeric limits and safe defaults, for example:

```json
{
  "socket_timeout": 10,
  "stream_timeout": 15
}
```

Do not add arbitrary headers, cookies, proxy credentials, SSL bypasses, HTTP methods, JavaScript, or per-item executable expressions.

Invalid HTTP collector configuration must fail safely through `InvalidConfigurationException`, so `PriceUpdateService` retains its existing generic competitor-group `COLLECTOR_ERROR` behavior.

### 8. HTML parsing behavior

For a successful HTTP 2xx HTML response:

1. parse the document without executing scripts;
2. evaluate the configured XPath;
3. require an unambiguous price node/value;
4. extract text content (v1 does not need arbitrary attribute extraction unless RoyalPC real HTML proves it necessary);
5. normalize common Unicode/HTML whitespace;
6. parse one monetary numeric value;
7. normalize it to a decimal string suitable for the existing `Money` value rules;
8. return the configured uppercase three-letter currency (RoyalPC: `RUB`).

The extractor must handle common Russian price forms such as:

```text
187 040 ₽
187 040 ₽
187 040 ₽
187040 ₽
187040.00
```

A successful result should be normalized consistently, preferably:

```text
187040.00
```

Do not use PHP float for money.

Reject ambiguous/malformed values instead of guessing.

### 9. Item-level error semantics

One failed HTTP item must not discard successful items from the same collector request.

Use stable item-level errors. At minimum define/test semantics equivalent to:

```text
URL_NOT_ALLOWED       URL invalid or outside competitor domain boundary
HTTP_REQUEST_FAILED   transport/network failure
HTTP_STATUS           non-2xx HTTP status, including redirect in v1
INVALID_CONTENT_TYPE  response is not acceptable HTML
HTML_PARSE_ERROR      HTML cannot be parsed sufficiently for configured extraction
PRICE_NOT_FOUND       selector finds no usable price
PRICE_AMBIGUOUS       selector/value yields multiple incompatible candidates
PRICE_INVALID         selected text cannot be normalized to a valid price
```

If the existing error model makes one of these names materially awkward, use a similarly stable explicit code and document the mapping.

Do not expose raw socket errors, local paths, credentials, full HTML bodies, or stack traces in persisted/user-facing error messages.

HTTP `404`, `403`, `429`, `500`, timeout, parse failure, and missing selector are expected collector outcomes, not uncaught exceptions.

### 10. Batch semantics

`CollectorInterface` remains batch-oriented.

For one `CollectorRequest` containing several RoyalPC URLs:

- process each item independently;
- v1 may perform sequential GETs;
- preserve request item IDs exactly;
- return every requested ID exactly once;
- one network/parse failure must not suppress successful siblings;
- no internal retry/backoff in Task 018;
- no concurrent/multi-curl implementation in Task 018.

`PriceUpdateService` remains responsible for response correlation validation and persistence.

### 11. Content type and HTTP status

Treat only successful 2xx responses as candidates for extraction.

Accept normal HTML content types such as:

```text
text/html
application/xhtml+xml
```

Allow a defensible fallback when a real site omits the Content-Type header but the response is otherwise clearly HTML; if implemented, test it explicitly. Do not parse JSON, images, PDFs or arbitrary binary payloads as HTML.

### 12. Admin UI

Update competitor admin editing so `http` is selectable alongside `mock` and `external`.

Keep the current JSON options textarea in Task 018; a schema-driven visual options editor is out of scope.

Update RU/EN hints/examples so an administrator can configure the HTTP collector without reading source code.

Example only (the real XPath must be confirmed against the live RoyalPC HTML during smoke testing):

```json
{
  "price_selector": {
    "type": "xpath",
    "value": "//REPLACE_WITH_VERIFIED_ROYALPC_PRICE_NODE"
  },
  "currency": "RUB"
}
```

Do not hard-code a RoyalPC XPath in PHP.

### 13. RoyalPC live smoke target

Use a real server-rendered RoyalPC product page as the first production smoke target, for example a currently available URL under:

```text
https://royal-computers.ru/gen/...
```

At task-definition time, a live indexed example is:

```text
https://royal-computers.ru/gen/upd069
```

and the public page exposes a build price labelled `Стоимость сборки` (observed price at task-definition time: `187 040 ₽`). The value is only a smoke reference and may change; tests must never hard-code the live amount as a permanent expected production value.

Before real smoke:

- inspect the current live HTML from the Bitrix server;
- identify a stable XPath for the price node;
- store that XPath in the RoyalPC competitor `COLLECTOR_OPTIONS`;
- keep the exact persisted product URL untouched.

The implementation is successful when a manual Task 016 check can retrieve the current live RoyalPC price and the same link can then be processed through the Task 017 scheduled runner/CLI path.

### 14. Factory/composition

`DefaultCollectorFactory` remains the single default collector composition point.

Expected shape:

```text
mock     -> MockCollector
http     -> HttpHtmlCollector + BitrixHttpTransport + DOM/XPath extractor
external -> unavailable for now -> existing safe COLLECTOR_ERROR behavior
```

Do not add RoyalPC-specific factory branches.

Keep constructor injection available so tests can use fake transport/extractor dependencies without Bitrix runtime or live network.

### 15. Existing persistence semantics remain unchanged

Task 018 must not modify the meaning of:

- `CURRENT_PRICE`;
- `CURRENCY`;
- `STATUS`;
- `ERROR_CODE` / `ERROR_MESSAGE`;
- `LAST_CHECK_AT`;
- `LAST_SUCCESS_AT`;
- stale price preservation after errors.

Those semantics are already owned by `PriceUpdateService` and verified in Tasks 014–017.

A successful HTTP result flows through the same success persistence path as Mock.

An HTTP item error flows through the same error persistence path and preserves stale successful price state.

## Security requirements

Task 018 introduces outbound network access, so these requirements are blocking:

- private/local IP access disabled in the production HTTP client;
- only HTTP/HTTPS absolute URLs;
- no `file://`, `ftp://`, `gopher://`, Unix sockets or other schemes;
- no URL credentials;
- competitor-domain match before network access;
- no arbitrary headers/cookies/proxy/SSL-disable options from persisted JSON;
- redirects disabled in v1;
- bounded timeouts and response size;
- no shell commands;
- no JavaScript execution;
- no dynamic PHP class/callback execution from DB values;
- raw transport errors and page bodies are not persisted;
- exact product URL/query parameters remain opaque and unchanged;
- public/admin output continues to HTML-escape persisted error text.

Tests must explicitly prove that private-IP and wrong-domain URLs are rejected before the fake/real transport is asked to fetch them.

## Version / packaging

Bump module version to `0.9.0`.

Because no new DB schema is required, Task 018 should not add tables or destructive migrations.

Update the Marketplace update-package source for `0.9.0` consistently with the packaging convention established after Task 017:

```text
install/updates/0.9.0/updater.php       # only if lifecycle work is actually required
install/updates/0.9.0/description.ru
install/updates/0.9.0/description.en
```

The update-package builder/tests must remain green. If no updater action is required beyond file replacement, do not invent migration side effects merely to have an updater.

Do not introduce a runtime Composer dependency/vendor packaging change in Task 018.

## Tests

Add focused deterministic tests without live Internet dependency.

At minimum cover:

1. `CollectorType::HTTP` is valid; existing `mock` / `external` behavior remains valid.
2. Factory creates `MockCollector` for mock and HTTP collector for http; external remains safely unavailable.
3. HTTP collector preserves request IDs and returns every requested item exactly once.
4. Two successful fake HTML pages produce two independent success results.
5. One success + one network failure yields mixed response without dropping success.
6. HTTP 404/403/429/500 become item-level stable errors.
7. Wrong competitor domain is rejected before transport invocation.
8. Invalid scheme and URL credentials are rejected before transport invocation.
9. Price extraction handles ASCII space, NBSP and narrow-NBSP thousands separators.
10. Decimal price normalization does not use float semantics.
11. Missing XPath match -> `PRICE_NOT_FOUND`.
12. Ambiguous incompatible matches -> `PRICE_AMBIGUOUS` or documented equivalent.
13. Invalid price text -> `PRICE_INVALID`.
14. Invalid collector options -> `InvalidConfigurationException` at factory/configuration boundary.
15. HTTP transport production source has private-IP disabled, SSL verification not disabled, bounded timeouts/body, GET-only behavior and redirects disabled.
16. No HTTP collector class contains ORM persistence or scheduler logic.
17. `PriceUpdateService` regression: HTTP success updates price through existing path; HTTP failure preserves stale successful price/LAST_SUCCESS_AT.
18. Competitor admin offers `http` and preserves permission/CSRF behavior.
19. Module version/update-package assertions move to `0.9.0` without regressing package metadata tests.
20. Full existing PHPUnit suite remains green.

Network-free unit tests should use representative HTML fixtures committed under tests/fixtures or inline fixture strings. Do not make CI depend on `royal-computers.ru` availability.

## Real-Bitrix smoke checklist

After CI is green, verify on the real Bitrix environment before marking Task 018 complete.

1. Module reports `0.9.0`; existing competitors/links remain present after update/reinstall.
2. Competitor edit UI offers `http`.
3. Configure RoyalPC with `DOMAIN=royal-computers.ru`, `COLLECTOR_TYPE=http`, a verified live XPath, `currency=RUB`.
4. Link an exact current RoyalPC product URL; confirm query string/path are stored unchanged.
5. Task 016 manual `Проверить цену` succeeds and stores the live price/CURRENCY=RUB/LAST_CHECK/LAST_SUCCESS.
6. Compare the stored price with the visible live RoyalPC page at that moment.
7. Break only the XPath -> `PRICE_NOT_FOUND` (or documented extraction error), stale successful price and LAST_SUCCESS remain unchanged.
8. Restore XPath -> success recovers and clears error fields.
9. Use a wrong-domain link under the RoyalPC competitor -> no outbound request; safe `URL_NOT_ALLOWED`-style error.
10. Test a 404 RoyalPC URL -> stable HTTP status error; no raw HTML/server internals persisted.
11. Run scheduled CLI with the RoyalPC link active -> real HTTP collection works through Task 017 runner and exits normally.
12. Hold the Task 017 DB lock -> CLI remains `locked=true` and performs no HTTP fetch; after release it runs normally.
13. Mock competitor still works unchanged in the same scheduled run.
14. `external` competitor still degrades safely to generic `COLLECTOR_ERROR` without blocking HTTP/Mock siblings.
15. Inactive link is not scheduled; inactive RoyalPC competitor remains `INACTIVE_COMPETITOR` without collection-state mutation.
16. Task 016 permission/CSRF/manual behavior remains intact.
17. Bitrix Agent path can process the HTTP collector and releases the scheduler lock.
18. Uninstall/reinstall preserves monitoring data and restores one inactive scheduled agent as already verified in Task 017.

Record the live URL, configured XPath, visible live price, stored price, timestamps, PHP/Bitrix version and smoke date.

## Out of scope

Do not implement in Task 018:

- KometaPC/Tilda browser execution;
- DNS session/AjaxState logic;
- Selenium/Playwright/Puppeteer;
- external Python collector service;
- JavaScript rendering;
- cookies/login/session workflows;
- proxy rotation;
- anti-bot bypasses;
- CAPTCHA handling;
- redirects across hosts;
- arbitrary request headers from admin JSON;
- retries/backoff/queue;
- concurrent HTTP requests;
- per-competitor scheduling intervals;
- price history;
- notifications;
- public price comparison;
- automatic repricing;
- CSS-selector runtime dependency/vendor packaging;
- unrelated admin redesign/refactoring.

## Acceptance criteria

Task 018 is complete only when all of the following are true:

- `http` is a first-class collector type;
- production HTTP uses Bitrix `HttpClient` behind an injectable transport boundary;
- private-network access is disabled and URL/domain validation happens before fetch;
- HTML price extraction is configurable per competitor and independent of HTTP transport;
- v1 extraction uses DOM/XPath without adding runtime Composer dependencies;
- money normalization is decimal-string based and handles normal Russian price formatting;
- per-item HTTP/extraction failures do not discard successful batch siblings;
- existing `PriceUpdateService` remains the sole owner of operational-state persistence;
- Mock and scheduler/manual behavior do not regress;
- CI is green;
- RoyalPC live manual smoke succeeds;
- RoyalPC scheduled CLI/Agent smoke succeeds;
- stale-price error/recovery semantics are verified with the real HTTP collector;
- module/update-package version is `0.9.0` and packaging tests remain green.
