<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use Bitrix\Main\Type\DateTime;
use InvalidArgumentException;
use KK\PriceWatch\Collector\CollectorItem;
use KK\PriceWatch\Collector\CollectorItemResult;
use KK\PriceWatch\Collector\CollectorRequest;
use KK\PriceWatch\Collector\CollectorResponse;
use KK\PriceWatch\Model\CollectionStatus;
use KK\PriceWatch\Model\CompetitorTable;
use KK\PriceWatch\Model\ProductCompetitorTable;
use Throwable;

final class PriceUpdateService
{
    private const GENERIC_ERROR = 'The collector could not process this competitor configuration.';
    private const INVALID_RESPONSE = 'The collector returned an invalid response.';

    public function __construct(
        private readonly CollectorFactoryInterface $collectorFactory,
        private readonly RequestIdGeneratorInterface $requestIdGenerator,
    ) {
    }

    /** @param list<int> $linkIds */
    public function updateLinks(array $linkIds): PriceUpdateBatchResult
    {
        $ids = $this->normalizeIds($linkIds);
        $links = [];
        $rows = ProductCompetitorTable::getList(['filter' => ['@ID' => $ids], 'select' => ['ID', 'COMPETITOR_ID', 'URL', 'ACTIVE']]);
        while ($row = $rows->fetch()) { $links[(int) $row['ID']] = $row; }

        $competitorIds = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['COMPETITOR_ID'], $links)));
        $competitors = [];
        if ($competitorIds !== []) {
            $rows = CompetitorTable::getList(['filter' => ['@ID' => $competitorIds], 'select' => ['ID', 'ACTIVE', 'COLLECTOR_TYPE', 'COLLECTOR_HANDLER', 'COLLECTOR_OPTIONS']]);
            while ($row = $rows->fetch()) { $competitors[(int) $row['ID']] = $row; }
        }

        $outcomes = [];
        $groups = [];
        foreach ($ids as $id) {
            $link = $links[$id] ?? null;
            if ($link === null) { $outcomes[] = new PriceUpdateOutcome($id, PriceUpdateOutcome::SKIPPED, 'NOT_FOUND', 'The monitored link was not found.'); continue; }
            if ($link['ACTIVE'] !== 'Y') { $outcomes[] = new PriceUpdateOutcome($id, PriceUpdateOutcome::SKIPPED, 'INACTIVE_LINK', 'The monitored link is inactive.'); continue; }
            $competitor = $competitors[(int) $link['COMPETITOR_ID']] ?? null;
            if ($competitor === null) { $outcomes[] = $this->persistError($id, 'COLLECTOR_ERROR', self::GENERIC_ERROR, new DateTime()); continue; }
            if ($competitor['ACTIVE'] !== 'Y') { $outcomes[] = new PriceUpdateOutcome($id, PriceUpdateOutcome::SKIPPED, 'INACTIVE_COMPETITOR', 'The competitor is inactive.'); continue; }
            $groups[(int) $link['COMPETITOR_ID']][] = $link;
        }

        foreach ($groups as $competitorId => $group) {
            $outcomes = [...$outcomes, ...$this->processGroup($competitors[$competitorId], $group)];
        }
        return new PriceUpdateBatchResult(count($ids), $outcomes);
    }

    /** @param array<string, mixed> $competitor @param list<array<string, mixed>> $links @return list<PriceUpdateOutcome> */
    private function processGroup(array $competitor, array $links): array
    {
        try {
            $options = \KK\PriceWatch\Model\CollectorOptions::decode((string) $competitor['COLLECTOR_OPTIONS']);
            $items = array_map(static fn(array $link): CollectorItem => new CollectorItem((string) $link['ID'], (string) $link['URL']), $links);
            $request = new CollectorRequest(CollectorRequest::SCHEMA_VERSION, $this->requestIdGenerator->generate(), $items, $options);
            $response = $this->collectorFactory->create($competitor)->collect($request);
        } catch (Throwable) {
            return $this->persistGroupError($links, 'COLLECTOR_ERROR', self::GENERIC_ERROR);
        }

        if (!$this->isCorrelated($request, $response)) {
            return $this->persistGroupError($links, 'INVALID_RESPONSE', self::INVALID_RESPONSE);
        }
        $checkedAt = new DateTime();
        if (!$response->success) {
            return array_map(fn(array $link): PriceUpdateOutcome => $this->persistError((int) $link['ID'], $response->error->code, $response->error->message, $checkedAt), $links);
        }
        $results = [];
        foreach ($response->items as $item) { $results[$item->id] = $item; }
        return array_map(fn(array $link): PriceUpdateOutcome => $this->persistItem((int) $link['ID'], $results[(string) $link['ID']], $checkedAt), $links);
    }

    private function isCorrelated(CollectorRequest $request, CollectorResponse $response): bool
    {
        if ($response->requestId !== $request->requestId) { return false; }
        if (!$response->success) { return true; }
        $requested = array_fill_keys(array_map(static fn(CollectorItem $item): string => $item->id, $request->items), true);
        foreach ($response->items as $item) { if (!isset($requested[$item->id])) { return false; } unset($requested[$item->id]); }
        return $requested === [];
    }

    private function persistItem(int $id, CollectorItemResult $item, DateTime $checkedAt): PriceUpdateOutcome
    {
        if (!$item->success) { return $this->persistError($id, $item->error->code, $item->error->message, $checkedAt); }
        return $this->update($id, ['CURRENT_PRICE' => $item->price, 'CURRENCY' => $item->currency, 'STATUS' => CollectionStatus::SUCCESS,
            'ERROR_CODE' => null, 'ERROR_MESSAGE' => null, 'LAST_CHECK_AT' => $checkedAt, 'LAST_SUCCESS_AT' => $checkedAt], PriceUpdateOutcome::SUCCESS);
    }

    private function persistError(int $id, string $code, string $message, DateTime $checkedAt): PriceUpdateOutcome
    {
        return $this->update($id, ['STATUS' => CollectionStatus::ERROR, 'ERROR_CODE' => $code, 'ERROR_MESSAGE' => $message, 'LAST_CHECK_AT' => $checkedAt], PriceUpdateOutcome::ERROR, $code, $message);
    }

    /** @param list<array<string, mixed>> $links @return list<PriceUpdateOutcome> */
    private function persistGroupError(array $links, string $code, string $message): array
    {
        $checkedAt = new DateTime();
        return array_map(fn(array $link): PriceUpdateOutcome => $this->persistError((int) $link['ID'], $code, $message, $checkedAt), $links);
    }

    /** @param array<string, mixed> $fields */
    private function update(int $id, array $fields, string $status, ?string $code = null, ?string $message = null): PriceUpdateOutcome
    {
        try { $result = ProductCompetitorTable::update($id, $fields); } catch (Throwable) {
            return new PriceUpdateOutcome($id, PriceUpdateOutcome::PERSISTENCE_FAILURE, 'PERSISTENCE_ERROR', 'The collection state could not be saved.');
        }
        if (!$result->isSuccess()) { return new PriceUpdateOutcome($id, PriceUpdateOutcome::PERSISTENCE_FAILURE, 'PERSISTENCE_ERROR', 'The collection state could not be saved.'); }
        return new PriceUpdateOutcome($id, $status, $code, $message);
    }

    /** @param list<int> $linkIds @return list<int> */
    private function normalizeIds(array $linkIds): array
    {
        if ($linkIds === []) { throw new InvalidArgumentException('At least one monitored link ID is required.'); }
        $ids = [];
        foreach ($linkIds as $id) {
            if (!is_int($id) || $id <= 0) { throw new InvalidArgumentException('Monitored link IDs must be positive integers.'); }
            $ids[$id] = $id;
        }
        return array_values($ids);
    }
}
