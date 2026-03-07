<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;

class OpenApiSpecInspector
{
    public function inspect(string $specPath): OpenApiDocumentData
    {
        $content = File::get($specPath);
        $lines = preg_split('/\R/', $content) ?: [];

        $openapiVersion = '';
        $infoTitle = '';
        $infoVersion = '';
        $serverUrls = [];

        $insideInfo = false;
        $insideServers = false;
        $insidePaths = false;

        $currentPath = null;
        $currentMethod = null;

        /** @var array<string, OpenApiOperationData> $operations */
        $operations = [];

        foreach ($lines as $line) {
            if (preg_match('/^openapi:\s*(.+)\s*$/', $line, $matches) === 1) {
                $openapiVersion = $this->normalizeScalar($matches[1]);
                $insideInfo = false;
                $insideServers = false;
                $insidePaths = false;

                continue;
            }

            if (preg_match('/^info:\s*$/', $line) === 1) {
                $insideInfo = true;
                $insideServers = false;
                $insidePaths = false;

                continue;
            }

            if (preg_match('/^servers:\s*$/', $line) === 1) {
                $insideInfo = false;
                $insideServers = true;
                $insidePaths = false;

                continue;
            }

            if (preg_match('/^paths:\s*$/', $line) === 1) {
                $insideInfo = false;
                $insideServers = false;
                $insidePaths = true;
                $currentPath = null;
                $currentMethod = null;

                continue;
            }

            if (preg_match('/^\S/', $line) === 1) {
                $insideInfo = false;
                $insideServers = false;
                $insidePaths = false;
                $currentPath = null;
                $currentMethod = null;
            }

            if ($insideInfo) {
                if (preg_match('/^\s{2}title:\s*(.+)\s*$/', $line, $matches) === 1) {
                    $infoTitle = $this->normalizeScalar($matches[1]);
                }

                if (preg_match('/^\s{2}version:\s*(.+)\s*$/', $line, $matches) === 1) {
                    $infoVersion = $this->normalizeScalar($matches[1]);
                }

                continue;
            }

            if ($insideServers) {
                if (preg_match('/^\s*-\s*url:\s*(.+)\s*$/', $line, $matches) === 1) {
                    $serverUrl = $this->normalizeScalar($matches[1]);
                    if ($serverUrl !== '') {
                        $serverUrls[] = $serverUrl;
                    }
                }

                continue;
            }

            if (! $insidePaths) {
                continue;
            }

            if (preg_match('/^\s{2}(\/[^\s:]*):\s*$/', $line, $matches) === 1) {
                $currentPath = $matches[1];
                $currentMethod = null;

                continue;
            }

            if ($currentPath === null) {
                continue;
            }

            if (preg_match('/^\s{4}(get|post|put|patch|delete|head|options|trace):\s*$/i', $line, $matches) === 1) {
                $currentMethod = strtoupper($matches[1]);
                $operationKey = $this->operationKey($currentPath, $currentMethod);

                $operations[$operationKey] = new OpenApiOperationData(
                    path: $currentPath,
                    method: $currentMethod,
                    summary: '',
                    has2xxResponse: false,
                );

                continue;
            }

            if ($currentMethod === null) {
                continue;
            }

            $operationKey = $this->operationKey($currentPath, $currentMethod);
            if (! isset($operations[$operationKey])) {
                continue;
            }

            if (preg_match('/^\s{6}summary:\s*(.*)\s*$/', $line, $matches) === 1) {
                $existing = $operations[$operationKey];
                $operations[$operationKey] = new OpenApiOperationData(
                    path: $existing->path,
                    method: $existing->method,
                    summary: $this->normalizeScalar($matches[1]),
                    has2xxResponse: $existing->has2xxResponse,
                );

                continue;
            }

            if (preg_match('/^\s{6}responses:\s*(.*)\s*$/', $line, $matches) === 1) {
                $inlineValue = trim((string) $matches[1]);
                if ($inlineValue !== '' && preg_match('/["\']?2\d\d["\']?\s*:/', $inlineValue) === 1) {
                    $existing = $operations[$operationKey];
                    $operations[$operationKey] = new OpenApiOperationData(
                        path: $existing->path,
                        method: $existing->method,
                        summary: $existing->summary,
                        has2xxResponse: true,
                    );
                }

                continue;
            }

            if (preg_match('/^\s{8}["\']?(2\d\d)["\']?\s*:/', $line) === 1) {
                $existing = $operations[$operationKey];
                $operations[$operationKey] = new OpenApiOperationData(
                    path: $existing->path,
                    method: $existing->method,
                    summary: $existing->summary,
                    has2xxResponse: true,
                );

                continue;
            }
        }

        return new OpenApiDocumentData(
            openapiVersion: $openapiVersion,
            infoTitle: $infoTitle,
            infoVersion: $infoVersion,
            serverUrls: array_values(array_unique($serverUrls)),
            operations: array_values($operations),
        );
    }

    private function operationKey(string $path, string $method): string
    {
        return sprintf('%s:%s', $method, $path);
    }

    private function normalizeScalar(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (
            (str_starts_with($trimmed, '\'') && str_ends_with($trimmed, '\''))
            || (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'))
        ) {
            $trimmed = substr($trimmed, 1, -1);
        }

        return trim($trimmed);
    }
}
