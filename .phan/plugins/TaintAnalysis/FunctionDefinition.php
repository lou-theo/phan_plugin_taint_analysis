<?php

use Phan\Language\Element\Func;

class FunctionDefinition
{
    /**
     * @var Func
     */
    private $func;

    /**
     * @var array<OutputToEvaluate>
     */
    private $outputs = [];

    /**
     * @var array<VariableSource>
     */
    private $returnSources = [];

    public function __construct(Func $func)
    {
        $this->func = $func;
    }

    //region Getter
    /**
     * @return Func
     */
    public function getFunc(): Func
    {
        return $this->func;
    }

    /**
     * @return array<OutputToEvaluate>
     */
    public function getOutputs(): array
    {
        return $this->outputs;
    }

    /**
     * @param OutputToEvaluate $outputToEvaluate
     */
    public function addOutput(OutputToEvaluate $outputToEvaluate)
    {
        $this->outputs[] = $outputToEvaluate;
    }

    /**
     * @return array<VariableSource>
     */
    public function getReturnSources(): array
    {
        return $this->returnSources;
    }

    /**
     * @param array<VariableSource> $variableSources
     */
    public function addReturnSources(array $variableSources)
    {
        $this->returnSources = array_merge($this->returnSources, $variableSources);
    }
    //endregion
}