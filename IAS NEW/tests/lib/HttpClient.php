<?php

/**
 * A tiny browser for the tests: keeps its own cookies, never follows redirects,
 * and can submit forms with the CSRF token taken from the page, like a real user.
 */
final class HttpClient
{
    /** Error text found in any response (PHP warnings, stack traces, SQL errors). */
    public static array $leaks = [];

    private $curl;

    public function __construct(private string $base, private string $userAgent)
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE     => '',      // in-memory cookie jar for this client only
            CURLOPT_USERAGENT      => $userAgent,
            CURLOPT_HTTPHEADER     => ['Accept-Language: en'],
            CURLOPT_TIMEOUT        => 30,
        ]);
    }

    public function get(string $path): HttpResponse
    {
        return $this->send('GET', $path);
    }

    public function post(string $path, array $data): HttpResponse
    {
        return $this->send('POST', $path, $data);
    }

    /**
     * Loads $page, reads its CSRF token, then posts $data to $action (default: the same page).
     */
    public function submit(string $page, array $data, ?string $action = null): HttpResponse
    {
        $form = $this->get($page);
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $form->body, $m);
        return $this->post($action ?? $page, $data + ['csrf_token' => $m[1] ?? '']);
    }

    public function cookie(string $name): string
    {
        foreach (curl_getinfo($this->curl, CURLINFO_COOKIELIST) as $line) {
            $parts = explode("\t", $line);
            if (($parts[5] ?? '') === $name) {
                return $parts[6] ?? '';
            }
        }
        return '';
    }

    public function setCookie(string $name, string $value): void
    {
        curl_setopt($this->curl, CURLOPT_COOKIELIST, "Set-Cookie: {$name}={$value}; path=/; domain=127.0.0.1");
    }

    private function send(string $method, string $path, ?array $data = null): HttpResponse
    {
        curl_setopt($this->curl, CURLOPT_URL, $this->base . ltrim($path, '/'));
        curl_setopt($this->curl, CURLOPT_PATH_AS_IS, true);   // keep ../ in URLs so traversal can be tested
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
        if ($method === 'POST') {
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($data ?? []));
        } else {
            curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        }

        $raw = (string) curl_exec($this->curl);
        $headerSize = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $response = new HttpResponse(
            (int) curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE),
            substr($raw, 0, $headerSize),
            substr($raw, $headerSize)
        );

        foreach (['Fatal error', 'Warning:', 'Notice:', 'Deprecated:', 'Stack trace', 'PDOException', 'SQLSTATE', 'Uncaught'] as $needle) {
            if (str_contains($response->body, $needle)) {
                self::$leaks[] = "$needle in $path";
            }
        }
        return $response;
    }
}

final class HttpResponse
{
    public string $location;

    public function __construct(public int $status, public string $headers, public string $body)
    {
        $this->location = $this->header('location');
    }

    /** Value of the last header with this name (lower-case), or an empty string. */
    public function header(string $name): string
    {
        preg_match_all('/^' . preg_quote($name, '/') . ':\s*(.*)$/mi', $this->headers, $m);
        return trim((string) end($m[1]));
    }
}
