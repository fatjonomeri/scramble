<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\RulesDocumentationRetriever;
use PhpParser\Node;

trait GeneratesParametersFromRules
{
    /**
     * @param  array<string, RuleSet>  $rules
     * @param  Node[]|RulesDocumentationRetriever  $rulesDocsRetriever
     * @param  array<string, string>  $messages
     * @return Parameter[]
     */
    private function makeParameters($rules, TypeTransformer $typeTransformer, array|RulesDocumentationRetriever $rulesDocsRetriever = [], string $in = 'query', array $messages = []): array
    {
        return (new RulesToParameters($rules, $rulesDocsRetriever, $typeTransformer, $in, $messages))->mergeDotNotatedKeys(false)->handle();
    }
}
