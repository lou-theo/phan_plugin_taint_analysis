<?php

use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Scope;

require_once __DIR__ . "/Source.php";

/**
 * Représente les sources de transmission de données des variables
 */
class VariableSource extends Source
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
     * VariableSource constructor.
     * @param Context $context
     * @param Variable|null $varObject
     * @param string $varName
     */
    public function __construct(Context $context, ?Variable $varObject, string $varName = "")
    {
        parent::__construct($context);
        $this->varObject = $varObject;
        if ($varObject != null && $varName == "") {
            $this->varName = $varObject->getName();
        } elseif ($varName != "") {
            $this->varName = $varName;
        } else {
            $this->varName = 'valeur constante';
        }
    }

    /**
     * @inheritDoc
     */
    public function getDisplayName(): string
    {
        return $this->varName == 'valeur constante' ? $this->varName : '$' . $this->varName;
    }

    //region Getter

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
    //endregion
}