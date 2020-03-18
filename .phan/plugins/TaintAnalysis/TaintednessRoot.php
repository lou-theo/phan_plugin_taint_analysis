<?php


class TaintednessRoot
{
    /**
     * @var string le nom de la variable qui transmet l'infection
     */
    public $varName;

    /**
     * @var int le numéro de la ligne où la transmission se fait
     */
    public $lineNumber;

    /**
     * @var string le nom du ficher concerné
     */
    public $fileName;

    /**
     * TaintednessRoot constructor.
     * @param string $varName
     * @param int $lineNumber
     * @param string $fileName
     */
    public function __construct(string $varName, int $lineNumber, string $fileName)
    {
        $this->varName = $varName;
        $this->lineNumber = $lineNumber;
        $this->fileName = $fileName;
    }

    public function __toString()
    {
        return $this->getVarName() . ' à ' . $this->fileName . ':' . $this->lineNumber;
    }

    /**
     * @return bool|string
     */
    public function getVarName(): string {
        return $this->varName == 'valeur constante' ? $this->varName : '$' . $this->varName;
    }
}