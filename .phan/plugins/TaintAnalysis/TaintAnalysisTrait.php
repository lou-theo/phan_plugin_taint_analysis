<?php

use ast\Node;
use Phan\Language\Context;
use Phan\Language\Element\Func;
use Phan\Language\Element\Variable;
use Phan\Language\Scope;

Trait TaintAnalysisTrait
{
    /**
     * @param Variable $varObject La variable dont on veut les sources
     * @return array<VariableSource> La liste des sources de la variable
     */
    private function getVariableSources(Variable $varObject): array
    {
        if (property_exists($varObject, 'variableSources')) {
            return $varObject->variableSources;
        } else {
            return [];
        }
    }

    /**
     * @param Variable $varObject La variable dont on veut savoir si elle est globale (pour les variables dans les fonctions)
     * @return boolean Si la variable est globale ou non
     */
    private function isGlobal(Variable $varObject): bool
    {
        if (property_exists($varObject, 'global')) {
            return $varObject->global;
        } else {
            return false;
        }
    }

    /**
     * @param Func $func L'objet Func dont on veut la FonctionDefinition
     * @return FunctionDefinition
     */
    private function getFunctionDefinitionFromFunc(Func $func): FunctionDefinition
    {
        $functionName = $func->getName();
        $functionDefinition = null;
        if (isset(TaintAnalysisPlugin::$functionDefinitions[$functionName])) {
            $functionDefinition = TaintAnalysisPlugin::$functionDefinitions[$functionName];
        } else {
            $functionDefinition = new FunctionDefinition($func);
            TaintAnalysisPlugin::$functionDefinitions[$functionName] = $functionDefinition;
        }
        return $functionDefinition;
    }

    /**
     * @param array<VariableSource> $variableSources La liste des sources à trier
     * @return array<VariableSource> La liste des sources triées et unique
     */
    public function sortVariableSources(array $variableSources): array
    {
        $variableSources = array_unique($variableSources);
        usort($variableSources, function(VariableSource $a, VariableSource $b)
        {
            $fileComparison = strcmp($a->getFileName(), $b->getFileName());
            if ($fileComparison != 0) {
                return $fileComparison;
            } else {
                return $a->getLineNumber() - $b->getLineNumber();
            }
        });
        return $variableSources;
    }

    /**
     * Récupère la variable dans le scope depuis laquelle elle a été déclarée
     *
     * @param Scope $scopeExplored Le scope depuis lequel on commence les recherches
     * @param string $varName Le nom de la variable à récupérer
     * @return Variable|null La variable ou null si elle n'existe pas
     */
    private function getVariableFromDeclarationScope(Scope $scopeExplored, $varName): ?Variable
    {
        $variable = $scopeExplored->getVariableByNameOrNull($varName);
        while (
            $scopeExplored->hasParentScope() &&
            (
                (
                    $scopeExplored->isInFunctionLikeScope() &&
                    $scopeExplored->getParentScope()->isInFunctionLikeScope()
                ) ||
                !$scopeExplored->isInFunctionLikeScope() ||
                $this->isGlobal($variable)
            )
        ) {
            $scopeExplored = $scopeExplored->getParentScope();
            $variableInParentScope = $scopeExplored->getVariableByNameOrNull($varName);
            if ($variableInParentScope != null) {
                $variable = $variableInParentScope;
            }
        }

        return $variable;
    }

    /**
     * Extrait le nom de la variable contenu dans un noeud de type AST_VAR
     *
     * @param Node $varNode Le noeud dont on veut récupérer le nom de la variable
     * @return string Le nom de la variable contenu dans le noeud
     */
    private function extractNameFromVarNode(Node $varNode)
    {
        if ($varNode->kind == ast\AST_VAR) {
            return $varNode->children['name'];
        } else {
            return $this->extractNameFromVarNode($varNode->children['expr']);
        }
    }

    /**
     * Evalue une expression et en extrait les sources de transmission des données
     *
     * @param string|Node $expression L'expression dont on veut extraire les sources
     * @param Context $context Le context lié à l'expression
     * @return array<VariableSource> La liste des sources contenues dans l'expression
     */
    private function extractVariableSourcesFromExpression($expression, Context $context): array
    {
        if (!is_object($expression)) {
            // si l'expression est quelque chose de constant (une chaine de caractère ou un int)
            return [new VariableSource($context, null)];
        }

        switch ($expression->kind ) {
            case ast\AST_VAR:
                // l'expression est une variable, on évalue si elle est teintée
                $varName = $this->extractNameFromVarNode($expression);;
                $variableSource = $this->createVariableSourceFromName($varName, $context);
                return [$variableSource];
            case ast\AST_DIM:
                // l'expression est une array à laquelle on accède
                $subExpression = $expression->children['expr'];
                return $this->extractVariableSourcesFromExpression($subExpression, $context);
            case ast\AST_BINARY_OP:
                // on retourne le merge des 2 sous expressions
                $leftSubExpression = $expression->children['left'];
                $rightSubExpression = $expression->children['right'];
                return array_merge(
                    $this->extractVariableSourcesFromExpression($leftSubExpression, $context),
                    $this->extractVariableSourcesFromExpression($rightSubExpression, $context)
                );

            default:
                // cas actuellement non prévu
                return [];
        }
    }

    /**
     * Crée une VariableSource à partir du nom d'une variable qui est une source de transmission de données
     *
     * @param string $varName Le nom de la variable source d'information
     * @param Context $context Le contexte lié à la source
     * @return VariableSource La source
     */
    private function createVariableSourceFromName(string $varName, Context $context): VariableSource
    {
        // on regarde si la variable est une des sources absolues de contagion
        if (in_array($varName, TaintAnalysisPlugin::$taintednessRoots)) {
            return new VariableSource($context, null, $varName);
        }

        $variable = $this->getVariableFromDeclarationScope($context->getScope(), $varName);
        if (!$variable) {
//            throw new Exception("Cas inconnu : pas de variable ?");
            return new VariableSource($context, null, $varName);
        }
        return new VariableSource($context, $variable);
    }
}