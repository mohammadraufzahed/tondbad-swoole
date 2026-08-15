<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Grpc\Compiler\DescriptorSetParser;
use TondbadSwoole\Grpc\Compiler\StubGenerator;

class GrpcCompileCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('grpc:compile');
        $this->setDescription('Compile .proto files into PHP gRPC message classes and service stubs.');

        $this->addOption('proto-path', null, \TondbadSwoole\Console\Input\InputOption::VALUE_REQUIRED, 'Directory containing .proto files', $this->basePath . '/protos');
        $this->addOption('out', null, \TondbadSwoole\Console\Input\InputOption::VALUE_REQUIRED, 'Output directory for PHP message classes', $this->basePath . '/generated');
        $this->addOption('stub-out', null, \TondbadSwoole\Console\Input\InputOption::VALUE_REQUIRED, 'Output directory for generated gRPC stubs');
        $this->addOption('namespace-prefix', null, \TondbadSwoole\Console\Input\InputOption::VALUE_REQUIRED, 'Namespace prefix for generated stubs', 'App\\Grpc\\Generated');
        $this->addOption('impl-namespace', null, \TondbadSwoole\Console\Input\InputOption::VALUE_REQUIRED, 'Namespace for service implementation classes', 'App\\Grpc\\Services');
        $this->addOption('impl-suffix', null, \TondbadSwoole\Console\Input\InputOption::VALUE_REQUIRED, 'Suffix for service implementation class names', 'Impl');
        $this->addOption('protoc', null, \TondbadSwoole\Console\Input\InputOption::VALUE_REQUIRED, 'Path to the protoc binary');
        $this->addOption('descriptor-set', null, \TondbadSwoole\Console\Input\InputOption::VALUE_REQUIRED, 'Path to a pre-built FileDescriptorSet');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $protoPath = rtrim((string) $input->getOption('proto-path'), '/');
        $out = rtrim((string) $input->getOption('out'), '/');
        $stubOut = $input->hasOption('stub-out') && $input->getOption('stub-out') !== null
            ? rtrim((string) $input->getOption('stub-out'), '/')
            : $out . '/Grpc';
        $namespacePrefix = (string) $input->getOption('namespace-prefix');
        $implNamespace = (string) $input->getOption('impl-namespace');
        $implSuffix = (string) $input->getOption('impl-suffix');
        $protoc = $input->hasOption('protoc') && $input->getOption('protoc') !== null
            ? (string) $input->getOption('protoc')
            : $this->findProtoc();
        $descriptorSet = $input->hasOption('descriptor-set') && $input->getOption('descriptor-set') !== null
            ? (string) $input->getOption('descriptor-set')
            : null;

        if ($descriptorSet === null && $protoc === null) {
            $output->writeln('<error>protoc binary not found. Install it, pass --protoc, or use --descriptor-set.</error>');

            return 1;
        }

        if ($descriptorSet === null) {
            $descriptorSet = $this->buildDescriptorSet($protoc, $protoPath, $out);

            if ($descriptorSet === null) {
                return 1;
            }
        }

        if (!is_file($descriptorSet)) {
            $output->writeln("<error>Descriptor set not found: {$descriptorSet}</error>");

            return 1;
        }

        $files = (new DescriptorSetParser())->parse(file_get_contents($descriptorSet));
        $generator = new StubGenerator($namespacePrefix, $stubOut, $implNamespace, $implSuffix);

        $written = 0;
        foreach ($files as $file) {
            foreach ($generator->generate($file) as $path => $content) {
                $this->ensureDirectory(dirname($path));
                file_put_contents($path, $content);
                $output->writeln("Generated {$path}");
                ++$written;
            }
        }

        if ($written === 0) {
            $output->writeln('No services found in descriptor set.');
        }

        return 0;
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
