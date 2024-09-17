<?php

use OpenSwoole\Process;

require_once __DIR__ . '/../vendor/autoload.php';

// Function to start a server in a separate process
function startServer($script)
{
    $process = new Process(function (Process $worker) use ($script) {
        // Execute the PHP script for the server
        $worker->exec('/usr/bin/php', [$script]);
    }, false, false); // Set redirect_stdin_stdout and create_pipe to false
    $process->start();
    return $process;
}

// Start the HTTP server (server.php)
$process1 = startServer(__DIR__ . '/server.php');

// Start the gRPC server (grpc.php)
$process2 = startServer(__DIR__ . '/grpc.php');

// Array to hold the processes
$processes = [$process1, $process2];

// Handle signals for graceful shutdown
Process::signal(SIGTERM, function () use ($processes) {
    foreach ($processes as $process) {
        Process::kill($process->pid, SIGTERM);
    }
    exit;
});

// Keep the main script running and wait for child processes
while (true) {
    $ret = Process::wait(true);
    if ($ret) {
        // One of the child processes has exited
        echo "Process {$ret['pid']} exited with code {$ret['code']}\n";
        // Optionally, restart the process or handle the exit
    }
}