<?php

/**
 * Minimal test reporter: groups checks into sections and prints PASS / FAIL.
 */
final class TestRunner
{
    private array $results = [];
    private string $section = '';
    private float $started;

    public function __construct()
    {
        $this->started = microtime(true);
        echo PHP_EOL . 'USLS IT Helpdesk: security and feature test suite' . PHP_EOL . str_repeat('=', 60) . PHP_EOL;
    }

    public function section(string $name): void
    {
        $this->section = $name;
        echo PHP_EOL . $name . PHP_EOL . str_repeat('-', strlen($name)) . PHP_EOL;
    }

    public function check(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['section' => $this->section, 'name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? '  PASS  ' : '  FAIL  ') . $name . (!$passed && $detail !== '' ? "  [$detail]" : '') . PHP_EOL;
    }

    public function report(): int
    {
        $failed = array_filter($this->results, static fn ($r) => !$r['passed']);
        $total = count($this->results);
        echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
        printf("%d checks, %d passed, %d failed in %.1f s%s", $total, $total - count($failed), count($failed), microtime(true) - $this->started, PHP_EOL);

        // Machine-readable copy for documentation
        file_put_contents(__DIR__ . '/../last-run.json', json_encode([
            'date'    => date('Y-m-d H:i'),
            'total'   => $total,
            'failed'  => count($failed),
            'results' => $this->results,
        ], JSON_PRETTY_PRINT));

        return $failed ? 1 : 0;
    }
}
