<?php

/**
 * Query-capturing mock database for xtense unit tests.
 * Records all sql_query() calls so tests can assert on exact SQL structure
 * without a real database connection.
 */
class SpyDatabase
{
    /** @var string[] */
    private array $queries = [];

    public function sql_query(string $query)
    {
        $this->queries[] = $query;
        return true;
    }

    public function sql_escape_string(string $string): string
    {
        return addslashes($string);
    }

    public function sql_fetch_assoc($result): array
    {
        return [];
    }

    public function sql_fetch_row($result): array
    {
        return [null];
    }

    private int $lastInsertId = 0;

    public function sql_insertid(): int
    {
        return ++$this->lastInsertId;
    }

    /** @return string[] All captured queries in order. */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getLastQuery(): ?string
    {
        return empty($this->queries) ? null : end($this->queries);
    }

    /**
     * Returns all captured queries whose text contains $substring (case-insensitive).
     * @return string[]
     */
    public function getQueriesContaining(string $substring): array
    {
        return array_values(array_filter(
            $this->queries,
            fn(string $q): bool => stripos($q, $substring) !== false
        ));
    }

    public function reset(): void
    {
        $this->queries = [];
    }
}
