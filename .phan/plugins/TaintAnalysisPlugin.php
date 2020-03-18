<?php

declare(strict_types=1);

use ast\Node;
use Phan\Issue;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\Variable;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Phan\Debug;

require_once __DIR__ . "/TaintAnalysis/TaintednessRoot.php";

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
class TaintAnalysisPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return TaintAnalysisVisitor::class;
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
    /**
     * Visite d'une assignation afin de voir quelles sont les variables infectées
     *
     * @override
     * @param Node $node
     */
    public function visitAssign(Node $node): void {
        \Phan\Debug::printNode($node);

        // on regarde si l'expression est teinte
        $expression = $node->children['expr'];
        $expressionTaintedness = $this->evaluateExprTaintedness($expression, false);

        // on récupère la variable qui se fait assigner
        $var = $node->children['var'];
        $varName = $var->children['name'];
        $varObject = $this->context->getScope()->getVariableByNameOrNull($varName);

        if (count($expressionTaintedness) == 0) {
            // si l'expression est saine on efface les sources possibles de contagion
//             $this->resetVarTaintedness($varObject);
        } else {
            // si l'expression est teinte, on ajoute les sources de teintes potentielles
//            $this->resetVarTaintedness($varObject);
            $this->addVarTaintedness($varObject, $expressionTaintedness);
        }
    }

    /**
     * Visite d'un echo afin de vérifier si les expressions affichées ne contiennent pas de variables infectées
     *
     * @override
     * @param Node $node
     */
    public function visitEcho(Node $node)
    {
        \Phan\Debug::printNode($node);

        $expression = $node->children['expr'];
        $expressionTaintedness = $this->evaluateExprTaintedness($expression, true);

        if (count($expressionTaintedness) == 0) {
            var_dump('OK');
        } else {
            var_dump('KO');
//            var_dump($expressionTaintedness);

            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'TaintAnalysisPlugin',
                "Une expression potentiellement infectée est affichée. Les sources potentielles sont : {STRING_LITERAL}",
                [implode(' ; ', $expressionTaintedness)],
                Issue::SEVERITY_NORMAL
            );
        }
    }

    /**
     * Evalue et retourne les sources de contamination potentielles pour une expression
     *
     * @param $expression L'expression à annalyser
     * @param bool $searchMode True si on cherche les antécédents (à utiliser lors des prints) false sinon (pour les assignations)
     * @return array<TaintednessRoot> la liste des sources possibles de contagion de l'expression
     */
    private function evaluateExprTaintedness($expression, bool $searchMode): array
    {
        if (!is_object($expression)) {
            // si l'expression est quelque chose de constant (une chaine de caractère ou un int)
            return [];
        }

        switch ($expression->kind ) {
            case ast\AST_VAR:
                // l'expression est une variable, on évalue si elle est teintée
                $varName = $expression->children['name'];
                return $this->evaluateVarTaintedness($varName, $searchMode);
            case ast\AST_DIM:
                // l'expression est une array à laquelle on accède
                $subExpression = $expression->children['expr'];
                return $this->evaluateExprTaintedness($subExpression, $searchMode);
            case ast\AST_BINARY_OP:
                // on retourne le merge des 2 sous expressions
                $leftSubExpression = $expression->children['left'];
                $rightSubExpression = $expression->children['right'];
                return array_merge($this->evaluateExprTaintedness($leftSubExpression, $searchMode), $this->evaluateExprTaintedness($rightSubExpression, $searchMode));

            default:
                // cas actuellement non prévu
                return [];
        }
    }

    /**
     * Evalue et retourne les sources de contamination potentielles pour une variable
     *
     * @param string $varName le nom de la variable à analyser
     * @param bool $searchMode True si on cherche les antécédents (à utiliser lors des prints) false sinon (pour les assignations)
     * @return array<TaintednessRoot> la liste des sources possibles de contagion de la variable
     */
    private function evaluateVarTaintedness(string $varName, bool $searchMode): array
    {
        // on regarde si la variable est une des sources absolues de contagion
        if ($varName == "_GET" || $varName == "_POST" || $varName == "_GLOBAL" || $varName == "_SERVER") {
            return [new TaintednessRoot($varName, $this->context->getLineNumberStart(), $this->context->getFile())];
        }

        // on récupère la variable dont on prend la valeur afin de voir si elle est contaminée
        $variable = $this->context->getScope()->getVariableByNameOrNull($varName);
        if (!$variable) {
            return [];
        }
        $varTaintedness = $this->getVarTaintedness($variable);

        if (count($varTaintedness) == 0) {
            // si la variable n'est pas contaminée, on a pas de source de contamination
            return [];
        } elseif ($searchMode) {
            // si on cherche à avoir les origines de contamination de la variable
            return $this->getVarTaintedness($variable);
        } else {
            // la variable est contaminée, on l'ajoute en tant que source de contamination
            return [new TaintednessRoot($varName, $this->context->getLineNumberStart(), $this->context->getFile())];
        }
    }

    /**
     * Permet d'obtenir la liste des sources de contagion qui ont été attribuées à une variable
     *
     * @param Variable $varObject La variable dont on veut obtenir des infos
     * @return array<TaintednessRoot> La liste des sources possibles de contamination
     */
    private function getVarTaintedness(Variable $varObject): array
    {
        if (property_exists($varObject, 'taintednessRoots')) {
            return $varObject->taintednessRoots;
        } else {
            return [];
        }
    }

    /**
     * Ajoute des sources de contamination possibles à une variable
     *
     * @param Variable $varObject La variable dont on veut ajouter des sources de contamination
     * @param array<TaintednessRoot> $expressionTaintedness Liste des sources de contamination à ajouter
     */
    private function addVarTaintedness(Variable $varObject, array $expressionTaintedness): void
    {
        $taintednessRoots = $this->getVarTaintedness($varObject);
        $taintednessRoots = array_unique(array_merge($taintednessRoots, $expressionTaintedness));
        usort($taintednessRoots, function(TaintednessRoot $a, TaintednessRoot $b)
        {
            $fileComparison = strcmp($a->fileName, $b->fileName);
            if ($fileComparison != 0) {
                return $fileComparison;
            } else {
                return $a->lineNumber - $b->lineNumber;
            }
        });
        $varObject->taintednessRoots = $taintednessRoots;
    }

    /**
     * Efface la liste de toutes les sources de contamination d'une variable
     *
     * @param Variable $varObject le nom de la variable a réinitialiser
     */
    private function resetVarTaintedness(Variable $varObject): void
    {
        $varObject->taintednessRoots = [];
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new TaintAnalysisPlugin();