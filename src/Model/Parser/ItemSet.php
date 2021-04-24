<?php
declare(strict_types=1);

namespace DatabaseDiffer\Model\Parser;

use ArrayIterator;
use Generator;
use IteratorAggregate;

class ItemSet implements IteratorAggregate
{
    /**
     * @var Item[]
     */
    private $values = [];

    public function add(Item $item): void
    {
        $this->values[] = $item;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->values);
    }

    public function getItem(string $exprType): ?Item
    {
        foreach ($this->values as $value) {
            if ($value->data['expr_type'] === $exprType) {
                return $value;
            }
        }

        return null;
    }

    public function getItems(string $exprType): Generator
    {
        foreach ($this->values as $value) {
            if ($value->data['expr_type'] === $exprType) {
                yield $value;
            }
        }
    }

    public function hasReservedItem(string $exprType, string $baseExpr): bool
    {
        foreach ($this->values as $value) {
            if ($value->data['expr_type'] === $exprType && $value->data['base_expr'] === $baseExpr) {
                return true;
            }
        }

        return false;
    }
}