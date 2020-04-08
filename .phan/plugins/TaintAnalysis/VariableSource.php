<?php

use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Scope;

/**
 * Représente les sources de transmission de données des variables
 */
class VariableSource
{
    /**
     * @var string Le nom de la variable source
     */
    private $varName;

    /**
     * @var Variable|null L'objet correspondant à la variable
     */
    private $varObject;

    /**
     * @var Context Le contexte lié à la variable
     */
    private $context;

    /**
     * @var int Le numéro de la ligne où la source transmet ses données
     */
    private $lineNumber;

    /**
     * VariableSource constructor.
     * @param Context $context
     * @param Variable|null $varObject
     * @param string $varName
     */
    public function __construct(Context $context, ?Variable $varObject, string $varName = "")
    {
        $this->context = $context;
        $this->varObject = $varObject;
        $this->lineNumber = $context->getLineNumberStart();
        if ($varObject != null && $varName == "") {
            $this->varName = $varObject->getName();
        } elseif ($varName != "") {
            $this->varName = $varName;
        } else {
            $this->varName = 'valeur constante';
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getDisplayVarName() . ' à ' . $this->getFileName() . ':' . $this->getLineNumber();
    }

    //region Getter
    /**
     * @return string Le nom de la variable adapté pour l'affichage
     */
    public function getDisplayVarName(): string
    {
        return $this->varName == 'valeur constante' ? $this->varName : '$' . $this->varName;
    }

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
     * @return string
     */
    public function getVarName(): string
    {
        return $this->varName;
    }

    /**
     * @return Variable|null
     */
    public function getVarObject(): ?Variable
    {
        return $this->varObject;
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