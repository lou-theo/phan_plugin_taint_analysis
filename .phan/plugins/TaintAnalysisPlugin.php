<?php

declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\Variable;
use Phan\PluginV3;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Phan\Debug;

require_once __DIR__ . "/TaintAnalysis/Source.php";
require_once __DIR__ . "/TaintAnalysis/VariableSource.php";
require_once __DIR__ . "/TaintAnalysis/FunctionSource.php";
require_once __DIR__ . "/TaintAnalysis/FunctionDefinition.php";
require_once __DIR__ . "/TaintAnalysis/OutputToEvaluate.php";
require_once __DIR__ . "/TaintAnalysis/TaintAnalysisTrait.php";

/**
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV3
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
class TaintAnalysisPlugin extends PluginV3 implements PostAnalyzeNodeCapability, FinalizeProcessCapability
{
    use TaintAnalysisTrait;

    /**
     * @var array<OutputToEvaluate> La liste des outputs à évaluer en global
     */
    public static $outputsToEvaluate = [];

    /**
     * @var array<string> Le nom des variables qui sont des sources de contamination absolues
     */
    public static $taintednessRoots = ["_GET", "_POST", "_GLOBAL", "_SERVER"];

    /**
     * @var array<FunctionDefinition> La liste des fonctions
     */
    public static $functionDefinitions = [];

    /**
     * @var array<FunctionSource> La liste des appels de fonction qui sont à analyser
     */
    public static $functionCallsToAnalyze = [];

    /**
     * @var array<FunctionSource> Les appels de fonctions qui ont déjà été étudiés pour la recherche des output dans les fonctions
     */
    private $functionCallsAlreadyVisited = [];

    /**
     * @var array<Source> La liste des sources déjà visitées dans l'analyse de chaque output
     */
    private $sourcesAlreadyVisited = [];

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return TaintAnalysisVisitor::class;
    }

    /**
     * @inheritDoc
     */
    public function finalizeProcess(CodeBase $code_base): void
    {
        $this->handleGlobalOutputs();
        self::$functionCallsToAnalyze = array_unique(self::$functionCallsToAnalyze);
        foreach (self::$functionCallsToAnalyze as $functionCall) {
            $this->handleFunctionOutputs($functionCall);
        }
    }

    /**
     * Gère la recherche des contaminations dans les outputs en général
     */
    private function handleGlobalOutputs()
    {
        // on parcourt chacun des outputs qui a été récolté pendant l'analyse
        /** @var OutputToEvaluate $output */
        foreach (self::$outputsToEvaluate as $output) {
            // on récupère les différentes sources d'informations dirrectement contenues dans l'expression
            $sources = $this->extractSourcesFromExpression($output->getExpression(), $output->getContext());
            $sources = $this->sortUniqueSources($sources);
            $evilSources = [];
            /** @var Source $source */
            foreach ($sources as $source) {
                // on parcourt chacune des sources récupérées pour voir si elles contiennent des sources potentielles de contamination
                $this->sourcesAlreadyVisited = [];
                $currentEvilSources = $this->evaluateEvilSources($source);
                if (count($currentEvilSources) != 0) {
                    $currentEvilSources = $this->sortUniqueSources($currentEvilSources);
                    $evilSources[$source->getDisplayName()] = $currentEvilSources;
                }
            }

            if (count($evilSources) != 0) {
                $stringOutput = "";
                foreach ($evilSources as $key => $evilSource) {
                    $stringOutput .= $key . ' (via ' . implode(' ; ', $evilSource) . ') ; ';
                }
                $this->emitPluginIssue(
                    $output->getCodeBase(),
                    $output->getContext(),
                    'TaintAnalysisPlugin',
                    "Une expression potentiellement infectée est affichée. Les sources potentielles d'infection sont : {STRING_LITERAL}",
                    [$stringOutput],
                    Issue::SEVERITY_NORMAL
                );
            }
        }
    }

    /**
     * Gère la recherche des contaminations dans les outputs des fonctions
     * @param Source $functionSource
     */
    private function handleFunctionOutputs(Source $functionSource)
    {
        if (!($functionSource instanceof FunctionSource) || !isset(self::$functionDefinitions[$functionSource->getFunctionName()])) {
            return;
        }
        /** @var FunctionDefinition $functionDefinition */
        $functionDefinition = self::$functionDefinitions[$functionSource->getFunctionName()];
        $functionSource->setFunctionDefinition($functionDefinition);

        foreach ($functionDefinition->getOutputs() as $output) {
            $evilSources = [];
            $sources = $this->extractSourcesFromExpression($output->getExpression(), $output->getContext());
            $sources = $this->sortUniqueSources($sources);
            /** @var Source $source */
            foreach ($sources as $source) {
                $this->sourcesAlreadyVisited = [];
                $currentEvilSources = $this->evaluateEvilSources($source, [$functionSource]);
                if (count($currentEvilSources) != 0) {
                    $currentEvilSources = $this->sortUniqueSources($currentEvilSources);
                    $evilSources[$source->getDisplayName()] = $currentEvilSources;
                }
            }

            if (count($evilSources) != 0) {
                $stringOutput = "";
                foreach ($evilSources as $key => $evilSource) {
                    $stringOutput .= $key . ' (via ' . implode(' ; ', $evilSource) . ') ; ';
                }
                $this->emitPluginIssue(
                    $output->getCodeBase(),
                    $output->getContext(),
                    'TaintAnalysisPlugin',
                    "Une expression potentiellement infectée est affichée via l'appel de la fonction {STRING_LITERAL}. Les sources potentielles d'infection sont : {STRING_LITERAL}",
                    [$functionSource, $stringOutput],
                    Issue::SEVERITY_NORMAL
                );
            }
        }

        foreach ($functionDefinition->getFunctionCallToVisitList() as $functionCallToVisit) {
            if (!in_array($functionCallToVisit, $this->functionCallsAlreadyVisited, true)) {
                $this->functionCallsAlreadyVisited[] = $functionCallToVisit;
                $this->handleFunctionOutputs($functionCallToVisit);
            }
        }
    }

    /**
     * Cherche les sources de contamination potentielles contenu dans une source
     *
     * @param Source $source La source à évaluer
     * @param array<FunctionSource> $currentFunctionSources Le stack des appels de fonction étudiés
     * @return array<Source> La liste des sources contaminées trouvées
     */
    private function evaluateEvilSources(Source $source, array $currentFunctionSources = []): array
    {
        $evilSources = [];
        $this->sourcesAlreadyVisited[] = $source;

        if ($source instanceof FunctionSource && isset(self::$functionDefinitions[$source->getFunctionName()])) {
            /** @var FunctionDefinition $functionDefinition */
            $functionDefinition = self::$functionDefinitions[$source->getFunctionName()];
            $parentSources = $functionDefinition->getReturnSources();

            $currentFunctionSources[] = $source;
            $source->setFunctionDefinition($functionDefinition);
        }
        else {
            if (in_array($source->getVarName(), self::$taintednessRoots, true)) {
                // Si le nom de la variable lié à la source est une des sources de contamination absolue
                $evilSources[] = $source;
            }
            if ($source->getVarObject() == null) {
                return $evilSources;
            }
            $parentSources = $this->getSources($source->getVarObject());

            // si on est dans le cas où on étudie un appel de fonction,
            // on regarde si la variable source actuellement étudié n'est pas dans les paramètres de la fonction
            // si c'est le cas, on ajoute les sources des paramètres d'appel de la fonction
            $currentFunctionSource = count($currentFunctionSources) != 0 ? $currentFunctionSources[count($currentFunctionSources) - 1] : null;
            if (
                $currentFunctionSource != null
                && ($indexParameter = $currentFunctionSource->getFunctionDefinition()->getParameterIndexOrNull($source->getVarName())) !== null
            ) {
                array_pop($currentFunctionSources);
                $parentSources = array_merge(
                    $parentSources,
                    $this->extractSourcesFromExpression($currentFunctionSource->getExpressionArguments()[$indexParameter], $currentFunctionSource->getContext())
                );
            }
        }

        // on fait une recherche récursive parmi les sources de la source
        /** @var Source $parentSource */
        foreach ($parentSources as $parentSource) {
            // on fait attention à éviter les dépendances circulaires
            if (!in_array($parentSource, $this->sourcesAlreadyVisited, true) && count($this->evaluateEvilSources($parentSource, $currentFunctionSources)) != 0) {
                $evilSources[] = $parentSource;
            }
        }

        return $evilSources;
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class TaintAnalysisVisitor extends PluginAwarePostAnalysisVisitor
{
    use TaintAnalysisTrait;

    /**
     * @override
     * @param Node $node
     */
    public function visitAssign(Node $node): void
    {
//        \Phan\Debug::printNode($node);

        $expression = $node->children['expr'];
        $sources = $this->extractSourcesFromExpression($expression, $this->context);

        // on récupère la variable qui se fait assigner
        $varNode = $node->children['var'];
        $varName = $this->extractNameFromVarNode($varNode);
        $varObject = $this->getVariableFromDeclarationScope($this->context->getScope(), $varName);

        $this->addSources($varObject, $sources);
    }

    /**
     * @override
     * @param Node $node
     */
    public function visitGlobal(Node $node)
    {
        $varNode = $node->children['var'];
        $varName = $this->extractNameFromVarNode($varNode);
        $varObject = $this->getVariableFromDeclarationScope($this->context->getScope(), $varName);
        $varObject->global = true;
    }

    /**
     * @override
     * @param Node $node
     */
    public function visitEcho(Node $node)
    {
//        \Phan\Debug::printNode($node);
        $expression = $node->children['expr'];
        $output = new OutputToEvaluate($expression, $this->code_base, $this->context);

        if (!$this->context->isInFunctionLikeScope()) {
            TaintAnalysisPlugin::$outputsToEvaluate[] = $output;
        } else {
            $func = $this->context->getFunctionLikeInScope($this->code_base);
            $functionDefinition = $this->getFunctionDefinitionFromFunc($func);
            $functionDefinition->addOutput($output);
        }
    }

    /**
     * @override
     * @param Node $node
     */
    public function visitCall(Node $node)
    {
        $functionSources = $this->extractSourcesFromExpression($node, $this->context);

        if (!$this->context->isInFunctionLikeScope()) {
            TaintAnalysisPlugin::$functionCallsToAnalyze = array_merge(
                TaintAnalysisPlugin::$functionCallsToAnalyze,
                $functionSources
            );
        } else {
            $func = $this->context->getFunctionLikeInScope($this->code_base);
            $functionDefinition = $this->getFunctionDefinitionFromFunc($func);
            $functionDefinition->addFunctionCallToVisit($functionSources);
        }
    }

    /**
     * @override
     * @param Node $node
     */
    public function visitReturn(Node $node)
    {
        $expression = $node->children['expr'];
        $sources = $this->extractSourcesFromExpression($expression, $this->context);
        $sources = $this->sortUniqueSources($sources);

        $func = $this->context->getFunctionLikeInScope($this->code_base);
        $functionDefinition = $this->getFunctionDefinitionFromFunc($func);
        $functionDefinition->addReturnSources($sources);
    }

    /**
     * @param Variable $varObject La variable dont on veut ajouter des sources
     * @param array<Source> $newSources La liste des sources que l'on veut ajouter
     */
    private function addSources(Variable $varObject, array $newSources): void
    {
        $sources = $this->getSources($varObject);
        $sources = array_merge($sources, $newSources);
        $sources = $this->sortUniqueSources($sources);
        $varObject->sources = $sources;
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new TaintAnalysisPlugin();