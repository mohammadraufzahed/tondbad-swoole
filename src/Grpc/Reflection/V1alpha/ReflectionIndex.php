<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc\Reflection\V1alpha;

use Google\Protobuf\Internal\DescriptorProto;
use Google\Protobuf\Internal\FileDescriptorProto;
use Google\Protobuf\Internal\FileDescriptorSet;

final class ReflectionIndex
{
    /** @var array<string, FileDescriptorProto> */
    private array $filesByName = [];

    /** @var array<string, FileDescriptorProto> */
    private array $symbolsToFile = [];

    /** @var array<string, list<int>> */
    private array $extensionsByType = [];

    private bool $loaded = false;

    public function __construct(private readonly string $path)
    {
    }

    public function listServices(array $registeredServices = []): array
    {
        $this->load();

        $services = [];

        foreach ($this->filesByName as $file) {
            $package = $file->getPackage();
            $prefix = $package === '' ? '' : $package . '.';

            foreach ($file->getService() as $service) {
                $services[] = $prefix . $service->getName();
            }
        }

        foreach ($registeredServices as $name) {
            $name = ltrim($name, '/');
            if (!in_array($name, $services, true)) {
                $services[] = $name;
            }
        }

        sort($services);

        return $services;
    }

    public function fileContainingSymbol(string $symbol): ?FileDescriptorProto
    {
        $this->load();
        $symbol = ltrim($symbol, '.');

        return $this->symbolsToFile[$symbol] ?? null;
    }

    public function fileByName(string $fileName): ?FileDescriptorProto
    {
        $this->load();

        return $this->filesByName[$fileName] ?? null;
    }

    public function fileContainingExtension(string $containingType, int $extensionNumber): ?FileDescriptorProto
    {
        $this->load();
        $containingType = ltrim($containingType, '.');

        foreach ($this->filesByName as $file) {
            foreach ($file->getExtension() as $extension) {
                $extendee = ltrim($extension->getExtendee(), '.');
                if ($extendee === $containingType && $extension->getNumber() === $extensionNumber) {
                    return $file;
                }
            }
        }

        return null;
    }

    /** @return list<int> */
    public function allExtensionNumbersOfType(string $containingType): array
    {
        $this->load();
        $containingType = ltrim($containingType, '.');

        return $this->extensionsByType[$containingType] ?? [];
    }

    /** @return list<FileDescriptorProto> */
    public function withDependencies(FileDescriptorProto $file): array
    {
        $this->load();

        $collected = [];
        $this->collectDependencies($file, $collected);

        return array_values($collected);
    }

    /** @param array<string, FileDescriptorProto> $collected */
    private function collectDependencies(FileDescriptorProto $file, array &$collected): void
    {
        $name = $file->getName();

        if (isset($collected[$name])) {
            return;
        }

        $collected[$name] = $file;

        foreach ($file->getDependency() as $dependency) {
            $depFile = $this->filesByName[$dependency] ?? null;

            if ($depFile !== null) {
                $this->collectDependencies($depFile, $collected);
            }
        }
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (!is_file($this->path) || !is_readable($this->path)) {
            return;
        }

        $set = new FileDescriptorSet();
        $set->mergeFromString((string) file_get_contents($this->path));

        foreach ($set->getFile() as $file) {
            $this->indexFile($file);
        }
    }

    private function indexFile(FileDescriptorProto $file): void
    {
        $this->filesByName[$file->getName()] = $file;

        $package = $file->getPackage();
        $prefix = $package === '' ? '' : $package . '.';

        foreach ($file->getService() as $service) {
            $this->symbolsToFile[$prefix . $service->getName()] = $file;
        }

        foreach ($file->getMessageType() as $message) {
            $this->indexMessage($message, $package, $message->getName());
        }

        foreach ($file->getEnumType() as $enum) {
            $this->symbolsToFile[$prefix . $enum->getName()] = $file;
        }

        foreach ($file->getExtension() as $extension) {
            $extendee = ltrim($extension->getExtendee(), '.');
            $number = $extension->getNumber();
            $this->extensionsByType[$extendee][] = $number;
        }
    }

    private function indexMessage(DescriptorProto $message, string $package, string $prefix): void
    {
        $full = $package === '' ? $prefix : $package . '.' . $prefix;

        $this->symbolsToFile[$full] = $this->filesByName[array_key_last($this->filesByName)];

        foreach ($message->getNestedType() as $nested) {
            $this->indexMessage($nested, $package, $prefix . '.' . $nested->getName());
        }

        foreach ($message->getEnumType() as $enum) {
            $this->symbolsToFile[$full . '.' . $enum->getName()] = $this->filesByName[array_key_last($this->filesByName)];
        }
    }
}
