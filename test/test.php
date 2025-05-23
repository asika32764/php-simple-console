<?php

use Asika\SimpleConsole\Console;

include_once __DIR__ . '/../vendor/autoload.php';

$app = new class () extends Console
{

    protected function configure(): void
    {
    }

    protected function doExecute(): int|bool
    {
        $r = $this->exec('ls', false);

        var_dump($r);

        // $process = \Symfony\Component\Process\Process::fromShellCommandline('ls');
        // $process->run(function ($type, $data) {
        //     echo $data;
        // });
        // echo $process->getOutput();

        return 0;
    }
};
$app->execute();

