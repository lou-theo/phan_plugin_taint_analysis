<?php

use ast\Node;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Scope;

/**
 * Représente chacun des output (echo) dans le code évalué
 */
class OutputToEvaluate
{
    /**
     * @var string|Node L'expression
     */
    private $expression;

    /**
     * @var CodeBase Le code base lié à l'output
     */
    private $code_base;

    /**
     * @var Context Le contexte lié à l'output
     */
    private $context;

    /**
     * @var int Le numéro de la ligne où se situe l'output
     */
    private $lineNumber;

    /**
     * OutputToEvaluate constructor.
     * @param string|Node $expression
     * @param CodeBase $code_base
     * @param Context $context
     */
    public function __construct($expression, CodeBase $code_base, Context $context)
    {
        $this->expression = $expression;
        $this->code_base = $code_base;
        $this->context = $context;
        $this->lineNumber = $context->getLineNumberStart();
    }

    //region Getter
    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->context->getFile();
    }

    /**
     * @return int
     */
    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * @return Scope
     */
    public function getScope(): Scope
    {
        return $this->context->getScope();
    }

    /**
     * @return Node|string
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * @return CodeBase
     */
    public function getCodeBase(): CodeBase
    {
        return $this->code_base;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        $this->context->setLineNumberStart($this->getLineNumber());
        return $this->context;
    }
    //endregion
}