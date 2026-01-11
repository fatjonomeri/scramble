<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;

class ComposedFormRequestRulesEvaluator implements RulesEvaluator
{
    private array $messages = [];
    public function __construct(
        private PrettyPrinter $printer,
        private ClassReflector $classReflector,
        private string $method,
    ) {}

    public function handle(): array
    {
        $rulesMethodNode = $this->classReflector->getMethod('rules')->getAstNode();

        $returnNode = (new NodeFinder)->findFirst(
            $rulesMethodNode ? [$rulesMethodNode] : [],
            fn ($node) => $node instanceof Return_ && $node->expr instanceof Array_
        )?->expr ?? null;

        $evaluators = [
            $formRequestEvaluator = new FormRequestRulesEvaluator($this->classReflector, $this->method),
            new NodeRulesEvaluator($this->printer, $rulesMethodNode, $returnNode, $this->method, $this->classReflector->className),
        ];

        foreach ($evaluators as $evaluator) {
            try {
                $rules = $evaluator->handle();
                
                // NEW: If it's FormRequestRulesEvaluator, capture messages
                if ($evaluator instanceof FormRequestRulesEvaluator) {
                    $this->messages = $evaluator->getMessages();
                }
                
                return $rules;
            } catch (\Throwable $e) {
                // @todo communicate error
            }
        }

        return [];
    }

    // NEW: Getter for messages
    public function getMessages(): array
    {
        return $this->messages;
    }
}
