<?php

namespace PHRETS\Parsers\Login;

class OneFive extends OneX
{
    public function readLine($line): array
    {
        $name = null;
        $value = null;

        if (str_contains((string) $line, '=')) {
            @[$name, $value] = explode('=', (string) $line, 2);
        }

        return [trim($name), trim($value)];
    }
}
