<?php

declare(strict_types=1);

namespace Mrokwor\LaravelLan\Diagnostics;

use Mrokwor\LaravelLan\Config\LanConfiguration;
use Mrokwor\LaravelLan\Diagnostics\Checks\EnvironmentCheck;
use Mrokwor\LaravelLan\Diagnostics\Checks\FirewallHintCheck;
use Mrokwor\LaravelLan\Diagnostics\Checks\HostBindingCheck;
use Mrokwor\LaravelLan\Diagnostics\Checks\NetworkInterfaceCheck;
use Mrokwor\LaravelLan\Diagnostics\Checks\PortCheck;
use Mrokwor\LaravelLan\Diagnostics\Checks\ViteCheck;
use Mrokwor\LaravelLan\Diagnostics\Contracts\DiagnosticCheckInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DiagnosticRunner
{
    /**
     * @param array<DiagnosticCheckInterface>|null $checks
     */
    public function __construct(
        private ?array $checks = null
    ) {
    }

    /**
     * Run all diagnostic checks against the given configuration.
     *
     * @return array<DiagnosticResult>
     */
    public function run(LanConfiguration $config): array
    {
        $checks = $this->checks ?? $this->getDefaultChecks();
        $results = [];

        foreach ($checks as $check) {
            $results[] = $check->check($config);
        }

        return $results;
    }

    /**
     * Check whether there are any blocking failures in the results.
     *
     * @param array<DiagnosticResult> $results
     */
    public function hasFailures(array $results): bool
    {
        foreach ($results as $result) {
            if ($result->isBlocking()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render the diagnostic results to the console output.
     *
     * @param array<DiagnosticResult> $results
     */
    public function render(array $results, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<info>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</info>');
        $output->writeln('<info>             Laravel LAN Diagnostics              </info>');
        $output->writeln('<info>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</info>');
        $output->writeln('');

        foreach ($results as $result) {
            $symbol = $result->status->symbol();
            $tag = $result->status->tag();

            $output->writeln("<{$tag}> {$symbol} {$result->name}</{$tag}>");
            $output->writeln("   {$result->message}");

            if ($result->hint !== null) {
                $output->writeln("   <comment>Tip: {$result->hint}</comment>");
            }

            $output->writeln('');
        }

        $hasFailures = $this->hasFailures($results);

        if ($hasFailures) {
            $output->writeln('<error>Result: One or more diagnostic checks failed. Address the issues above before starting Laravel LAN.</error>');
        } else {
            $output->writeln('<info>Result: All essential checks passed. Laravel LAN is ready to start!</info>');
        }
        $output->writeln('');
    }

    /**
     * @return array<DiagnosticCheckInterface>
     */
    private function getDefaultChecks(): array
    {
        return [
            new EnvironmentCheck(),
            new HostBindingCheck(),
            new NetworkInterfaceCheck(),
            new PortCheck(),
            new ViteCheck(),
            new FirewallHintCheck(),
        ];
    }
}
