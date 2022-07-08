<?php

namespace PHRETS\Parsers\Login;

abstract class OneX
{
    protected array $capabilities = [];
    protected array $details = [];
    protected array $valid_transactions = [
        'Action', 'ChangePassword', 'GetObject', 'Login', 'LoginComplete', 'Logout', 'Search', 'GetMetadata',
        'ServerInformation', 'Update', 'PostObject', 'GetPayloadList',
    ];

    public function parse($body): void
    {
        $lines = explode("\r\n", (string) $body);
        if (empty($lines[3])) {
            $lines = explode("\n", (string) $body);
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === '0') {
                continue;
            }

            [$name, $value] = $this->readLine($line);
            if ($name) {
                if (in_array($name, $this->valid_transactions) || preg_match('/^X\-/', (string) $name)) {
                    $this->capabilities[$name] = $value;
                } else {
                    $this->details[$name] = $value;
                }
            }
        }
    }

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    abstract public function readLine($line);
}
