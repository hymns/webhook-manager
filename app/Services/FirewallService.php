<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Exception;

class FirewallService
{
    protected string $firewallType;

    public function __construct()
    {
        $this->firewallType = $this->detectFirewallType();
    }

    /**
     * Detect which firewall is available (ufw or firewalld)
     */
    protected function detectFirewallType(): string
    {
        // Check for firewalld first (RHEL-based)
        $result = Process::run('which firewall-cmd 2>/dev/null');
        if ($result->successful() && !empty(trim($result->output()))) {
            return 'firewalld';
        }

        // Check for ufw (Debian-based)
        $result = Process::run('which ufw 2>/dev/null');
        if ($result->successful() && !empty(trim($result->output()))) {
            return 'ufw';
        }

        return 'none';
    }

    /**
     * Get firewall type
     */
    public function getFirewallType(): string
    {
        return $this->firewallType;
    }

    /**
     * Get firewall status (generic method)
     */
    public function getStatus(): array
    {
        if ($this->firewallType === 'firewalld') {
            return $this->getFirewalldStatus();
        }
        return $this->getUfwStatus();
    }

    /**
     * Get UFW status
     */
    public function getUfwStatus(): array
    {
        try {
            $result = Process::run('sudo ufw status verbose');
            $output = $result->output();
            
            if (strpos($output, 'Status: active') !== false) {
                return [
                    'enabled' => true,
                    'output' => $output,
                    'type' => 'ufw'
                ];
            }
            
            return [
                'enabled' => false,
                'output' => $output,
                'type' => 'ufw'
            ];
        } catch (Exception $e) {
            return [
                'enabled' => false,
                'error' => $e->getMessage(),
                'type' => 'ufw'
            ];
        }
    }

    /**
     * Get firewalld status
     */
    public function getFirewalldStatus(): array
    {
        try {
            $result = Process::run('sudo firewall-cmd --state');
            $output = trim($result->output());
            
            return [
                'enabled' => $output === 'running',
                'output' => $output,
                'type' => 'firewalld'
            ];
        } catch (Exception $e) {
            return [
                'enabled' => false,
                'error' => $e->getMessage(),
                'type' => 'firewalld'
            ];
        }
    }

    /**
     * Enable firewall (generic method)
     */
    public function enable(): array
    {
        if ($this->firewallType === 'firewalld') {
            return $this->enableFirewalld();
        }
        return $this->enableUfw();
    }

    /**
     * Enable UFW
     */
    public function enableUfw(): array
    {
        try {
            $result = Process::run('sudo ufw --force enable');
            
            return [
                'success' => true,
                'message' => 'Firewall enabled successfully',
                'output' => $result->output()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enable firewalld
     */
    public function enableFirewalld(): array
    {
        try {
            Process::run('sudo systemctl start firewalld');
            Process::run('sudo systemctl enable firewalld');
            
            return [
                'success' => true,
                'message' => 'Firewall enabled successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Disable firewall (generic method)
     */
    public function disable(): array
    {
        if ($this->firewallType === 'firewalld') {
            return $this->disableFirewalld();
        }
        return $this->disableUfw();
    }

    /**
     * Disable UFW
     */
    public function disableUfw(): array
    {
        try {
            $result = Process::run('sudo ufw disable');
            
            return [
                'success' => true,
                'message' => 'Firewall disabled successfully',
                'output' => $result->output()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Disable firewalld
     */
    public function disableFirewalld(): array
    {
        try {
            Process::run('sudo systemctl stop firewalld');
            
            return [
                'success' => true,
                'message' => 'Firewall disabled successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Add firewall rule (generic method)
     */
    public function addRule($action, $port = null, $protocol = null, $fromIp = null, $direction = 'in'): array
    {
        if ($this->firewallType === 'firewalld') {
            return $this->addFirewalldRule($action, $port, $protocol, $fromIp);
        }
        return $this->addUfwRule($action, $port, $protocol, $fromIp, $direction);
    }

    /**
     * Add UFW rule
     */
    public function addUfwRule($action, $port = null, $protocol = null, $fromIp = null, $direction = 'in'): array
    {
        try {
            $command = "sudo ufw {$action}";
            
            if ($direction && $direction !== 'both') {
                $command .= " {$direction}";
            }
            
            if ($port) {
                $command .= " {$port}";
            }
            
            if ($protocol) {
                $command .= "/{$protocol}";
            }
            
            if ($fromIp) {
                $command .= " from {$fromIp}";
            }
            
            $result = Process::run($command);
            Process::run('sudo ufw reload');
            
            return [
                'success' => true,
                'message' => 'Rule added successfully',
                'output' => $result->output()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Add firewalld rule
     */
    public function addFirewalldRule($action, $port = null, $protocol = null, $fromIp = null): array
    {
        try {
            $protocol = $protocol ?: 'tcp';
            
            if ($action === 'allow' && $port) {
                $result = Process::run("sudo firewall-cmd --permanent --add-port={$port}/{$protocol}");
            } elseif ($action === 'deny' && $port) {
                // firewalld uses rich rules for deny
                $result = Process::run("sudo firewall-cmd --permanent --add-rich-rule='rule port port=\"{$port}\" protocol=\"{$protocol}\" reject'");
            }
            
            // Add source IP restriction if specified
            if ($fromIp && $port) {
                Process::run("sudo firewall-cmd --permanent --add-rich-rule='rule family=\"ipv4\" source address=\"{$fromIp}\" port port=\"{$port}\" protocol=\"{$protocol}\" accept'");
            }
            
            // Reload to apply
            Process::run('sudo firewall-cmd --reload');
            
            return [
                'success' => true,
                'message' => 'Rule added successfully',
                'output' => $result->output() ?? ''
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete firewall rule (generic method)
     */
    public function deleteRule($ruleNumber, $port = null, $protocol = null): array
    {
        if ($this->firewallType === 'firewalld') {
            return $this->deleteFirewalldRule($port, $protocol);
        }
        return $this->deleteUfwRule($ruleNumber);
    }

    /**
     * Delete UFW rule
     */
    public function deleteUfwRule($ruleNumber): array
    {
        try {
            $result = Process::run("sudo ufw --force delete {$ruleNumber}");
            
            return [
                'success' => true,
                'message' => 'Rule deleted successfully',
                'output' => $result->output()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete firewalld rule
     */
    public function deleteFirewalldRule($port, $protocol = 'tcp'): array
    {
        try {
            $result = Process::run("sudo firewall-cmd --permanent --remove-port={$port}/{$protocol}");
            Process::run('sudo firewall-cmd --reload');
            
            return [
                'success' => true,
                'message' => 'Rule deleted successfully',
                'output' => $result->output()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Reset firewall (generic method)
     */
    public function reset(): array
    {
        if ($this->firewallType === 'firewalld') {
            return $this->resetFirewalld();
        }
        return $this->resetUfw();
    }

    /**
     * Reset UFW (delete all rules)
     */
    public function resetUfw(): array
    {
        try {
            $result = Process::run('sudo ufw --force reset');
            
            return [
                'success' => true,
                'message' => 'Firewall reset successfully',
                'output' => $result->output()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Reset firewalld to defaults
     */
    public function resetFirewalld(): array
    {
        try {
            // Remove all custom ports
            $result = Process::run('sudo firewall-cmd --permanent --zone=public --remove-all-ports 2>/dev/null || true');
            Process::run('sudo firewall-cmd --reload');
            
            return [
                'success' => true,
                'message' => 'Firewall reset successfully',
                'output' => $result->output()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get list of open ports (firewalld)
     */
    public function getFirewalldPorts(): array
    {
        try {
            $result = Process::run('sudo firewall-cmd --list-ports');
            $ports = array_filter(explode(' ', trim($result->output())));
            
            return [
                'success' => true,
                'ports' => $ports
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'ports' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get list of services (firewalld)
     */
    public function getFirewalldServices(): array
    {
        try {
            $result = Process::run('sudo firewall-cmd --list-services');
            $services = array_filter(explode(' ', trim($result->output())));
            
            return [
                'success' => true,
                'services' => $services
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'services' => [],
                'error' => $e->getMessage()
            ];
        }
    }
}
