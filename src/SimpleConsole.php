<?php

declare(strict_types=1);

namespace Asika\SimpleConsole {

    class SimpleConsole implements \ArrayAccess
    {
        public const ParameterType STRING = ParameterType::STRING;

        public const ParameterType INT = ParameterType::INT;

        public const ParameterType NUMERIC = ParameterType::NUMERIC;

        public const ParameterType FLOAT = ParameterType::FLOAT;

        public const ParameterType BOOLEAN = ParameterType::BOOLEAN;

        public const ParameterType ARRAY = ParameterType::ARRAY;

        public const ParameterType LEVEL = ParameterType::LEVEL;

        public const int SUCCESS = 0;

        public const int FAILURE = 255;

        public int $verbosity = 0;

        public array $params = [];

        public array $boolMapping = [
            ['n', 'no', 'false', 0, '0'],
            ['y', 'yes', 'true', 1, '1'],
        ];

        public bool $disableDefaultParameters = false;

        protected ArgvParser $parser;

        public static function createArgvParser(\Closure|null $configure = null): ArgvParser
        {
            $parser = new ArgvParser();

            if ($configure) {
                $configure($parser);
            }

            return $parser;
        }

        public static function parseArgv(\Closure|null $configure = null, ?array $argv = null): array
        {
            $argv ??= $_SERVER['argv'];

            return static::createArgvParser($configure)->parse($argv);
        }

        public function __construct(
            public $stdout = STDOUT,
            public $stderr = STDERR,
            public $stdin = STDIN,
        ) {
            $this->parser = static::createArgvParser();
        }

        public function addParameter(
            string|array $name,
            ParameterType $type,
            string $description = '',
            bool $required = false,
            mixed $default = null,
            bool $negatable = false,
        ): Parameter {
            return $this->parser->addParameter(
                $name,
                $type,
                $description,
                $required,
                $default,
                $negatable,
            );
        }

        public function addHelpParameter(): Parameter
        {
            return $this->addParameter(
                '--help|-h',
                static::BOOLEAN,
                'Show description of all parameters',
                default: false
            );
        }

        public function addVerbosityParameter(): Parameter
        {
            return $this->addParameter(
                '--verbosity|-v',
                static::LEVEL,
                'The verbosity level of the output',
            );
        }

        public function get(string $name, mixed $default = null): mixed
        {
            return $this->params[$name] ?? $default;
        }

        protected function configure(): void
        {
            //
        }

        protected function doExecute(): int|bool
        {
            return 0;
        }

        public function execute(?array $argv = null, ?\Closure $main = null): int
        {
            $argv = $argv ?? $_SERVER['argv'];
            $main ??= $this->doExecute(...);
            $main->bindTo($this);

            try {
                if (!$this->disableDefaultParameters) {
                    $this->addHelpParameter();
                    $this->addVerbosityParameter();
                }

                $this->configure();

                $this->params = $this->parser->parse($argv);

                if (!$this->disableDefaultParameters) {
                    $this->verbosity = (int) $this->get('verbosity');

                    if ($this->get('help')) {
                        $this->showHelp();
                        return static::SUCCESS;
                    }
                }

                $exitCode = $main($this);

                if ($exitCode === true || $exitCode === null) {
                    $exitCode = 0;
                } elseif ($exitCode === false) {
                    $exitCode = 255;
                }

                return (int) $exitCode;
            } catch (\Throwable $e) {
                return $this->handleException($e);
            }
        }

        protected function help(): string
        {
            return '';
        }

        public function showHelp(): void
        {
            $this->writeln(ParameterDescriptor::describe($this->parser, 'command', $this->help()));
        }

        public function write(string $message, bool $err = false): static
        {
            fwrite($err ? $this->stderr : $this->stdout, $message);
            return $this;
        }

        public function writeln(string $message = '', bool $err = false): static
        {
            $this->write($message . "\n", $err);
            return $this;
        }

        public function newLine(int $lines = 1, bool $err = false): static
        {
            $this->write(str_repeat("\n", $lines), $err);
            return $this;
        }

        public function in(): string
        {
            return rtrim(fread(STDIN, 8192), "\n\r");
        }

        public function ask(string $question = '', string $default = ''): string
        {
            $this->write($question);

            $in = rtrim(fread(STDIN, 8192), "\n\r");

            return $in === '' ? $default : $in;
        }

        public function askConfirm(string $question = '', string $default = ''): bool
        {
            return (bool) $this->mapBoolean($this->ask($question, $default));
        }

        public function mapBoolean($in): bool|null
        {
            $in = strtolower((string) $in);

            [$falsy, $truly] = $this->boolMapping;

            if (in_array($in, $falsy, true)) {
                return false;
            }

            if (in_array($in, $truly, true)) {
                return true;
            }

            return null;
        }

        public function exec(string $cmd, ?\Closure $output = null): int
        {
            $this->writeln('>> ' . $cmd);

            $descriptorspec = [
                0 => ["pipe", "r"],  // stdin
                1 => ["pipe", "w"],  // stdout
                2 => ["pipe", "w"]   // stderr
            ];

            if ($process = proc_open($cmd, $descriptorspec, $pipes)) {
                while (($out = fgets($pipes[1])) || $err = fgets($pipes[2])) {
                    if (isset($out[0])) {
                        if ($output) {
                            $output($out, false);
                        } else {
                            $this->write($out, false);
                        }
                    }

                    if (isset($err[0])) {
                        if ($output) {
                            $output($err, true);
                        } else {
                            $this->write($err, true);
                        }
                    }
                }

                return proc_close($process);
            }

            return 255;
        }

        public function mustExec(string $cmd, ?\Closure $output = null): int
        {
            $result = $this->exec($cmd, $output);

            if ($result !== 0) {
                throw new \RuntimeException('Command "' . $cmd . '" failed with code ' . $result);
            }

            return $result;
        }

        protected function handleException(\Throwable $e): int
        {
            $v = $this->verbosity;

            if ($e instanceof InvalidParameterException) {
                $this->writeln('[Warning] ' . $e->getMessage(), true)
                    ->newLine(err: true)
                    ->writeln('HELP', true);
            } else {
                $this->writeln('[Error] ' . $e->getMessage(), true);
            }

            if ($v > 0) {
                $this->writeln('[Backtrace]:', true)
                    ->writeln($e->getTraceAsString(), true);
            }

            $code = $e->getCode();

            return $code === 0 ? 255 : $code;
        }

        public function offsetExists(mixed $offset): bool
        {
            return array_key_exists($offset, $this->params);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->params[$offset] ?? null;
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            throw new \BadMethodCallException('Cannot set params.');
        }

        public function offsetUnset(mixed $offset): void
        {
            throw new \BadMethodCallException('Cannot unset params.');
        }
    }

    class ArgvParser
    {
        protected array $values = [];

        protected array $tokens = [];

        protected array $existsNames = [];

        protected bool $parseOptions = false;

        public private(set) int $currentArgument = 0;

        /** @var array<Parameter> */
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
            ParameterType $type,
            string $description = '',
            bool $required = false,
            mixed $default = null,
            bool $negatable = false,
        ): Parameter {
            if (is_string($name) && str_contains($name, '|')) {
                $name = explode('|', $name);

                foreach ($name as $n) {
                    if (!str_starts_with($n, '-')) {
                        throw new \InvalidArgumentException('Argument name cannot contains "|" sign.');
                    }
                }
            }

            $parameter = new Parameter($name, $type, $description, $required, $default, $negatable);

            foreach ((array) $parameter->name as $n) {
                if (in_array($n, $this->existsNames, true)) {
                    throw new \InvalidArgumentException('Duplicate parameter name "' . $n . '"');
                }
            }

            array_push($this->existsNames, ...((array) $parameter->name));
            $this->parameters[$parameter->primaryName] = $parameter;

            return $parameter;
        }

        public function removeParameter(string $name): void
        {
            unset($this->parameters[$name]);
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
            return array_find(
                iterator_to_array($this->options),
                static fn(Parameter $option) => $option->hasName($name)
            );
        }

        public function mustGetOption(string $name): Parameter
        {
            if (!$option = $this->getOption($name)) {
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
                    if ($parameter->isArg && $parameter->required) {
                        throw new InvalidParameterException(
                            "Required argument \"{$parameter->primaryName}\" is missing."
                        );
                    }

                    $this->values[$parameter->primaryName] = $parameter->defaultValue ?? false;
                } else {
                    $parameter->validate($this->values[$parameter->primaryName]);

                    $this->values[$parameter->primaryName] = $parameter->castValue(
                        $this->values[$parameter->primaryName]
                    );
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
            if ($arg = $this->getArgumentByIndex($this->currentArgument)) {
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

            // Try get option value from next token
            if (\in_array($value, ['', null], true) && $option->acceptValue && \count($this->tokens)) {
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
            } elseif ($option->isLevel) {
                $this->values[$option->primaryName] ??= 0;
                $this->values[$option->primaryName]++;
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

        public string $synopsis {
            get {
                if (is_string($this->name)) {
                    return $this->name;
                }

                $shorts = [];
                $fulls = [];

                foreach ($this->name as $n) {
                    if (strlen($n) === 1) {
                        $shorts[] = '-' . $n;
                    } else {
                        $fulls[] = '--' . $n;
                    }
                }

                if ($this->negatable) {
                    $fulls[] = '--no-' . $this->primaryName;
                }

                $ns = array_filter(
                    [
                        implode('|', $shorts),
                        implode('|', $fulls),
                    ]
                );

                return implode(', ', $ns);
            }
        }

        public bool $acceptValue {
            get {
                return !$this->isBoolean && !$this->isLevel && !$this->negatable;
            }
        }

        public bool $isArray {
            get {
                return $this->type === ParameterType::ARRAY;
            }
        }

        public bool $isLevel {
            get {
                return $this->type === ParameterType::LEVEL;
            }
        }

        public bool $isBoolean {
            get {
                return $this->type === ParameterType::BOOLEAN;
            }
        }

        public mixed $defaultValue {
            get {
                if ($this->isArray) {
                    return $this->default ?? [];
                }

                if ($this->isLevel) {
                    return $this->default ?? 0;
                }

                return $this->default;
            }
        }

        public function __construct(
            public string|array $name,
            public ParameterType $type,
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
                        throw new \InvalidArgumentException('Invalid option name "' . $n . '"');
                    }

                    $this->name[$i] = ltrim($n, '-');
                }
            }

            if ($this->isArray && !is_array($this->defaultValue)) {
                throw new \InvalidArgumentException("Default value of \"{$this->primaryName}\" must be an array.");
            }

            if ($this->isArg) {
                if ($this->negatable) {
                    throw new \InvalidArgumentException(
                        "Argument \"{$this->primaryName}\" cannot be negatable."
                    );
                }
            } else {
                if ($this->negatable && $this->required) {
                    throw new \InvalidArgumentException(
                        "Negatable option \"{$this->primaryName}\" cannot be required."
                    );
                }
            }

            if ($this->required && $this->default !== null) {
                throw new \InvalidArgumentException(
                    "Default value of \"{$this->primaryName}\" cannot be set when required is true."
                );
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
                ParameterType::INT, ParameterType::LEVEL => (int) $value,
                ParameterType::NUMERIC, ParameterType::FLOAT => (float) $value,
                ParameterType::BOOLEAN => (bool) $value,
                ParameterType::ARRAY => (array) $value,
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
                case ParameterType::INT:
                    if (!is_numeric($value) || ((string) (int) $value) !== $value) {
                        throw new InvalidParameterException(
                            "Invalid value type for \"{$this->primaryName}\". Expected int."
                        );
                    }
                    break;
                case ParameterType::FLOAT:
                    if (!is_numeric($value) || ((string) (float) $value) !== $value) {
                        throw new InvalidParameterException(
                            "Invalid value type for \"{$this->primaryName}\". Expected float."
                        );
                    }
                    break;
                case ParameterType::NUMERIC:
                    if (!is_numeric($value)) {
                        throw new InvalidParameterException(
                            "Invalid value type for \"{$this->primaryName}\". Expected numeric."
                        );
                    }
                    break;

                case ParameterType::BOOLEAN:
                    if (!is_bool($value) && $value !== '1' && $value !== '0') {
                        throw new InvalidParameterException(
                            "Invalid value type for \"{$this->primaryName}\". Expected boolean or 1/0."
                        );
                    }
                    break;

                case ParameterType::ARRAY:
                    if (!is_array($value)) {
                        throw new InvalidParameterException(
                            "Invalid value type for \"{$this->primaryName}\". Expected array."
                        );
                    }
                    break;
            }
        }
    }

    class ParameterDescriptor
    {
        public static function describe(ArgvParser $parser, string $commandName, string $help = ''): string
        {
            $lines = ['Usage:'];

            $lines[] = '  ' . $commandName . ' ' . static::synopsis($parser, true);

            $arguments = iterator_to_array($parser->arguments);
            $options = iterator_to_array($parser->options);

            if (count($arguments)) {
                $lines[] = '';
                $lines[] = 'Arguments:';
                $maxColWidth = 0;
                $argumentLines = [];

                foreach ($arguments as $argument) {
                    $argumentLines[] = static::describeArgument($argument, $maxColWidth);
                }

                foreach ($argumentLines as [$start, $end]) {
                    $spacing = $maxColWidth - strlen($start) + 4;
                    $lines[] = '  ' . $start . str_repeat(' ', $spacing) . $end;
                }
            }

            if (count($options)) {
                $lines[] = '';
                $lines[] = 'Options:';
                $maxColWidth = 0;
                $optionLines = [];

                foreach ($options as $option) {
                    $optionLines[] = static::describeOption($option, $maxColWidth);
                }

                foreach ($optionLines as [$start, $end]) {
                    $spacing = $maxColWidth - strlen($start) + 4;
                    $lines[] = '  ' . $start . str_repeat(' ', $spacing) . $end;
                }
            }

            if ($help) {
                $lines[] = '';
                $lines[] = 'Help:';
            }

            return implode("\n", $lines);
        }

        public static function describeArgument(Parameter $parameter, int &$maxWidth = 0): array
        {
            if (!static::defaultIsEmpty($parameter)) {
                $default = ' [default: ' . static::formatValue($parameter->default) . ']';
            } else {
                $default = '';
            }

            $maxWidth = max($maxWidth, strlen($parameter->synopsis));

            return [$parameter->synopsis, $parameter->description . $default];
        }

        public static function describeOption(Parameter $parameter, int &$maxWidth = 0): array
        {
            if (($parameter->acceptValue || $parameter->negatable) && !static::defaultIsEmpty($parameter)) {
                $default = ' [default: ' . static::formatValue($parameter->default) . ']';
            } else {
                $default = '';
            }

            $value = '';
            if ($parameter->acceptValue) {
                $value = '=' . strtoupper($parameter->primaryName);

                if (!$parameter->required) {
                    $value = '[' . $value . ']';
                }
            }

            $synopsis = $parameter->synopsis . ($parameter->acceptValue ? $value : '');
            $maxWidth = max($maxWidth, strlen($synopsis));

            return [
                $synopsis,
                $parameter->description . $default . ($parameter->isArray ? ' (multiple values allowed)' : ''),
            ];
        }

        public static function defaultIsEmpty(Parameter $parameter): bool
        {
            if ($parameter->default === null) {
                return true;
            }

            if (is_array($parameter->default) && count($parameter->default) === 0) {
                return true;
            }

            return false;
        }

        public static function formatValue(mixed $value): string
        {
            if (\INF === $value) {
                return 'INF';
            }

            if (\is_string($value)) {
                $value = static::escape($value);
            } elseif (\is_array($value)) {
                foreach ($value as $key => $v) {
                    if (\is_string($v)) {
                        $value[$key] = static::escape($v);
                    }
                }
            }

            return str_replace('\\\\', '\\', json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
        }

        public static function escape(string $text): string
        {
            $text = preg_replace('/([^\\\\]|^)([<>])/', '$1\\\\$2', $text);

            return self::escapeTrailingBackslash($text);
        }

        public static function escapeTrailingBackslash(string $text): string
        {
            if (str_ends_with($text, '\\')) {
                $len = \strlen($text);
                $text = rtrim($text, '\\');
                $text = str_replace("\0", '', $text);
                $text .= str_repeat("\0", $len - \strlen($text));
            }

            return $text;
        }

        public static function synopsis(ArgvParser $parser, bool $simple = false): string
        {
            $elements = [];

            if ($simple) {
                $elements[] = '[options]';
            } else {
                foreach ($parser->options as $option) {
                    $value = '';

                    if ($option->acceptValue) {
                        $value = strtoupper($option->primaryName);

                        if (!$option->required) {
                            $value = '[' . $value . ']';
                        }

                        $value = ' ' . $value;
                    }

                    $synopsis = str_replace(', ', '|', $option->synopsis);

                    $element = $synopsis . $value;

                    $elements[] = '[' . $element . ']';
                }
            }

            /** @var Parameter[] $arguments */
            $arguments = iterator_to_array($parser->arguments);

            if ($elements !== [] && $arguments !== []) {
                $elements[] = '[--]';
            }

            $tail = '';
            foreach ($arguments as $argument) {
                $element = '<' . $argument->primaryName . '>';

                if ($argument->isArray) {
                    $element .= '...';
                }

                if (!$argument->required) {
                    $element = '[' . $element;
                    $tail .= ']';
                }

                $elements[] = $element;
            }

            return implode(' ', $elements) . $tail;
        }
    }

    enum ParameterType
    {
        case STRING;
        case INT;
        case NUMERIC;
        case FLOAT;
        case BOOLEAN;
        case LEVEL;
        case ARRAY;
    }

    class InvalidParameterException extends \RuntimeException
    {
    }
}
