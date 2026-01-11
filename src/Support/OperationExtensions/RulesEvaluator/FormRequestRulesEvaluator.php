<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator;

use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Illuminate\Http\Request;

class FormRequestRulesEvaluator implements RulesEvaluator
{
    private array $messages = [];
    public function __construct(
        private ClassReflector $classReflector,
        private string $method,
    ) {}

    public function handle(): array
    {
        return $this->rules($this->classReflector->className, $this->method);
    }

    protected function rules(string $requestClassName, string $method)
    {
        /** @var Request $request */
        $request = (new $requestClassName);

        $rules = [];

        if (method_exists($request, 'setMethod')) {
            $request->setMethod(strtoupper($method));
        }

        if (method_exists($request, 'rules')) {
            $rules = $request->rules();
        }

        if (method_exists($request, 'messages')) {
            $this->messages = $request->messages();
        }

        return $rules;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}
