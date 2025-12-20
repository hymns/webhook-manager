<?php

namespace App\Http\Controllers;

use App\Models\FirewallRule;
use App\Services\FirewallService;
use Illuminate\Http\Request;

class FirewallController extends Controller
{
    public function __construct(
        protected FirewallService $firewall
    ) {}

    /**
     * Display firewall rules
     */
    public function index()
    {
        $rules = FirewallRule::orderBy('is_system', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $firewallStatus = $this->firewall->getStatus();
        $firewallType = $this->firewall->getFirewallType();

        return view('firewall.index', compact('rules', 'firewallStatus', 'firewallType'));
    }

    /**
     * Store a new firewall rule
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'action' => 'required|in:allow,deny',
            'port' => 'nullable|string|max:50',
            'protocol' => 'nullable|in:tcp,udp,any',
            'from_ip' => 'nullable|ip',
            'direction' => 'required|in:in,out,both',
        ]);

        // Add rule to UFW
        $result = $this->firewall->addRule(
            $validated['action'],
            $validated['port'] ?? null,
            $validated['protocol'] ?? null,
            $validated['from_ip'] ?? null,
            $validated['direction']
        );

        if (!$result['success']) {
            return back()->with('error', 'Failed to add firewall rule: ' . ($result['error'] ?? 'Unknown error'));
        }

        // Save to database
        FirewallRule::create($validated);

        return back()->with('success', 'Firewall rule added successfully');
    }

    /**
     * Delete a firewall rule
     */
    public function destroy(FirewallRule $firewallRule)
    {
        if ($firewallRule->is_system) {
            return back()->with('error', 'System rules cannot be deleted');
        }

        // Note: We can't easily delete from UFW by ID, so we just delete from DB
        // User will need to manually reset UFW if they want to clean up
        $firewallRule->delete();

        return back()->with('success', 'Firewall rule deleted from database. Run "Reset UFW" to clean up actual rules.');
    }

    /**
     * Enable Firewall
     */
    public function enable()
    {
        $result = $this->firewall->enable();

        if (!$result['success']) {
            return back()->with('error', 'Failed to enable firewall: ' . ($result['error'] ?? 'Unknown error'));
        }

        return back()->with('success', 'Firewall enabled successfully');
    }

    /**
     * Disable Firewall
     */
    public function disable()
    {
        $result = $this->firewall->disable();

        if (!$result['success']) {
            return back()->with('error', 'Failed to disable firewall: ' . ($result['error'] ?? 'Unknown error'));
        }

        return back()->with('success', 'Firewall disabled successfully');
    }

    /**
     * Reset Firewall (delete all rules)
     */
    public function reset()
    {
        $result = $this->firewall->reset();

        if (!$result['success']) {
            return back()->with('error', 'Failed to reset firewall: ' . ($result['error'] ?? 'Unknown error'));
        }

        // Delete all non-system rules from database
        FirewallRule::where('is_system', false)->delete();

        return back()->with('success', 'Firewall reset successfully');
    }

    /**
     * Toggle rule active status
     */
    public function toggle(FirewallRule $firewallRule)
    {
        $firewallRule->update([
            'is_active' => !$firewallRule->is_active
        ]);

        return back()->with('success', 'Rule status updated');
    }
}
