<?php

namespace TondbadSwoole\Services;

class ExampleService
{
    /**
     * Return a greeting message.
     *
     * @param string $name
     * @return string
     */
    public function getGreeting(string $name): string
    {
        return "Hello, " . ucfirst($name) . "!";
    }
}
