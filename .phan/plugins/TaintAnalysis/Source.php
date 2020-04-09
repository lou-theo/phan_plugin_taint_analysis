<?php

use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Scope;

/**
 * Représente les sources de transmission de données
 */
abstract class Source
{
    /**
     * @var Context Le contexte lié à la source
     */
    protected $context;

    /**
     * @var int Le numéro de la ligne où la source transmet ses données
     */
    protected $lineNumber;

    /**
     * Source constructor.
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->lineNumber = $context->getLineNumberStart();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getDisplayName() . ' à ' . $this->getFileName() . ':' . $this->getLineNumber();
    }

    /**
     * @return string Le nom de la source adapté pour l'affichage
     */
    public abstract function getDisplayName(): string;

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
     * @return Context
     */
    public function getContext(): Context
    {
        $this->context->setLineNumberStart($this->getLineNumber());
        return $this->context;
    }
    //endregion
}