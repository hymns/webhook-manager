<?php

namespace App\Services\Firewall;

class FirewalldService extends AbstractFirewallService
{
    public function getType(): string
    {
        return 'firewalld';
    }

    public function getStatus(): array
    {
        $result = $this->runCommand('sudo /usr/bin/firewall-cmd --state');
        
        $isActive = str_contains($result['output'], 'running');
        
        return [
            'active' => $isActive,
            'status' => $isActive ? 'running' : 'not running',
            'output' => $result['output'],
        ];
    }

    public function enable(): array
    {
        // Start and enable firewalld (RHEL uses /usr/bin/systemctl)
        $this->runCommand('sudo /usr/bin/systemctl start firewalld');
        $result = $this->runCommand('sudo /usr/bin/systemctl enable firewalld');
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Firewalld enabled successfully' : 'Failed to enable firewalld',
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }

    public function disable(): array
    {
        $this->runCommand('sudo /usr/bin/systemctl stop firewalld');
        $result = $this->runCommand('sudo /usr/bin/systemctl disable firewalld');
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Firewalld disabled successfully' : 'Failed to disable firewalld',
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }

    public function addRule(string $port, string $protocol = 'tcp'): array
    {
        $rule = "{$port}/{$protocol}";
        
        // Add to runtime
        $this->runCommand("sudo /usr/bin/firewall-cmd --add-port={$rule}");
        
        // Add permanently
        $result = $this->runCommand("sudo /usr/bin/firewall-cmd --permanent --add-port={$rule}");
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? "Rule added: {$rule}" : "Failed to add rule: {$rule}",
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }

    public function deleteRule(string $port, string $protocol = 'tcp'): array
    {
        $rule = "{$port}/{$protocol}";
        
        // Remove from runtime
        $this->runCommand("sudo /usr/bin/firewall-cmd --remove-port={$rule}");
        
        // Remove permanently
        $result = $this->runCommand("sudo /usr/bin/firewall-cmd --permanent --remove-port={$rule}");
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? "Rule deleted: {$rule}" : "Failed to delete rule: {$rule}",
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }

    public function reset(): array
    {
        // Reload to default configuration
        $result = $this->runCommand('sudo /usr/bin/firewall-cmd --complete-reload');
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Firewalld reset successfully' : 'Failed to reset firewalld',
            'output' => $result['output'],
            'error' => $result['error'],
        ];
    }

    public function getRules(): array
    {
        $result = $this->runCommand('sudo /usr/bin/firewall-cmd --list-ports');
        
        if (!$result['success']) {
            return [];
        }

        $rules = [];
        $ports = array_filter(explode(' ', trim($result['output'])));
        
        foreach ($ports as $index => $port) {
            $rules[] = [
                'number' => $index + 1,
                'rule' => $port,
            ];
        }

        // Also get services
        $servicesResult = $this->runCommand('sudo /usr/bin/firewall-cmd --list-services');
        if ($servicesResult['success']) {
            $services = array_filter(explode(' ', trim($servicesResult['output'])));
            foreach ($services as $service) {
                $rules[] = [
                    'number' => count($rules) + 1,
                    'rule' => "service: {$service}",
                ];
            }
        }

        return $rules;
    }
}
