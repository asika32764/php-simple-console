<?php

declare(strict_types=1);

namespace Asika\SimpleConsole {
    class SimpleConsole
    {
    }

    class ArgumentParser
    {
        protected array $parsed = [];

        protected int $currentArgument = 0;

        /**
         * @var array<Parameter>
         */
        public array $parameters = [];

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
        ): Parameter {
            $parameter = new Parameter($name, $type, $description, $required, $default);
            $this->parameters[] = $parameter;

            return $parameter;
        }

        public function getArgument(string $name): ?Parameter
        {
            return array_find(iterator_to_array($this->arguments), static fn($n) => $n === $name);
        }

        public function getArgumentByIndex(int $index): ?Parameter
        {
            return iterator_to_array($this->arguments)[$index] ?? null;
        }

        public function getLastArgument(): ?Parameter
        {
            $args = iterator_to_array($this->arguments);

            return $args[array_key_last($args)] ?? null;
        }

        public function getOption(string $name): ?Parameter
        {
            if (!str_starts_with($name, '-')) {
                if (strlen($name) === 1) {
                    $name = '-' . $name;
                } else {
                    $name = '--' . $name;
                }
            }

            return array_find(iterator_to_array($this->options), static fn(Parameter $option) => $option->hasName($name));
        }

        public function parse(array $argv): array
        {
            $this->parsed = [];

            array_shift($argv);

            $parseOptions = true;

            while (null !== $token = array_shift($argv)) {
                $parseOptions = $this->parseToken((string) $token, $parseOptions);
            }

            return $this->parsed;
        }

        protected function parseToken(string $token, bool $parseOptions): bool
        {
            if ('' === $token) {
                $this->parseArgument($token);
            } elseif ('--' === $token) {
                return false;
            } elseif (str_starts_with($token, '--')) {
                $this->parseLongOption($token);
            } elseif ('-' === $token[0] && '-' !== $token) {
                $this->parseShortOption($token);
            } else {
                $this->parseArgument($token);
            }

            return $parseOptions;
        }

        private function parseShortOption(string $token): void
        {
            $name = substr($token, 1);

            if (\strlen($name) > 1) {
                $option = $this->getOption($token);
                if ($option && $option->acceptValue) {
                    // -n[value]
                    $this->parsed[$name[0]] = substr($name, 1);
                } else {
                    $this->parseShortOptionSet($name);
                }
            } else {
                $this->parsed[$name] = null;
            }
        }

        private function parseShortOptionSet(string $name): void
        {
            $len = \strlen($name);
            for ($i = 0; $i < $len; ++$i) {
                $this->getOption($name[$i]);

                $option = $this->getOption('-' . $name[$i]);

                if (!$option) {
                    throw new \RuntimeException(\sprintf('The "-%s" option does not exist.', $name[$i]));
                }

                if ($option->acceptValue) {
                    $this->parsed[$option->primaryName] = $i === $len - 1 ? null : substr($name, $i + 1);
                    break;
                }

                $this->parsed[$name[$i]] = null;
            }
        }

        private function parseLongOption(string $token): void
        {
            $name = substr($token, 2);

            if (false !== $pos = strpos($name, '=')) {
                if ('' === $value = substr($name, $pos + 1)) {
                    array_unshift($this->parsed, $value);
                }

                $this->parsed[substr($name, 0, $pos)] = $value;
            } else {
                $this->parsed[$name] = null;
            }
        }

        private function parseArgument(string $token): void
        {
            $c = $this->currentArgument;

            $arg = $this->getArgumentByIndex($c);

            if ($arg) {
                $this->parsed[$arg->primaryName] = $arg->isArray ? [$token] : $token;
            } elseif (($last = $this->getLastArgument()) && $last->isArray) {
                $this->parsed[$last->primaryName][] = $token;
            } else {
                throw new \RuntimeException("Unknown argument \"$token\".");
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

                foreach ($this->name as $n) {
                    if (str_starts_with($n, '--')) {
                        return $n;
                    }
                }

                return $this->name[0];
            }
        }

        public bool $acceptValue {
            get {
                return $this->type->acceptValue();
            }
        }

        public bool $isArray {
            get {
                return $this->type === ArgumentType::ARRAY;
            }
        }

        public function __construct(
            public string|array $name,
            public ArgumentType $type,
            public string $description = '',
            public bool $required = false,
            public mixed $default = null,
        ) {
            if (is_string($this->name) && str_starts_with($this->name, '-')) {
                $this->name = [$this->name];
            }

            if (is_array($this->name)) {
                foreach ($this->name as $i => $n) {
                    if (!str_starts_with($n, '--') && strlen($n) > 2) {
                        throw new \RuntimeException('Invalid option name "' . $n . '"');
                    }
                }
            }
        }

        public function hasName(string $name): bool
        {
            if (is_string($this->name)) {
                return $this->name === $name;
            }

            return array_any($this->name, static fn($n) => $n === $name);
        }
    }

    enum ArgumentType
    {
        case STRING;
        case INT;
        case BOOLEAN;
        case ARRAY;

        public function acceptValue(): bool
        {
            return in_array(
                $this,
                [
                    self::STRING,
                    self::INT,
                    self::BOOLEAN,
                    self::ARRAY,
                ],
                true,
            );
        }
    }
}
