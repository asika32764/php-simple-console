<?php

declare(strict_types=1);

namespace Asika {
    class SimpleConsole
    {
    }

    class ArgumentParser
    {
        protected array $parsed = [];

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

        public function parse(array $argv)
        {
            array_shift($argv);

            $parseOptions = true;
            $this->parsed = $argv;

            while (null !== $token = array_shift($this->parsed)) {
                $parseOptions = $this->parseToken((string) $token, $parseOptions);
            }
        }

        public function getArgument(string $name): ?Parameter
        {
            return array_find(iterator_to_array($this->arguments), static fn($n) => $n === $name);
        }

        public function getOption(string $name): ?Parameter
        {
            return array_find(iterator_to_array($this->options), static fn($option) => $option->hasName($name));
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

        /**
         * Parses a short option set.
         *
         * @throws RuntimeException When option given doesn't exist
         */
        private function parseShortOptionSet(string $name): void
        {
            $len = \strlen($name);
            for ($i = 0; $i < $len; ++$i) {
                if (!$this->definition->hasShortcut($name[$i])) {
                    $encoding = mb_detect_encoding($name, null, true);
                    throw new RuntimeException(\sprintf('The "-%s" option does not exist.', false === $encoding ? $name[$i] : mb_substr($name, $i, 1, $encoding)));
                }

                $option = $this->definition->getOptionForShortcut($name[$i]);
                if ($option->acceptValue()) {
                    $this->addLongOption($option->getName(), $i === $len - 1 ? null : substr($name, $i + 1));

                    break;
                }

                $this->addLongOption($option->getName(), null);
            }
        }

        /**
         * Parses a long option.
         */
        private function parseLongOption(string $token): void
        {
            $name = substr($token, 2);

            if (false !== $pos = strpos($name, '=')) {
                if ('' === $value = substr($name, $pos + 1)) {
                    array_unshift($this->parsed, $value);
                }
                $this->addLongOption(substr($name, 0, $pos), $value);
            } else {
                $this->addLongOption($name, null);
            }
        }

        /**
         * Parses an argument.
         *
         * @throws RuntimeException When too many arguments are given
         */
        private function parseArgument(string $token): void
        {
            $c = \count($this->parameters);

            // if input is expecting another argument, add it
            if ($this->definition->hasArgument($c)) {
                $arg = $this->definition->getArgument($c);
                $this->parameters[$arg->getName()] = $arg->isArray() ? [$token] : $token;

                // if last argument isArray(), append token to last argument
            } elseif ($this->definition->hasArgument($c - 1) && $this->definition->getArgument($c - 1)->isArray()) {
                $arg = $this->definition->getArgument($c - 1);
                $this->parameters[$arg->getName()][] = $token;

                // unexpected argument
            } else {
                $all = $this->definition->getArguments();
                $symfonyCommandName = null;
                if (($inputArgument = $all[$key = array_key_first($all)] ?? null) && 'command' === $inputArgument->getName()) {
                    $symfonyCommandName = $this->parameters['command'] ?? null;
                    unset($all[$key]);
                }

                if (\count($all)) {
                    if ($symfonyCommandName) {
                        $message = \sprintf('Too many arguments to "%s" command, expected arguments "%s".', $symfonyCommandName, implode('" "', array_keys($all)));
                    } else {
                        $message = \sprintf('Too many arguments, expected arguments "%s".', implode('" "', array_keys($all)));
                    }
                } elseif ($symfonyCommandName) {
                    $message = \sprintf('No arguments expected for "%s" command, got "%s".', $symfonyCommandName, $token);
                } else {
                    $message = \sprintf('No arguments expected, got "%s".', $token);
                }

                throw new RuntimeException($message);
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
