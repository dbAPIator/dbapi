<?php defined('BASEPATH') OR exit('No direct script access allowed');

use Opis\JsonSchema\Validator;

class OpenApiBodyValidator
{
    /** @var array<string,mixed> */
    private $spec;

    public function __construct(string $specPath)
    {
        $raw = file_get_contents($specPath);
        if ($raw === false) {
            throw new RuntimeException("Cannot read OpenAPI spec: {$specPath}");
        }

        if (preg_match('/\.ya?ml$/i', $specPath)) {
            if (!function_exists('parse_mgmt_openapi_yaml')) {
                require_once APPPATH . 'helpers/swagger/SpecBuilder.php';
            }
            $decoded = parse_mgmt_openapi_yaml($raw);
        } else {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new RuntimeException("OpenAPI spec is not valid JSON: {$specPath}");
            }
        }

        $this->spec = $decoded;
    }

    /**
     * Validates decoded JSON payload against OpenAPI requestBody schema for the given method+path.
     * Throws InvalidArgumentException with JSON-encoded error array on validation failure.
     */
    public function validate(string $method, string $actualPath, $decodedJson): void
    {
        $method = strtolower($method);
        $tmpl = $this->matchPathTemplate($actualPath);

        $op = $this->spec['paths'][$tmpl][$method] ?? null;
        if (!is_array($op)) {
            throw new RuntimeException("No operation for {$method} {$tmpl}");
        }

        // Accept application/json or merge-patch for PATCH
        $content = $op['requestBody']['content'] ?? [];
        $schemaNode =
            ($content['application/json']['schema'] ?? null) ??
            ($content['application/merge-patch+json']['schema'] ?? null);

        if (!$schemaNode) {
            // For admin API I would treat this as a server misconfig.
            throw new RuntimeException("No requestBody schema for {$method} {$tmpl}");
        }

        $schemaDoc = $this->buildJsonSchemaDocument($schemaNode);

        $validator = new Validator();
        $result = $validator->validate($decodedJson, json_encode($schemaDoc));

        if (!$result->isValid()) {
            $errs = [];
            $this->collectValidationErrors($result->error(), $errs);
            throw new InvalidArgumentException(json_encode($errs));
        }
    }

    /**
     * Flatten Opis v2 ValidationError tree into list of { keyword, message, dataPath, schemaPath, property? }.
     * Only collects leaf errors (no subErrors) so the response shows the actual failing property.
     * @param \Opis\JsonSchema\Errors\ValidationError $error
     * @param array $errs
     */
    private function collectValidationErrors($error, array &$errs): void
    {
        if ($error === null) {
            return;
        }
        $subErrors = $error->subErrors();
        if ($subErrors !== []) {
            foreach ($subErrors as $sub) {
                $this->collectValidationErrors($sub, $errs);
            }
            return;
        }
        $dataPath = $error->data()->fullPath();
        $schemaPath = $error->schema()->info()->path();
        $entry = [
            'keyword'    => $error->keyword(),
            'message'   => $error->message(),
            'dataPath'  => $dataPath === [] ? '/' : '/' . implode('/', array_map('strval', $dataPath)),
            'schemaPath' => $schemaPath === [] ? '/' : '/' . implode('/', array_map('strval', $schemaPath)),
        ];
        $args = $error->args();
        if ($error->keyword() === 'required' && isset($args['missing']) && is_array($args['missing'])) {
            $entry['properties'] = $args['missing'];
            if (count($args['missing']) === 1) {
                $entry['property'] = $args['missing'][0];
            }
        }
        if ($error->keyword() === 'type' && isset($args['expected'])) {
            $entry['expected'] = $args['expected'];
        }
        $errs[] = $entry;
    }

    private function matchPathTemplate(string $actualPath): string
    {
        if (isset($this->spec['paths'][$actualPath])) return $actualPath;

        foreach ($this->spec['paths'] as $template => $_) {
            $pattern = preg_replace('#\{[^/]+\}#', '[^/]+', $template);
            if (preg_match('#^' . $pattern . '$#', $actualPath)) {
                return $template;
            }
        }
        return $actualPath; // will fail later with "No operation"
    }

    /** @param array<string,mixed> $schemaNode */
    private function buildJsonSchemaDocument(array $schemaNode): array
    {
        $defs = $this->spec['components']['schemas'] ?? [];

        // Rewrite local OpenAPI refs (#/components/schemas/X) to JSON Schema defs (#/$defs/X)
        // in both the request schema and inside every component schema (nested refs)
        $schemaNode = $this->rewriteRefsToDefs($schemaNode);
        $defs = array_map(function ($schema) {
            return $this->rewriteRefsToDefs($schema);
        }, $defs);

        return array_merge(
            [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                '$defs'   => $defs,
            ],
            $schemaNode
        );
    }

    private function rewriteRefsToDefs($node)
    {
        if (is_array($node)) {
            if (isset($node['$ref']) && is_string($node['$ref']) &&
                strpos($node['$ref'], '#/components/schemas/') === 0) {
                $name = substr($node['$ref'], strlen('#/components/schemas/'));
                $node['$ref'] = '#/$defs/' . $name;
            }
            foreach ($node as $k => $v) {
                $node[$k] = $this->rewriteRefsToDefs($v);
            }
        }
        return $node;
    }
}
