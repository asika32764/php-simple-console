# PHP Simple Console V2.0

![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/asika32764/php-simple-console/ci.yml?style=for-the-badge)
[![Packagist Version](https://img.shields.io/packagist/v/asika/simple-console?style=for-the-badge)
](https://packagist.org/packages/asika/simple-console)
[![Packagist Downloads](https://img.shields.io/packagist/dt/asika/simple-console?style=for-the-badge)](https://packagist.org/packages/asika/simple-console)

Single file console framework to help you write scripts quickly, **v2.0 requires PHP 8.4 or later**.

> This package is highly inspired by [Symfony Console](https://symfony.com/doc/current/components/console.html) and Python [argparse](https://docs.python.org/3/library/argparse.html).

<!-- TOC -->
* [PHP Simple Console V2.0](#php-simple-console-v20)
  * [Installation](#installation)
  * [Getting Started](#getting-started)
    * [Run Console by Closure](#run-console-by-closure)
    * [Run Console by Custom Class](#run-console-by-custom-class)
    * [The Return Value](#the-return-value)
  * [Parameter Parser](#parameter-parser)
  * [Parameter Definitions](#parameter-definitions)
    * [Show Help](#show-help)
    * [Override Help Information](#override-help-information)
  * [Parameter Configurations](#parameter-configurations)
    * [Get Parameters Value](#get-parameters-value)
    * [Parameters Type](#parameters-type)
      * [`ARRAY` type](#array-type)
      * [`LEVEL` type](#level-type)
    * [Parameters Options](#parameters-options)
      * [`description`](#description)
      * [`required`](#required)
      * [`default`](#default)
      * [`negatable`](#negatable)
    * [Parameters Parsing](#parameters-parsing)
  * [Error Handling](#error-handling)
    * [Wrong Parameters](#wrong-parameters)
    * [Verbosity](#verbosity)
  * [The Built-In Options](#the-built-in-options)
    * [Disable Built-In Options for Console App](#disable-built-in-options-for-console-app)
  * [Input/Output](#inputoutput)
    * [STDIN/STDOUT/STDERR](#stdinstdoutstderr)
    * [Output Methods](#output-methods)
    * [Input and Asking Questions](#input-and-asking-questions)
  * [Run Sub-Process](#run-sub-process)
    * [Hide Command Name](#hide-command-name)
    * [Custom Output](#custom-output)
    * [Disable the Output](#disable-the-output)
    * [Override `exec()`](#override-exec)
  * [Delegating Multiple Tasks](#delegating-multiple-tasks)
  * [Contributing and PR is Welcome](#contributing-and-pr-is-welcome)
<!-- TOC -->

## Installation

Use composer:

``` bash
composer require asika/simple-console
```

Download single
file: [Download Here](https://raw.githubusercontent.com/asika32764/php-simple-console/master/src/Console.php)

CLI quick download:

```shell
# WGET
wget https://raw.githubusercontent.com/asika32764/php-simple-console/master/src/Console.php
chmod +x Console.php

# CURL
curl https://raw.githubusercontent.com/asika32764/php-simple-console/master/src/Console.php -o Console.php
chmod +x Console.php
```

## Getting Started

### Run Console by Closure

Use closure to create a simple console script.

```php
#!/bin/sh php
<?php
// Include single file
include_once __DIR__ . '/Console.php';

// Or use composer
include_once __DIR__ . '/vendor/autolod.php';

$app = new \Asika\SimpleConsole\Console();

$app->execute(
    $argv,
    function () use ($app) {
        $this->writeln('Hello');
        
        // OR
        
        $app->writeln('Hello');
    
        return $app::SUCCESS; // OR `0` as success
    }
);
```

The closure can receive a `Console` object as parameter, so you can use `$this` or `$app` to access the console object.

```php
$app->execute(
    $argv,
    function (Console $app) {
        $app->writeln('Hello');
    
        // ...
    }
);
```

The `$argv` can be omit, Console will get it from `$_SERVER['argv']`

```php
$app->execute(
    main: function (Console $app) {
        $app->writeln('Hello');
    
        // ...
    }
);
```

### Run Console by Custom Class

You can also use class mode, extends `Asika\SimpleConsole\Console` class to create a console application.

```php
$app = new class () extends \Asika\SimpleConsole\Console
{
    protected function doExecute(): int|bool
    {
        $this->writeln('Hello');

        return static::SUCCESS;
    }
}

$app->execute();

// OR

$app->execute($argv);
```

### The Return Value

You can return `true` or `0` as success, `false` or any int larger than `0` as failure. Please refer to
[GNU/Linux Exit Codes](https://slg.ddnss.de/list-of-common-exit-codes-for-gnu-linux/).

Simple Console provides constants for success and failure:

```php
retrun $app::SUCCESS; // 0
retrun $app::FAILURE; // 255
```

## Parameter Parser

Simple Console supports arguments and options pre-defined. Below is a parser object to help use define parameters and
parse `argv` variables. You can pass the parsed data to any function or entry point.

```php
use Asika\SimpleConsole\Console;

function main(array $options) {
    // Run your code...
}

$parser = \Asika\SimpleConsole\Console::createArgvParser();

// Arguments
$parser->addParameter('name', type: Console::STRING, description: 'Your name', required: true);
$parser->addParameter('age', type: Console::INT, description: 'Your age');

// Name starts with `-` or `--` will be treated as option
$parser->addParameter('--height|-h', type: Console::FLOAT, description: 'Your height', required: true);
$parser->addParameter('--location|-l', type: Console::STRING, description: 'Live location', required: true);
$parser->addParameter('--muted|-m', type: Console::BOOLEAN, description: 'Is muted');

main($parser->parse($argv));
```

Same as:

```php
use Asika\SimpleConsole\ArgvParser;
use Asika\SimpleConsole\Console;

$params = Console::parseArgv(
    function (ArgvParser $parser) {
        // Arguments
        $parser->addParameter('name', type: Console::STRING, description: 'Your name', required: true);
        $parser->addParameter('age', type: Console::INT, description: 'Your age');

        // Name starts with `-` or `--` will be treated as option
        $parser->addParameter('--height|-h', type: Console::FLOAT, description: 'Your height', required: true);
        $parser->addParameter('--location|-l', type: Console::STRING, description: 'Live location', required: true);
        $parser->addParameter('--muted|-m', type: Console::BOOLEAN, description: 'Is muted');
    },
    // pass $argv or leave empty
);

main($params);
```

## Parameter Definitions

After upgraded to 2.0, the parameter defining is required for `Console` app, if a provided argument or option is not
exists
in pre-defined parameters, it will raise an error.

```php
// me.php

$app = new Console();
// Arguments
$app->addParameter('name', type: $app::STRING, description: 'Your name', required: true);
$app->addParameter('age', type: $app::INT, description: 'Your age');

// Name starts with `-` or `--` will be treated as option
$app->addParameter('--height', type: $app::FLOAT, description: 'Your height', required: true);
$app->addParameter('--location|-l', type: $app::STRING, description: 'Live location', required: true);
$app->addParameter('--muted|-m', type: $app::BOOLEAN, description: 'Is muted');

$app->execute(
    argv: $argv,
    main: function () use ($app) {
        $app->writeln('Hello');
        $app->writeln('Name: ' . $app->get('name'));
        $app->writeln('Age: ' . $app->get('age'));
        $app->writeln('Height: ' . $app->get('height'));
        $app->writeln('Location: ' . $app->get('location'));
        $app->writeln('Muted: ' . $app->get('muted') ? 'Y' : 'N');

        return $app::SUCCESS;
    }
);
```

Also same as:

```php
// me.php

$app = new class () extends Console
{
    protected function configure(): void
    {
        // Arguments
        $this->addParameter('name', type: $this::STRING, description: 'Your name', required: true);
        $this->addParameter('age', type: $this::INT, description: 'Your age');

        // Name starts with `-` or `--` will be treated as option
        $this->addParameter('--height', type: $this::FLOAT, description: 'Your height', required: true);
        $this->addParameter('--location|-l', type: $this::STRING, description: 'Live location', required: true);
        $this->addParameter('--muted|-m', type: $this::BOOLEAN, description: 'Is muted');
    }
    
    protected function doExecute(): int|bool
    {
        $this->writeln('Hello');
        $this->writeln('Name: ' . $this->get('name'));
        $this->writeln('Age: ' . $this->get('age'));
        $this->writeln('Height: ' . $this->get('height'));
        $this->writeln('Location: ' . $this->get('location'));
        $this->writeln('Muted: ' . ($this->get('muted') ? 'Y' : 'N'));

        return $this::SUCCESS;
    }
};
$app->execute();
```

Now if we enter

```bash
php me.php --name="John Doe " --age=18 --height=1.8 --location=America --muted
```

It shows:

```
Hello
Name: John Doe
Age: 25
Height: 1.8
Location: America
Muted: Y
```

Then, if we enter wrong parameters, Simple Console will throw errors:

```bash
php me.php # [Warning] Required argument "name" is missing.
php me.php Simon eighteen # [Warning] Invalid value type for "age". Expected INT.
php me.php Simon 18 foo bar # [Warning] Unknown argument "foo".
php me.php Simon 18 --nonexists # [Warning] The "-nonexists" option does not exist.
php me.php Simon 18 --location # [Warning] Required value for "location" is missing.
php me.php Simon 18 --muted=ON # [Warning] Option "muted" does not accept value.
php me.php Simon 18 --height one-point-eight # [Warning] Invalid value type for "height". Expected FLOAT.
```

### Show Help

Simple Console supports to describe arguments/options information which follows [docopt](http://docopt.org/) standards.

Add `--help` or `-h` to Console App:

```bash
php me.php --help
```

Will print the help information:

```
Usage:
  me.php [options] [--] <name> [<age>]

Arguments:
  name    Your name
  age     Your age

Options:
  --height=HEIGHT            Your height
  -l, --location=LOCATION    Live location
  -m, --muted                Is muted
  -h, --help                 Show description of all parameters
  -v, --verbosity            The verbosity level of the output

```

Add your heading/epilog and command name:

```php
// Use constructor
$app = new \Asika\SimpleConsole\Console(
    heading: <<<HEADER
    [Console] SHOW ME - v1.0
    
    This command can show personal information.
    HEADER,
    epilog: <<<EPILOG
    $ show-me.php John 18 
    $ show-me.php John 18 --location=Europe --height 1.75 
    
    ...more please see https://show-me.example
    EPILOG,
    commandName: 'show-me.php'
);

// Or set properties

$app->heading = <<<HEADER
[Console] SHOW ME - v1.0

This command can show personal information.
HEADER;

$app->commandName = 'show-me.php'; // If not provided, will auto use script file name
$app->epilog = <<<EPILOG
$ show-me.php John 18 
$ show-me.php John 18 --location=Europe --height 1.75 

...more please see https://show-me.example
EPILOG;

$app->execute();
```

The result:

```
[Console] SHOW ME - v1.0

This command can show personal information.

Usage:
  show-me.php [options] [--] <name> [<age>]

Arguments:
  name    Your name
  age     Your age

Options:
  --height=HEIGHT            Your height
  -l, --location=LOCATION    Live location
  -m, --muted                Is muted
  -h, --help                 Show description of all parameters
  -v, --verbosity            The verbosity level of the output

Help:
$ show-me.php John 18 
$ show-me.php John 18 --location=Europe --height 1.75 

...more please see https://show-me.example
```

### Override Help Information

If your are using class extending, you may override `showHelp()` method to add your own help information.

```php
$app = new class () extends Console
{
    public function showHelp(): void
    {
        $this->writeln(
            <<<HELP
            My script v1.0
            
            Options:
            -h, --help      Show this help message
            -q, --quiet     Suppress output
            -l, --location  Your location
            HELP
        );
    }
```

## Parameter Configurations

To define parameters, you can use `addParameter()` method.

The parameter name without `-` and `--` will be treated as argument.

```php
// Function style
$app->addParameter('name', type: $app::STRING, description: 'Your name', required: true);

// Chaining style
$app->addParameter('name', type: $app::STRING)
    ->description('Your name')
    ->required(true)
    ->default('John Doe');
```

The parameter name starts with `-` and `--` will be treated as options, you can use `|` to separate primary name
and shortcuts.

```php
$app->addParameter('--foo', type: $app::STRING, description: 'Foo description');
$app->addParameter('--muted|-m', type: $app::BOOLEAN, description: 'Muted description');
```

Arguments' name cannot same as options' name, otherwise it will throw an error.

### Get Parameters Value

To get parameters' value, you can use `get()` method, all values will cast to the type which you defined.

```php
$name = $app->get('name'); // String
$height = $app->get('height'); // Int
$muted = $app->get('muted'); // Bool
```

Array access also works:

```php
$name = $app['name'];
$height = $app['height'];
```

If a parameter is not provided, it will return `FALSE`, and if a parameter provided but has no value, it will
return as `NULL`.

```bash
php console.php # `dir` is FALSE
php console.php --dir # `dir` is NULL
php console.php --dir /path/to/dir # `dir` is `/path/to/dir`
```

So you can easily detect the parameter existence and give a default value.

```php
if (($dir = $app['dir']) !== false) {
    $dir ??= '/path/to/default';
}
```

### Parameters Type

You can define the type of parameters, it will auto convert to the type you defined.

| Type    | Argument                     | Option                | Description                                                             |
|---------|------------------------------|-----------------------|-------------------------------------------------------------------------|
| STRING  | String type                  | String type           |                                                                         |
| INT     | Integer type                 | Integer type          |                                                                         |
| FLOAT   | Float type                   | Flot type             | Can be int or float, will all converts to float                         |
| NUMERIC | Int or Float                 | Int or Float          | Can be int or float, will all converts to float                         |
| BOOLEAN | (X)                          | Add `--opt` as `TRUE` | Use `negatable` to supports `--opt` as `TRUE` and `--no-opt` as `FALSE` |
| ARRAY   | Array, must be last argument | Array                 | Can provide multiple as `string[]`                                      |
| LEVEL   | (X)                          | Int type              | Can provide multiple times and convert the times to int                 |

All parameter values parsed from `argv` is default as `string` type, and convert to the type you defined.

#### `ARRAY` type

The `ARRAY` can be use to arguments and options.

If you set an argument as `ARRAY` type, it must be last argument, and you can add more tailing arguments.

```php
$app->addParameter('name', $app::STRING);
$app->addParameter('tag', $app::ARRAY);

// Run: console.php foo a b c d e

$app->get('tag'); // [a, b, c ,d, e]
```

Use `--` to escape all following options, all will be treated as arguments, it is useful if you are
writing a proxy script.

```bash
php listen.php --timeout 500 --wait 100 -- php flower.php hello --name=sakura --location Japan --muted

// The last argument values will be:
// ['php', 'flower.php', 'hello', '--name=sakura', '--location', 'Japan', '--muted']
```

If you set an option as `ARRAY` type, it can be used as `--tag a --tag b --tag c`.

```php
$app->addParameter('--tag|-t', $app::ARRAY);

$app->get('tag'); // [a, b, c]
```

#### `LEVEL` type

The `LEVEL` type is a special type, it will convert the times to int. For example, a verbosity level of `-vvv` will be
converted to `3`,
and `-v` will be converted to `1`. You can use this type to define the verbosity level of your argv parser.

```php
$parser->addParameter('--verbosity|-v', type: $app::LEVEL, description: 'The verbosity level of the output');
```

If you are using `Console` class, the verbosity is built-in option, you don't need to define it again.

### Parameters Options

#### `description`

- **Argument**: Description for the argument, will be shown in help information.
- **Option**: Description for the option, will be shown in help information.

#### `required`

- **Argument**: Required argument must be provided, otherwise it will throw an error.
    - You should not set a required argument after an optional argument.
- **Option**: All options are `optional`.
    - If you set an option as `required`, it means this option requires a value, only `--option` without value is not
      allowed.
    - `boolean` option should not be required.

#### `default`

- **Argument**: Default value for the argument.
    - If not provided, it will be `null`, `false` for boolean type, or `[]` for array type.
- **Option**: Default value for the option.
    - If not provided, it will be `null`, `false` for boolean type, or `[]` for array type.

#### `negatable`

- **Argument**: Argument cannot use this option.
- **Option**: Negatable option. Should work with `boolean` type.
    - If set to `true`, it will support `--xxx|--no-xxx` 2 styles to set `true|false`.
    - If you want to set a boolean option's default as `TRUE` and use `--no-xxx` to set it as `FALSE`, you can do this:
    ```php
    $app->addParameter('--muted|-m', $app::BOOLEAN, default: true, negatable: true);
    ```

### Parameters Parsing

Simple Console follows [docopt](http://docopt.org/) style to parse parameters.

- Long options starts with `--`
- Option shortcut starts with `-`
- If an option `-a` requires value, `-abc` will parse as `$a = bc`
- If option `-a` dose not require value, `-abc` will parse as `$a = true, $b = true, $c = true`
- Long options supports `=` while shortcuts are not. The `--foo=bar` is valid and `-f=bar` is invalid, you should use
  `-f bar`.
- Add `--` to escape all following options, all will be treated as arguments.

## Error Handling

Just throw Exception in `doExecute()`, Console will auto catch error.

``` php
throw new \RuntimeException('An error occurred');
```

If Console app receive an Throwable or Exception, it will render an `[ERROR]` message:

```bash
[Error] An error occurred.
```

Add `-v` to show backtrace if error.

```
[Error] An error occurred.
[Backtrace]:
#0 /path/to/Console.php(145): Asika\SimpleConsole\Console@anonymous->doExecute()
#1 /path/to/test.php(36): Asika\SimpleConsole\Console->execute()
#2 {main}
```

### Wrong Parameters

If you provide wrong parameters, Console will render a `[WARNING]` message with synopsis:

```
[Warning] Invalid value type for "age". Expected INT.

test.php [-h|--help] [-v|--verbosity] [--height HEIGHT] [-l|--location LOCATION] [-m|--muted] [--] <name> [<age>]
```

You can manually raise this `[WARNING]` by throwing `InvalidParameterException`:

```php
if ($app->get('age') < 18) {
    throw new \Asika\SimpleConsole\InvalidParameterException('Age must greater than 18.');
}
```

### Verbosity

You can set verbosity by option `-v`

```bash
php console.php # verbosity: 0
php console.php -v # verbosity: 1
php console.php -vv # verbosity: 2
php console.php -vvv # verbosity: 3
```

or ser it manually in PHP:

```php
$app->verbosity = 3;
```

If `verbosity` is larger than `0`, it will show backtrace in exception output.

You can show your own debug information on different verbosity:

```php
if ($app->verbosity > 2) {
    $app->writeln($debugInfo);
}
```

## The Built-In Options

The `--help|-h` and `--verbosity|-v` options are built-in options if you use `Console` app.

```php
$app = new \Asika\SimpleConsole\Console();
// add parameters...
$app->execute(); // You can use built-in `-h` and `-v` options
```

If you parse `argv` by `ArgvParser`, you must add it manually. To avoid the required parameters error,
you can set `validate` to `false` when parsing. Then validate and cast parameters after parsing and help
content display.

```php
$parser->addParameter(
    '--help|-h',
    static::BOOLEAN,
    'Show description of all parameters',
    default: false
);

$parser->addParameter(
    '--verbosity|-v',
    static::LEVEL,
    'The verbosity level of the output',
);

// Add other parameters...

/** @var \Asika\SimpleConsole\ArgvParser $parser */
$params = $parser->parse($argv, validate: false);

if ($params['help'] !== false) {
    echo \Asika\SimpleConsole\ParameterDescriptor::describe($parser, 'command.php');
    exit(0);
}

// Now we can validate and cast params
$params = $parser->validateAndCastParams($params);

main($params);
```

### Disable Built-In Options for Console App

If you don't want to use built-in options for Console App, you can set `disableDefaultParameters` to `true`:

```php
$app = new \Asika\SimpleConsole\Console();
$app->disableDefaultParameters = true;

// Add it manually
$app->addParameter('--help|-h', $app::BOOLEAN, default: false);

// Set verbosity
$app->verbosity = (int) env('DEBUG_LEVEL');

$app->execute(
    main: function (\Asika\SimpleConsole\Console $app) {
        if ($app->get('help')) {
            $this->showHelp();
            return 0;
        }
        
        // ...
    }
);
```

## Input/Output

### STDIN/STDOUT/STDERR

Simple Console supports to read from STDIN and write to STDOUT/STDERR. The default stream can be set at constructor.

```php
new Console(
    stdout: STDOUT,
    stderr: STDERR,
    stdin: STDIN
);
```

If you want to catch the output, you can set `stdout` to a file pointer or a stream.

```php
$fp = fopen('php://memory', 'r+');

$app = new Console(stdout: $fp);
$app->execute();

rewind($fp);
echo stream_get_contents($fp);
```

### Output Methods

To output messages, you can use these methods:

- `write(string $message, bool $err = false)`: Write to STDOUT or STDERR
- `writeln(string $message, bool $err = false)`: Write to STDOUT or STDERR with a new line
- `newLine(int $lines, bool $err = false)`: Write empty new lines to STDOUT or STDERR

### Input and Asking Questions

To input data, you can use `in()` methods:

```php
// This will wait user enter text...
$app->write('Please enter something: ');
$ans = $app->in();
```

Use `ask()` to ask a question, if return empty string, the default value will instead.

```php
$ans = $app->ask('What is your name: ', [$default]);
```

Use `askConfirm()` to ask a question with `Y/n`:

```php
$ans = $app->askConfirm('Are you sure you want to do this? [y/N]: '); // Return BOOL

// Set default as Yes
$ans = $app->askConfirm('Are you sure you want to do this? [Y/n]: ', 'y');
```

- The `'n', 'no', 'false', 0, '0'` will be `FALSE`.
- The `'y', 'yes', 'true', 1, '1'` will be `TRUE`.

To add your boolean mapping, set values to `boolMapping`

```php
$app->boolMapping[0] = [...]; // Falsy values
$app->boolMapping[1] = [...]; // Truly values
```

## Run Sub-Process

Use `exec()` to run a sub-process, it will instantly print the output and return the result code of the command.

```php
$app->exec('ls');
$app->exec('git status');
$app->exec('git commit ...');
$result = $app->exec('git push');

// All output will instantly print to STDOUT

if ($result->code !== 0) {
    // Failure
}

$result->code; // 0 is success
$result->success; // BOOL
```

Use `mustExec()` to make sure a sub-process should run success, otherwise it will throw an exception.

```php
try {
    $this->mustExec('...');
} catch (\RuntimeException $e) {
    // 
}
```

### Hide Command Name

Bt default, `exec()` and `mustExec()` will show the command name before executing, for example.

```basg
>> git show
...

>> git commit -am ""
...

>> git push
...
```

if you want to hide it, set the arg: `showCmd` to `false`.

```php
$app->exec('cmd...', showCmd: false);
```

### Custom Output

Simple Console use `proc_open()` to run sub-process, so you can set your own output stream by callback.

```php
$log = '';

$app->exec(
    'cmd ...',
    output: function (string $data, bool $err) use ($app, &$log) {
        $app->write($data, $err);
        
        $log .= $data;
    }
);
```

### Disable the Output

Use `false` to disable the output, you can get full output from result object after sub-process finished.

Note, the output will only write to result object if `output` set to `false`. If you set `output` as closure or
keep default `NULL`, the output will be empty in result object.

```php
$result = $app->exec('cmd ...', output: false);

$result->output; // StdOutput of sub-process
$result->errOutput; // StdErr Output of sub-process


// Below will not write to the result object
$result = $app->exec('cmd ...');
// OR
$result = $app->exec('cmd ...', output: function () { ... });

$result->output; // Empty
$result->errOutput; // Empty
```

### Override `exec()`

By now, running sub-process by `prop_open()` is in BETA, if `prop_open()` not work for your environment, simply override
`exec()` to use PHP `system()` instead.

```php
public function exec(string $cmd, \Closure|null $output = null, bool $showCmd = true): ExecResult
{
    !$showCmd || $this->writeln('>> ' . $cmd);
    
    $returnLine = system($cmd, $code);

    return new \Asika\SimpleConsole\ExecResult($code, $returnLine, $returnLine);
}
```

## Delegating Multiple Tasks

If your script has multiple tasks, for example, the build script contains `configure|make|clear` etc...

Here is an example to show how to delegate multiple tasks and pass the necessary params to method interface.

```php
$app = new class () extends Console
{
    protected function configure(): void
    {
        $this->addParameter('task', type: $this::STRING, description: 'Task (configure|build|make|move|clear)', required: true);
        $this->addParameter('--lib-path', type: $this::STRING);
        $this->addParameter('--temp-path', type: $this::STRING);
        $this->addParameter('--nested', type: $this::STRING);
        $this->addParameter('--all', type: $this::STRING);
    }

    protected function doExecute(): int|bool
    {
        $params = [];

        foreach ($this->params as $k => $v) {
            // Use any camel case convert library
            $params[Str::toCamelCase($k)] = $v;
        }

        return $this->{$this['task']}(...$params);
    }

    // `...$args` is required, otherwise the redundant params will make method calling error
    protected function build(string $libPath, string $tempPath, ...$args): int
    {
        $this->writeln("Building: $libPath | $tempPath");
        return 0;
    }

    protected function clear(string $nested, string $dir, ...$args): int
    {
        $this->writeln("Clearing: $nested | $dir");
        return 0;
    }
};
$app->execute();
```

Now run:

```bash
php make.php build --lib-path foo --temp-path bar

# Building foo | bar
```


## Contributing and PR is Welcome

I'm apologize that I'm too busy to fix or handle all issues and reports, but pull-request is very welcome 
and will speed up the fixing process.
