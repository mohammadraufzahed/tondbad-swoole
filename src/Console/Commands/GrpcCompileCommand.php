<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Grpc\Compiler\DescriptorSetParser;
use TondbadSwoole\Grpc\Compiler\StubGenerator;

class GrpcCompileCommand extends Command
{
    public function getName(): string
    {
        return 'grpc:compile';
    }

    public function getDescription(): string
    {
        return 'Compile .proto files into PHP gRPC message classes and service stubs.';
    }

    public function run(array $args): int
    {
        $options = $this->parseArgs($args);

        $protoPath = rtrim($options['proto_path'] ?? $this->basePath . '/protos', '/');
        $out = rtrim($options['out'] ?? $this->basePath . '/generated', '/');
        $stubOut = rtrim($options['stub_out'] ?? $out . '/Grpc', '/');
        $namespacePrefix = $options['namespace_prefix'] ?? 'App\\Grpc\\Generated';
        $implNamespace = $options['impl_namespace'] ?? 'App\\Grpc\\Services';
        $implSuffix = $options['impl_suffix'] ?? 'Impl';
        $protoc = $options['protoc'] ?? $this->findProtoc();
        $descriptorSet = $options['descriptor_set'] ?? null;

        if ($descriptorSet === null && $protoc === null) {
            fwrite(STDERR, "protoc binary not found. Install it, pass --protoc, or use --descriptor-set.\n");

            return 1;
        }

        if ($descriptorSet === null) {
            $descriptorSet = $this->buildDescriptorSet($protoc, $protoPath, $out);

            if ($descriptorSet === null) {
                return 1;
            }
        }

        if (!is_file($descriptorSet)) {
            fwrite(STDERR, "Descriptor set not found: {$descriptorSet}\n");

            return 1;
        }

        $files = (new DescriptorSetParser())->parse(file_get_contents($descriptorSet));
        $generator = new StubGenerator($namespacePrefix, $stubOut, $implNamespace, $implSuffix);

        $written = 0;
        foreach ($files as $file) {
            foreach ($generator->generate($file) as $path => $content) {
                $this->ensureDirectory(dirname($path));
                file_put_contents($path, $content);
                fwrite(STDOUT, "Generated {$path}\n");
                ++$written;
            }
        }

        if ($written === 0) {
            fwrite(STDOUT, "No services found in descriptor set.\n");
        }

        return 0;
    }

    private function parseArgs(array $args): array
    {
        $options = [];

        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--')) {
                continue;
            }

            $arg = ltrim($arg, '-');
            [$key, $value] = array_pad(explode('=', $arg, 2), 2, '1');
            $options[str_replace('-', '_', $key)] = $value;
        }

        return $options;
    }

    private function findProtoc(): ?string
    {
        $paths = array_filter(explode(PATH_SEPARATOR, getenv('PATH') ?: ''));

        foreach ($paths as $path) {
            $candidate = $path . '/protoc';

            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildDescriptorSet(string $protoc, string $protoPath, string $out): ?string
    {
        if (!is_dir($protoPath)) {
            fwrite(STDERR, "Proto path not found: {$protoPath}\n");

            return null;
        }

        $this->ensureDirectory($out);

        $tmp = sys_get_temp_dir() . '/tondbad-grpc-' . uniqid() . '.desc';
        $files = glob($protoPath . '/*.proto') ?: [];

        if ($files === []) {
            fwrite(STDERR, "No .proto files found in {$protoPath}\n");

            return null;
        }

        $escapedFiles = implode(' ', array_map('escapeshellarg', $files));
        $cmd = sprintf(
            '%s --proto_path=%s --php_out=%s --descriptor_set_out=%s --include_imports %s 2>&1',
            escapeshellarg($protoc),
            escapeshellarg($protoPath),
            escapeshellarg($out),
            escapeshellarg($tmp),
            $escapedFiles,
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            fwrite(STDERR, "protoc failed:\n" . implode("\n", $output) . "\n");

            return null;
        }

        return $tmp;
    }

}
