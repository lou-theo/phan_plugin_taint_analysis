<?php

use ast\Node;
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
     * @var FunctionDefinition|null La FunctionDefinition liée à l'appel de la fonction
     */
    private $functionDefinition;

    /**
     * @var array<mixed|Node> La liste des expressions utilisées en tant qu'argument d'appel de la fonction
     */
    private $expressionArguments = [];

    /**
     * FunctionSource constructor.
     * @param Context $context
     * @param string $functionName
     * @param Node $nodeArgumentsList
     */
    public function __construct(Context $context, string $functionName, Node $nodeArgumentsList)
    {
        parent::__construct($context);
        $this->functionName = $functionName;
        foreach ($nodeArgumentsList->children as $argument) {
            $this->expressionArguments[] = $argument;
        }
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

    /**
     * @return array<mixed|Node>
     */
    public function getExpressionArguments(): array
    {
        return $this->expressionArguments;
    }

    /**
     * @return FunctionDefinition|null
     */
    public function getFunctionDefinition(): ?FunctionDefinition
    {
        return $this->functionDefinition;
    }

    /**
     * @param FunctionDefinition|null $functionDefinition
     */
    public function setFunctionDefinition(?FunctionDefinition $functionDefinition): void
    {
        $this->functionDefinition = $functionDefinition;
    }
    //endregion
}