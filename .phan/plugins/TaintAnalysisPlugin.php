<?php

declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\Variable;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Phan\Debug;

require_once __DIR__ . "/TaintAnalysis/VariableSource.php";
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
class TaintAnalysisPlugin extends PluginV3 implements PostAnalyzeNodeCapability, PluginV3\FinalizeProcessCapability
{
    use TaintAnalysisTrait;

    /**
     * @var array<OutputToEvaluate>
     */
    public static $outputsToEvaluate = [];

    /**
     * @var array<string> Le nom des variables qui sont des sources de contamination absolues
     */
    public static $taintednessRoots = ["_GET", "_POST", "_GLOBAL", "_SERVER"];

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
        // on parcourt chacun des outputs qui a été récolté pendant l'analyse
        /** @var OutputToEvaluate $output */
        foreach (self::$outputsToEvaluate as $output) {
            // on récupère les différentes sources d'informations dirrectement contenues dans l'expression
            $sources = $this->extractVariableSourcesFromExpression($output->getExpression(), $output->getContext());
            $sources = array_unique($sources);
            $evilSources = [];
            foreach ($sources as $source) {
                // on parcourt chacune des sources récupérées pour voir si elles contiennent des sources potentielles de contamination
                $evilSources = array_merge($evilSources, $this->evaluateEvilSources($source));
            }
            $evilSources = array_unique($evilSources);

            if (count($evilSources) != 0) {
                $this->emitPluginIssue(
                    $output->getCodeBase(),
                    $output->getContext(),
                    'TaintAnalysisPlugin',
                    "Une expression potentiellement infectée est affichée. Les sources potentielles d'infection sont : {STRING_LITERAL}",
                    [implode(' ; ', $evilSources)],
                    Issue::SEVERITY_NORMAL
                );
            }
        }
    }

    /**
     * Cherche les sources de contamination potentielles contenu dans une source
     *
     * @param VariableSource $source La source à évaluer
     * @return array<VariableSource> La liste des sources contaminées trouvées
     */
    private function evaluateEvilSources(VariableSource $source): array
    {
        $evilSources = [];
        $firstSourceName = $source->getVarName();

        if (in_array($source->getVarName(), self::$taintednessRoots)) {
            // Si le nom de la variable lié à la source est une des sources de contamination absolue
            $evilSources[] = $source;
        }
        if ($source->getVarObject() == null) {
            return $evilSources;
        }

        // on fait une recherche récursive parmi les sources de la source
        $parentSources = $this->getVariableSources($source->getVarObject());
        if (count($parentSources) != 0) {
            foreach ($parentSources as $parentSource) {
                // on fait attention à éviter les dépendances circulaires
                if ($parentSource->getVarName() != $firstSourceName) {
                    $evilSources = array_merge($evilSources, $this->evaluateEvilSources($parentSource));
                }
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
        $sources = $this->extractVariableSourcesFromExpression($expression, $this->context);

        // on récupère la variable qui se fait assigner
        $varNode = $node->children['var'];
        $varName = $this->extractNameFromVarNode($varNode);
        $varObject = $this->getVariableFromDeclarationScope($this->context->getScope(), $varName);

        $this->addVariableSources($varObject, $sources);
    }

    /**
     * @override
     * @param Node $node
     */
    public function visitEcho(Node $node)
    {
//        \Phan\Debug::printNode($node);

        $expression = $node->children['expr'];
        TaintAnalysisPlugin::$outputsToEvaluate[] = new OutputToEvaluate($expression, $this->code_base, $this->context);
    }

    /**
     * @param Variable $varObject La variable dont on veut ajouter des sources
     * @param array<VariableSource> $newVariableSources La liste des sources que l'on veut ajouter à la variable
     */
    private function addVariableSources(Variable $varObject, array $newVariableSources): void
    {
        $variableSources = $this->getVariableSources($varObject);
        $variableSources = array_unique(array_merge($variableSources, $newVariableSources));
        usort($variableSources, function(VariableSource $a, VariableSource $b)
        {
            $fileComparison = strcmp($a->getFileName(), $b->getFileName());
            if ($fileComparison != 0) {
                return $fileComparison;
            } else {
                return $a->getLineNumber() - $b->getLineNumber();
            }
        });
        $varObject->variableSources = $variableSources;
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new TaintAnalysisPlugin();