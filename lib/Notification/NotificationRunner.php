<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

final class NotificationRunner
{
    public const DEFAULT_BATCH_SIZE = 100;
    public const MAX_BATCH_SIZE = 1000;

    public function __construct(
        private readonly NotificationSettingsProviderInterface $settings,
        private readonly NotificationLinkRepositoryInterface $links,
        private readonly NotificationStateRepositoryInterface $states,
        private readonly NotificationRuleEvaluator $evaluator,
        private readonly ProductLabelResolverInterface $products,
        private readonly NotificationTransportInterface $transport,
        private readonly NotificationRunLockInterface $lock,
    ) {}

    public function run(int $batchSize = self::DEFAULT_BATCH_SIZE, ?DateTimeImmutable $now = null): NotificationRunResult
    {
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH_SIZE) throw new InvalidArgumentException('Batch size must be between 1 and 1000.');
        try { $config = $this->settings->get(); } catch (Throwable) { return new NotificationRunResult(failed: true); }
        if (!$config->enabled) return new NotificationRunResult(disabled: true);
        if ($config->recipients === [] || $config->siteId === '') return new NotificationRunResult(configurationMissing: true);
        try { if (!$this->lock->acquire()) return new NotificationRunResult(locked: true); } catch (Throwable) { return new NotificationRunResult(failed: true); }

        $now ??= new DateTimeImmutable();
        $scanned = 0;
        $all = [];
        try {
            $maximum = $this->links->maximumActiveId();
            $lastId = 0;
            while ($maximum !== null && $lastId < $maximum) {
                $rows = $this->links->findActiveAfter($lastId, $maximum, $batchSize);
                if ($rows === []) break;
                $ids = array_map(static fn(array $row): int => (int) $row['ID'], $rows);
                $delivered = $this->states->findForLinks($ids);
                foreach ($rows as $row) {
                    $id = (int) $row['ID'];
                    foreach ($this->evaluator->evaluate($row, $now) as $rule => $current) {
                        $transition = $this->evaluator->transition($id, $rule, $current, $delivered[$id][$rule] ?? null, $row);
                        if ($transition !== null) $all[] = $transition;
                    }
                }
                $scanned += count($rows);
                $lastId = (int) $rows[array_key_last($rows)]['ID'];
            }

            $deliverable = array_values(array_filter($all, static fn(NotificationTransition $t): bool => $config->sendRecovery || $t->type !== NotificationTransition::RECOVERED));
            $sent = false;
            if ($deliverable !== []) {
                $productIds = array_map(static fn(NotificationTransition $t): int => (int) $t->link['PRODUCT_ID'], $deliverable);
                $digest = new NotificationDigest($now, $deliverable, $this->products->resolve($productIds));
                if (!$this->transport->send($digest, $config->recipients, $config->siteId)) return new NotificationRunResult(scanned: $scanned, transitions: count($all), failed: true);
                $sent = true;
            }
            // Suppressed recoveries are an intentional successful no-op and still advance the baseline.
            if ($all !== []) $this->states->saveDelivered($all, $now);
            return new NotificationRunResult(scanned: $scanned, transitions: count($all), sent: $sent);
        } catch (Throwable) {
            return new NotificationRunResult(scanned: $scanned, transitions: count($all), failed: true);
        } finally {
            try { $this->lock->release(); } catch (Throwable) { /* Agent/CLI receive the deterministic failure from normal work. */ }
        }
    }
}
