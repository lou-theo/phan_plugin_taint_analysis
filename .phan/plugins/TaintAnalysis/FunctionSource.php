<?php

use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Scope;

require_once __DIR__ . "/Source.php";

/**
 * Représente les sources de transmission de données des variables
 */
class FunctionSource extends Source
{
    /**
     * @var string Le nom de la fonction appelée
     */
    private $functionName;

    /**
     * FunctionSource constructor.
     * @param Context $context
     * @param string $functionName
     */
    public function __construct(Context $context, string $functionName)
    {
        parent::__construct($context);
        $this->functionName = $functionName;
    }

    /**
     * @inheritDoc
     */
    public function getDisplayName(): string
    {
        return $this->getFunctionName() . '()';
    }

    //region Getter
    /**
     * @return string
     */
    public function getFunctionName(): string
    {
        return $this->functionName;
    }
    //endregion
}