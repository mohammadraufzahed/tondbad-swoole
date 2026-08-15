<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use ReflectionClass;
use ReflectionProperty;
use TondbadSwoole\Console\Attributes\Argument as ArgumentAttribute;
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Authorize;
use TondbadSwoole\Console\Attributes\Option as OptionAttribute;
use TondbadSwoole\Console\CommandInterface;
use TondbadSwoole\Console\ConsoleException;
use TondbadSwoole\Console\Input\InputArgument;
use TondbadSwoole\Console\Input\InputDefinition;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Input\InputOption;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Validation\Schema;

abstract class Command implements CommandInterface
{
    protected string $name = '';
    protected string $description = '';
    protected bool $coroutine = true;
    protected ?string $authorizeAbility = null;
    protected ?string $authorizeGuard = null;

    /** @var list<string> */
    protected array $aliases = [];

    private ?InputDefinition $definition = null;

    public function __construct(protected readonly string $basePath)
    {
    }

    protected function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }

    public function getName(): string
    {
        $this->getDefinition();

        return $this->name;
    }

    public function getDescription(): string
    {
        $this->getDefinition();

        return $this->description;
    }

    public function isCoroutine(): bool
    {
        $this->getDefinition();

        return $this->coroutine;
    }

    public function getDefinition(): InputDefinition
    {
        if ($this->definition === null) {
            $this->definition = new InputDefinition();
            $this->collectClassAttributes();
            $this->configure();
            $this->definition->addGlobalOptions();
        }

        return $this->definition;
    }

    final public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->getDefinition()->bind($input);
        $this->hydrate($input);

        return $this->execute($input, $output);
    }

    protected function configure(): void
    {
    }

    abstract protected function execute(InputInterface $input, OutputInterface $output): int;

    protected function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    protected function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    protected function setCoroutine(bool $coroutine): self
    {
        $this->coroutine = $coroutine;

        return $this;
    }

    protected function addArgument(
        string $name,
        int $mode = InputArgument::OPTIONAL,
        string $description = '',
        mixed $default = null,
        string|Schema|null $schema = null,
        array $allowed = [],
    ): self {
        $this->getDefinition()->addArgument(new InputArgument($name, $mode, $description, $default, $this->buildSchema($schema, $allowed)));

        return $this;
    }

    protected function addOption(
        string $name,
        ?string $shortcut = null,
        int $mode = InputOption::VALUE_NONE,
        string $description = '',
        mixed $default = null,
        string|Schema|null $schema = null,
        array $allowed = [],
    ): self {
        $this->getDefinition()->addOption(new InputOption($name, $shortcut, $mode, $description, $default, $this->buildSchema($schema, $allowed)));

        return $this;
    }

    public function getAuthorizeAbility(): ?string
    {
        $this->getDefinition();

        return $this->authorizeAbility;
    }

    public function getAuthorizeGuard(): ?string
    {
        $this->getDefinition();

        return $this->authorizeGuard;
    }

    /**
     * @return list<string>
     */
    public function getAliases(): array
    {
        $this->getDefinition();

        return $this->aliases;
    }

    protected function buildSchema(string|Schema|null $schema, array $allowed, ?\ReflectionProperty $property = null): ?Schema
    {
        if ($schema instanceof Schema) {
            $base = $schema;
        } elseif ($schema !== null) {
            $base = match ($schema) {
                'string' => Schema::string()->coerce(),
                'int'    => Schema::int()->coerce(),
                'float'  => Schema::float()->coerce(),
                'bool'   => Schema::bool()->coerce(),
                'email'  => Schema::string()->coerce()->email(),
                'url'    => Schema::string()->coerce()->url(),
                'uuid'   => Schema::string()->coerce()->uuid(),
                'ip'     => Schema::string()->coerce()->ip(),
                'json'   => Schema::json(),
                default  => throw new ConsoleException("Unknown schema shorthand: {$schema}"),
            };
        } elseif ($property !== null) {
            $base = $this->schemaFromPropertyType($property);
        } else {
            $base = Schema::string()->coerce();
        }

        if (!empty($allowed)) {
            $base = $base->in($allowed);
        }

        return $base->lax();
    }

    private function schemaFromPropertyType(\ReflectionProperty $property): Schema
    {
        $type = $property->getType();

        if ($type instanceof \ReflectionNamedType) {
            $schema = match ($type->getName()) {
                'bool' => Schema::bool()->coerce(),
                'int' => Schema::int()->coerce(),
                'float' => Schema::float()->coerce(),
                'array' => Schema::array(Schema::string()->coerce()),
                default => Schema::string()->coerce(),
            };

            return $type->allowsNull() ? $schema->nullable() : $schema;
        }

        return Schema::string()->coerce();
    }

    private function collectClassAttributes(): void
    {
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getAttributes(AsCommand::class) as $attribute) {
            $config = $attribute->newInstance();
            $this->name = $config->name;
            $this->description = $config->description;
            $this->coroutine = $config->coroutine;
            $this->aliases = $config->aliases;
        }

        foreach ($reflection->getAttributes(Authorize::class) as $attribute) {
            $config = $attribute->newInstance();
            $this->authorizeAbility = $config->ability;
            $this->authorizeGuard = $config->guard;
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes(ArgumentAttribute::class) as $attribute) {
                $argument = $attribute->newInstance();
                $name = $argument->name !== '' ? $argument->name : $property->getName();
                $this->getDefinition()->addArgument(new InputArgument(
                    $name,
                    $argument->mode,
                    $argument->description,
                    $argument->default,
                    $this->buildSchema($argument->schema, $argument->allowed, $property),
                ));
            }

            foreach ($property->getAttributes(OptionAttribute::class) as $attribute) {
                $option = $attribute->newInstance();
                $name = $option->name !== '' ? $option->name : $property->getName();
                $this->getDefinition()->addOption(new InputOption(
                    $name,
                    $option->shortcut,
                    $option->mode,
                    $option->description,
                    $option->default,
                    $this->buildSchema($option->schema, $option->allowed, $property),
                ));
            }
        }
    }

    private function hydrate(InputInterface $input): void
    {
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            foreach ($property->getAttributes(ArgumentAttribute::class) as $attribute) {
                $argument = $attribute->newInstance();
                $key = $argument->name !== '' ? $argument->name : $name;
                $property->setValue($this, $input->getArgument($key));
            }

            foreach ($property->getAttributes(OptionAttribute::class) as $attribute) {
                $option = $attribute->newInstance();
                $key = $option->name !== '' ? $option->name : $name;
                $property->setValue($this, $input->getOption($key));
            }
        }
    }
}
