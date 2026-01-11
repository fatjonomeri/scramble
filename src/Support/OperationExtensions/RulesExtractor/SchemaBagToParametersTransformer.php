<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiSchema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

/**
 * @internal
 */
class SchemaBagToParametersTransformer
{
    public function __construct(
        private TypeTransformer $openApiTransformer,
        private bool $mergeDotNotatedKeys = true,
        /** @var array<string, PhpDocNode> */
        private array $rulesDocs = [],
        private string $in = 'query',
        /** @var array<string, string> */
        private array $messages = [], // NEW: Add messages
    ) {}

    /** @return Parameter[] */
    public function handle(SchemaBag $schemaBag): array
    {
        return $this->transformSchemaBagToParameters($schemaBag);
    }

    /** @return array<int, Parameter> */
    private function transformSchemaBagToParameters(SchemaBag $schemaBag): array
    {
        return collect($schemaBag->all())
            ->reject(fn ($_, $name) => $this->shouldIgnoreParameter($name))
            ->map(function ($schema, $name) {
                if (! $rulesDocs = $this->rulesDocs[$name] ?? null) {
                    return $schema;
                }

                return (new PhpDocSchemaTransformer($this->openApiTransformer))->transform($schema, $rulesDocs);
            })
            ->map(fn ($schema, $name) => $this->makeParameterFromSchema($schema, $name))
            ->values()
            ->pipe(fn ($c) => $this->mergeDotNotatedKeys ? collect((new DeepParametersMerger($c))->handle()) : $c)
            ->all();
    }

    protected function shouldIgnoreParameter(string $name): bool
    {
        $rulesDocs = $this->rulesDocs[$name] ?? null;

        return (bool) ($rulesDocs?->getTagsByName('@ignoreParam') ?? []);
    }

    protected function makeParameterFromSchema(OpenApiSchema $schema, string $name): Parameter
    {
        $description = $schema->description;
        $example = $schema->example;

        // NEW: Get structured validation messages
        $validationMessages = $this->getStructuredMessagesForField($name);
        if (!empty($validationMessages)) {
            // Add messages as an OpenAPI extension
            $schema->setExtensionProperty('validation-messages', $validationMessages);
        }

        $schema->setDescription('')->example(new MissingValue);

        return Parameter::make($name, $schema->getAttribute('isInQuery') ? 'query' : $this->in)
            ->setSchema(Schema::fromType($schema))
            ->example($example)
            ->required((bool) $schema->getAttribute('required', false))
            ->description($description);
    }

    /**
     * Get structured validation messages for a specific field
     * Maps rule names to their custom messages
     * 
     * @param string $fieldName
     * @return array<string, string>
     */
    private function getStructuredMessagesForField(string $fieldName): array
    {
        $structuredMessages = [];
        
        foreach ($this->messages as $key => $message) {
            // Match patterns like "fieldname.rulename"
            if (str_starts_with($key, $fieldName . '.')) {
                // Extract the rule name (e.g., "email.required" -> "required")
                $ruleName = substr($key, strlen($fieldName) + 1);
                $structuredMessages[$ruleName] = $message;
            }
            // Also match exact field name (for general field messages)
            elseif ($key === $fieldName) {
                $structuredMessages['_default'] = $message;
            }
        }
        
        return $structuredMessages;
    }
}
