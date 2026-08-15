<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Compiler;

use Google\Protobuf\Internal\FileDescriptorProto;
use Google\Protobuf\Internal\FileDescriptorSet;

final class DescriptorSetParser
{
    /** @return ProtoFile[] */
    public function parse(string $descriptorSetBytes): array
    {
        $set = new FileDescriptorSet();
        $set->mergeFromString($descriptorSetBytes);

        $files = [];
        $typeMap = [];

        foreach ($set->getFile() as $file) {
            $fileDto = $this->parseFile($file, $typeMap);
            $files[] = $fileDto;
        }

        // Resolve type names after all files have been parsed so imports are available.
        foreach ($files as $file) {
            foreach ($file->services as $service) {
                foreach ($service->methods as $method) {
                    $typeMap[$method->inputType] ??= $this->guessClass($method->inputType, $file->phpNamespace);
                    $typeMap[$method->outputType] ??= $this->guessClass($method->outputType, $file->phpNamespace);
                }
            }
        }

        return $files;
    }

    private function parseFile(FileDescriptorProto $file, array &$typeMap): ProtoFile
    {
        $name = $file->getName();
        $package = $file->getPackage() ?: null;
        $options = $file->getOptions();
        $phpNamespace = $options->getPhpNamespace() ?: $this->packageToNamespace($package);
        $metadataNamespace = $options->getPhpMetadataNamespace() ?: 'GPBMetadata';

        $messages = [];
        foreach ($file->getMessageType() as $message) {
            $messages[] = new ProtoMessage($message->getName(), $phpNamespace);
            $typeName = $this->fullyQualifiedName($package, $message->getName());
            $typeMap[$typeName] = $phpNamespace . '\\' . $message->getName();
            $this->collectNestedTypes($message, $package, $phpNamespace, $typeMap);
        }

        $services = [];
        foreach ($file->getService() as $service) {
            $serviceName = $service->getName();
            $name = ltrim($this->fullyQualifiedName($package, $serviceName), '.');

            $methods = [];
            foreach ($service->getMethod() as $method) {
                $inputType = $method->getInputType();
                $outputType = $method->getOutputType();

                $inputPhpClass = $typeMap[$inputType] ?? $this->guessClass($inputType, $phpNamespace);
                $outputPhpClass = $typeMap[$outputType] ?? $this->guessClass($outputType, $phpNamespace);

                $methods[] = new ProtoMethod(
                    $method->getName(),
                    $inputType,
                    $outputType,
                    $inputPhpClass,
                    $outputPhpClass,
                    $method->getClientStreaming(),
                    $method->getServerStreaming(),
                );
            }

            $services[] = new ProtoService(
                $serviceName,
                $name,
                $package ?? '',
                $phpNamespace,
                $methods,
            );
        }

        return new ProtoFile($name, $package, $phpNamespace, $metadataNamespace, $messages, $services);
    }

    private function collectNestedTypes(\Google\Protobuf\Internal\DescriptorProto $message, ?string $package, string $phpNamespace, array &$typeMap, string $parent = ''): void
    {
        $prefix = $parent === '' ? $message->getName() : $parent . '\\' . $message->getName();

        foreach ($message->getNestedType() as $nested) {
            $typeName = $this->fullyQualifiedName($package, $prefix . '.' . $nested->getName());
            $typeMap[$typeName] = $phpNamespace . '\\' . $prefix . '\\' . $nested->getName();
            $this->collectNestedTypes($nested, $package, $phpNamespace, $typeMap, $prefix);
        }
    }

    private function fullyQualifiedName(?string $package, string $name): string
    {
        return $package ? '.' . $package . '.' . $name : '.' . $name;
    }

    private function packageToNamespace(?string $package): string
    {
        if ($package === null || $package === '') {
            return '';
        }

        return implode('\\', array_map('ucfirst', explode('.', $package)));
    }

    private function guessClass(string $type, string $fallbackNamespace): string
    {
        $type = ltrim($type, '.');

        return $fallbackNamespace === '' ? $type : $fallbackNamespace . '\\' . str_replace('.', '\\', $type);
    }
}
