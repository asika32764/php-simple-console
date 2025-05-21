<?php

declare(strict_types=1);

namespace Asika\SimpleConsole {
    class SimpleConsole
    {
    }

    class ArgvParser
    {
        protected array $values = [];

        protected array $tokens = [];

        protected array $existsNames = [];

        protected bool $parseOptions = false;

        public private(set) int $currentArgument = 0;

        /**
         * @var array<Parameter>
         */
        public private(set) array $parameters = [];

        /**
         * @var iterable<Parameter>
         */
        public iterable $arguments {
            get {
                foreach ($this->parameters as $parameter) {
                    if ($parameter->isArg) {
                        yield $parameter->primaryName => $parameter;
                    }
                }
            }
        }

        /**
         * @var iterable<Parameter>
         */
        public iterable $options {
            get {
                foreach ($this->parameters as $parameter) {
                    if (!$parameter->isArg) {
                        yield $parameter->primaryName => $parameter;
                    }
                }
            }
        }

        public function addParameter(
            string|array $name,
            ArgumentType $type,
            string $description = '',
            bool $required = false,
            mixed $default = null,
            bool $negatable = false,
        ): Parameter {
            if (is_string($name) && str_contains($name, '|')) {
                $name = explode('|', $name);

                foreach ($name as $n) {
                    if (!str_starts_with($n, '-')) {
                        throw new \RuntimeException('Argument name cannot contains "|" sign.');
                    }
                }
            }

            $parameter = new Parameter($name, $type, $description, $required, $default, $negatable);

            foreach ($parameter->nameArray as $n) {
                if (in_array($n, $this->existsNames, true)) {
                    throw new \RuntimeException('Duplicate parameter name "' . $n . '"');
                }
            }

            array_push($this->existsNames, ...$parameter->nameArray);

            $this->parameters[$parameter->primaryName] = $parameter;

            return $parameter;
        }

        public function getArgument(string $name): ?Parameter
        {
            return array_find(iterator_to_array($this->arguments), static fn($n) => $n === $name);
        }

        public function getArgumentByIndex(int $index): ?Parameter
        {
            return array_values(iterator_to_array($this->arguments))[$index] ?? null;
        }

        public function getLastArgument(): ?Parameter
        {
            $args = iterator_to_array($this->arguments);

            return $args[array_key_last($args)] ?? null;
        }

        public function getOption(string $name): ?Parameter
        {
            return array_find(iterator_to_array($this->options), static fn(Parameter $option) => $option->hasName($name));
        }

        public function mustGetOption(string $name): Parameter
        {
            $option = $this->getOption($name);

            if (!$option) {
                throw new InvalidParameterException(\sprintf('The "-%s" option does not exist.', $name));
            }

            return $option;
        }

        public function parse(array $argv): array
        {
            $this->currentArgument = 0;
            $this->parseOptions = true;
            $this->values = [];

            array_shift($argv);

            $this->tokens = $argv;

            while (null !== $token = array_shift($this->tokens)) {
                $this->parseToken((string) $token);
            }

            foreach ($this->parameters as $parameter) {
                if (!array_key_exists($parameter->primaryName, $this->values)) {
                    if ($parameter->required) {
                        throw new InvalidParameterException("Required parameter \"{$parameter->primaryName}\" is missing.");
                    }

                    $this->values[$parameter->primaryName] = $parameter->defaultValue ?? false;
                } else {
                    $parameter->validate($this->values[$parameter->primaryName]);

                    $this->values[$parameter->primaryName] = $parameter->castValue($this->values[$parameter->primaryName]);
                }
            }

            return $this->values;
        }

        protected function parseToken(string $token): void
        {
            if ($this->parseOptions && '' === $token) {
                $this->parseArgument($token);
            } elseif ($this->parseOptions && '--' === $token) {
                $this->parseOptions = false;
            } elseif ($this->parseOptions && str_starts_with($token, '--')) {
                $this->parseLongOption($token);
            } elseif ($this->parseOptions && '-' === $token[0] && '-' !== $token) {
                $this->parseShortOption($token);
            } else {
                $this->parseArgument($token);
            }
        }

        private function parseShortOption(string $token): void
        {
            $name = substr($token, 1);

            if (\strlen($name) > 1) {
                $option = $this->getOption($token);

                if ($option && $option->acceptValue) {
                    // -n[value]
                    $this->setOptionValue($name[0], substr($name, 1));
                } else {
                    $this->parseShortOptionSet($name);
                }
            } else {
                $this->setOptionValue($name, null);
            }
        }

        private function parseShortOptionSet(string $name): void
        {
            $len = \strlen($name);

            for ($i = 0; $i < $len; ++$i) {
                $option = $this->mustGetOption($name[$i]);

                if ($option->acceptValue) {
                    $this->setOptionValue($option->primaryName, $i === $len - 1 ? null : substr($name, $i + 1));
                    break;
                }

                $this->setOptionValue($option->primaryName, null);
            }
        }

        private function parseLongOption(string $token): void
        {
            $name = substr($token, 2);
            $pos = strpos($name, '=');

            if ($pos !== false) {
                $value = substr($name, $pos + 1);

                if ($value === '') {
                    array_unshift($this->values, $value);
                }

                $this->setOptionValue(substr($name, 0, $pos), $value);
            } else {
                $this->setOptionValue($name, null);
            }
        }

        private function parseArgument(string $token): void
        {
            $c = $this->currentArgument;

            $arg = $this->getArgumentByIndex($c);

            if ($arg) {
                $this->values[$arg->primaryName] = $arg->isArray ? [$token] : $token;
            } elseif (($last = $this->getLastArgument()) && $last->isArray) {
                $this->values[$last->primaryName][] = $token;
            } else {
                throw new InvalidParameterException("Unknown argument \"$token\".");
            }

            $this->currentArgument++;
        }

        public function setOptionValue(string $name, mixed $value = null): void
        {
            $option = $this->getOption($name);

            // If option not exists, make sure it is negatable
            if (!$option) {
                if (str_starts_with($name, 'no-')) {
                    $option = $this->getOption(substr($name, 3));

                    if ($option->isBoolean && $option->negatable) {
                        $this->values[$option->primaryName] = false;
                    }
                    return;
                }

                throw new InvalidParameterException(\sprintf('The "-%s" option does not exist.', $name));
            }

            if ($value !== null && !$option->acceptValue) {
                throw new InvalidParameterException('Option "' . $option->primaryName . '" does not accept value.');
            }

            if (\in_array($value, ['', null], true) && $option->acceptValue && \count($this->tokens)) {
                // if option accepts an optional or mandatory argument
                // let's see if there is one provided
                $next = array_shift($this->tokens);

                if ((isset($next[0]) && '-' !== $next[0]) || \in_array($next, ['', null], true)) {
                    $value = $next;
                } else {
                    array_unshift($this->tokens, $next);
                }
            }

            if ($value === null && $option->isBoolean) {
                $value = true;
            }

            if ($option->isBoolean) {
                $value = (bool) $value;
            }

            if ($option->isArray) {
                $this->values[$option->primaryName][] = $value;
            } else {
                $this->values[$option->primaryName] = $value;
            }
        }
    }

    class Parameter
    {
        public bool $isArg {
            get {
                return is_string($this->name);
            }
        }

        public string $primaryName {
            get {
                if (is_string($this->name)) {
                    return $this->name;
                }

                return $this->name[0];
            }
        }

        public array $nameArray {
            get {
                return (array) $this->name;
            }
        }

        public bool $acceptValue {
            get {
                return !$this->type->isBoolean();
            }
        }

        public bool $isArray {
            get {
                return $this->type === ArgumentType::ARRAY;
            }
        }

        public bool $isBoolean {
            get {
                return $this->type->isBoolean();
            }
        }

        public mixed $defaultValue {
            get {
                if ($this->isArray) {
                    return $this->default ?? [];
                }

                return $this->default;
            }
        }

        public function __construct(
            public string|array $name,
            public ArgumentType $type,
            public string $description = '',
            public bool $required = false,
            public mixed $default = null,
            public bool $negatable = false,
        ) {
            if (is_string($this->name) && str_starts_with($this->name, '-')) {
                $this->name = [$this->name];
            }

            if (is_array($this->name)) {
                foreach ($this->name as $i => $n) {
                    if (!str_starts_with($n, '--') && strlen($n) > 2) {
                        throw new InvalidParameterException('Invalid option name "' . $n . '"');
                    }

                    $this->name[$i] = ltrim($n, '-');
                }
            }

            if ($this->isArray && !is_array($this->defaultValue)) {
                throw new InvalidParameterException("Default value of \"{$this->primaryName}\" must be an array.");
            }

            if ($this->required && $this->default !== null) {
                throw new InvalidParameterException("Default value of \"{$this->primaryName}\" cannot be set when required is true.");
            }
        }

        public function hasName(string $name): bool
        {
            $name = ltrim($name, '-');

            if (is_string($this->name)) {
                return $this->name === $name;
            }

            return array_any($this->name, static fn($n) => $n === $name);
        }

        public function castValue(mixed $value): mixed
        {
            return match ($this->type) {
                ArgumentType::INT => (int) $value,
                ArgumentType::NUMERIC, ArgumentType::FLOAT => (float) $value,
                ArgumentType::BOOLEAN => (bool) $value,
                ArgumentType::ARRAY => (array) $value,
                default => $value,
            };
        }

        public function validate(mixed $value): void
        {
            if ($value === null) {
                if ($this->required) {
                    throw new InvalidParameterException("Required value \"{$this->primaryName}\" is missing.");
                }

                return;
            }

            switch ($this->type) {
                case ArgumentType::INT:
                    if (!is_numeric($value) || ((string) (int) $value) !== $value) {
                        throw new InvalidParameterException("Invalid value type for \"{$this->primaryName}\". Expected int.");
                    }
                case ArgumentType::FLOAT:
                    if (!is_numeric($value) || ((string) (float) $value) !== $value) {
                        throw new InvalidParameterException("Invalid value type for \"{$this->primaryName}\". Expected float.");
                    }
                    break;
                case ArgumentType::NUMERIC:
                    if (!is_numeric($value)) {
                        throw new InvalidParameterException("Invalid value type for \"{$this->primaryName}\". Expected numeric.");
                    }
                    break;

                case ArgumentType::BOOLEAN:
                    if (!is_bool($value) && $value !== '1' && $value !== '0') {
                        throw new InvalidParameterException("Invalid value type for \"{$this->primaryName}\". Expected boolean or 1/0.");
                    }
                    break;

                case ArgumentType::ARRAY:
                    if (!is_array($value)) {
                        throw new InvalidParameterException("Invalid value type for \"{$this->primaryName}\". Expected array.");
                    }
                    break;
            }
        }
    }

    enum ArgumentType
    {
        case STRING;
        case INT;
        case NUMERIC;
        case FLOAT;
        case BOOLEAN;
        case ARRAY;

        public function acceptValue(): bool
        {
            return in_array(
                $this,
                [
                    self::STRING,
                    self::INT,
                    self::FLOAT,
                    self::NUMERIC,
                    self::BOOLEAN,
                    self::ARRAY,
                ],
                true,
            );
        }

        public function valueRequired(): bool
        {
            return in_array(
                $this,
                [
                    self::STRING,
                    self::NUMERIC,
                    self::ARRAY,
                ],
                true,
            );
        }

        public function isBoolean(): bool
        {
            return $this === self::BOOLEAN;
        }
    }

    class InvalidParameterException extends \RuntimeException
    {
        //
    }
}
