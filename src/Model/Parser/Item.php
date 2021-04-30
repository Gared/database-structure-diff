<?php
declare(strict_types=1);

namespace DatabaseDiffer\Model\Parser;

class Item
{
    /**
     * @var array
     */
    public $data;

    /**
     * @var ItemSet
     */
    public $subTree;

    /**
     * @param array $data
     */
    public function __construct(array $data, ItemSet $subTree)
    {
        $this->data = $data;
        $this->subTree = $subTree;
    }

    public function getExprType(): string
    {
        return $this->data['expr_type'];
    }

    public function getBaseExpr(): string
    {
        $baseExpr = $this->data['base_expr'];
        $baseExpr = trim($baseExpr, "'");
        return $baseExpr;
    }

    public function getName(): string
    {
        if (array_key_exists('no_quotes', $this->data)) {
            $noQuotes = $this->data['no_quotes'];
            if ($noQuotes['delim'] && $noQuotes['parts'][1] !== ')') {
                return $noQuotes['parts'][1];
            }
            return $noQuotes['parts'][0];
        }

        return $this->getBaseExpr();
    }
}