# PHP Simple Console

Single file console framework to help you write build scripts.

## Installation

Use composer:

``` bash
composer require asika/simple-console
```

Or downlaod single file to use: [Download Here](https://raw.githubusercontent.com/asika32764/php-simple-console/master/src/Console.php)

## Getting Started

Use closure

``` php
// Include single file
include_once __DIR__ . '/Console.php';

// Or use composer
include_once __DIR__ . '/vendor/autolod.php';

$app = new \Asika\SimpleConsole\Console;

// Use closure
$app->execute(function (\Asika\SimpleConsole\Console $app)
{
    // PHP 5.3
    $app->out('Hello');

    // PHP 5.4 or higher use $this
    $this->out('Hello');

    // Return TRUE will auto convert to 0 exitcode.
    return true;
});
```

Or Create your own class.

``` php
class Build extends \Asika\SimpleConsole\Console
{
    protected $help = <<<HELP
[Usage] php build.php <version>

[Options]
    h | help   Show help information
    v          Show more debug information.
HELP;

    protected function doExecute ()
    {
        $this->out('Hello');

        // Return TRUE will auto convert to 0 exitcode.
        return true;
    }
}

$app = new Build;
$app->execute();
```

## Show HELP

Add `-h` or `--help` to show usage, you can add custom usage to `$this->help`, or override `$this->getHelp()`.

If you want to change `h` and `help` option, override `$this->helpOptions = array('...')`.

## Handle Error

Just throw Exception in `doExecute()`, Console will auto catch error.

``` php
throw new \RuntimeException('...');
```

Add `-v` to show backtrace if error.

## Handle Wrong Arguments

Wrong Argument use `\Asika\SimpleConsole\CommandArgsException`

``` php
$arg = $this->getArgument(0);

if (!$arg)
{
    throw new \Asika\SimpleConsole\CommandArgsException('Please enter a name.');
}
```

Console will auto show help information.

``` bash
[Warning] Please enter a name.

[Usage] console.php <name>

[Options]
    h | help   Show help info.
    v          Show more debug information.
```

## API

### `getArgument($order[, $default = null])`

``` php
$first = $this->getArgument(0, 'default value');
```

### `setArgument($order, $$value)`

``` php
$this->setArgument(1, 'value');
```

### `getOption($name: array|string[, $default = null])`

Get option `--foo`

``` php
$this->getOption('foo');
```

Get option `-f` or `--foo`, first match will return.

``` php
$this->getOption(array('f', 'foo'));
```

> NOTE:
> `-abc` will convert to `a => 1, b => 1, c => 1`
> And `-vvv` will convert to `v => 3`

### `setOption($name, $value)`

Set otpion to toption list. `$name` also support array.

### `out($string[, $newline: bool = false])`

Write to STDOUT,

``` bash
$this->out('Hello')->out('World');
```

### `err($string[, $newline: bool = false])`

Write to STDERR

``` bash
$this->err('Hello')->err('World');
```

### `in($string[$default = null, $bool = false)`

Ask a question, read from STDIN

``` bash
$un = $this->in('Please enter username: ', 'default_name');
```

Read as boolean:

- `yes, y, 1, true` will convert to `TRUE`
- `no, n, 0, false` will convert to `FALSE`

``` bash
$bool = $this->in('Are you sure? [Y/n]', [default true/false], true);
```

### `exec($cmd)`

A proxy to execute a cmd by `exec()` and return value.

It will add a title `>> {your command}` before exec so you will know what has been executed.
