<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;
interface ProductLabelResolverInterface { /** @param list<int> $ids @return array<int,string> */ public function resolve(array $ids): array; }
