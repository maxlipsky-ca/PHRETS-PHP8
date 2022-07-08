<?php

namespace PHRETS\Models\Search;

use ArrayAccess;
use Closure;
use Countable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use League\Csv\CannotInsertRecord;
use League\Csv\Writer;
use PHRETS\Exceptions\CapabilityUnavailable;
use PHRETS\Session;
use SplTempFileObject;
use Traversable;

class Results implements Countable, ArrayAccess, IteratorAggregate
{
    protected ?string $resource = '';
    protected ?string $class = '';
    protected ?Session $session = null;
    protected mixed $metadata = null;
    protected int $total_results_count = 0;
    protected int $returned_results_count = 0;
    protected mixed $error = null;
    /** @var Collection|Record[] */
    protected Collection|array $results;
    protected array $headers = [];
    protected string $restricted_indicator = '****';
    protected bool $maxrows_reached = false;

    public function __construct()
    {
        $this->results = new Collection();
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return $this
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @param null $keyed_by
     */
    public function addRecord(Record $record, $keyed_by = null)
    {
        // register this Results object as the record's parent automatically
        $record->setParent($this);

        $this->returned_results_count++;

        if (is_callable($keyed_by)) {
            $this->results->put($keyed_by($record), $record);
        } elseif ($keyed_by) {
            $this->results->put($record->get($keyed_by), $record);
        } else {
            $this->results->push($record);
        }
    }

    /**
     * Set which field's value will be used to key the records by.
     *
     * @param $field
     */
    public function keyResultsBy($field)
    {
        $results = clone $this->results;
        $this->results = new Collection();
        foreach ($results as $r) {
            $this->addRecord($r, $field);
        }
    }

    /**
     * Grab a record by it's tracked key.
     *
     * @param $key_id
     */
    public function find($key_id): ?Record
    {
        return $this->results->get($key_id);
    }

    /**
     * @return null
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param null $error
     *
     * @return $this
     */
    public function setError($error): static
    {
        $this->error = $error;

        return $this;
    }

    public function getReturnedResultsCount(): int
    {
        return $this->returned_results_count;
    }

    /**
     * @return $this
     */
    public function setReturnedResultsCount(int $returned_results_count): static
    {
        if (is_int($returned_results_count) == false) {
            throw new \InvalidArgumentException('$returned_results_count should be an integer');
        }

        $this->returned_results_count = $returned_results_count;

        return $this;
    }

    public function getTotalResultsCount(): int
    {
        return $this->total_results_count;
    }

    /**
     * @return $this
     */
    public function setTotalResultsCount(int $total_results_count): static
    {
        if (is_int($total_results_count) == false) {
            throw new \InvalidArgumentException('$total_results_count should be an integer');
        }

        $this->total_results_count = $total_results_count;

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    /**
     * @return $this
     */
    public function setClass(string $class): static
    {
        $this->class = $class;

        return $this;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    /**
     * @return $this
     */
    public function setResource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * @return Session
     */
    public function getSession(): ?Session
    {
        return $this->session;
    }

    /**
     * @return $this
     */
    public function setSession(Session $session): static
    {
        $this->session = $session;

        return $this;
    }

    /**
     * @return null
     *
     * @throws CapabilityUnavailable
     */
    public function getMetadata()
    {
        if (!$this->metadata) {
            $this->metadata = $this->session->GetTableMetadata($this->getResource(), $this->getClass());
        }

        return $this->metadata;
    }

    /**
     * @param null $metadata
     *
     * @return $this
     */
    public function setMetadata($metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getRestrictedIndicator(): string
    {
        return $this->restricted_indicator;
    }

    /**
     * @param $indicator
     *
     * @return $this
     */
    public function setRestrictedIndicator($indicator): static
    {
        $this->restricted_indicator = $indicator;

        return $this;
    }

    public function getIterator(): Traversable
    {
        return $this->results->getIterator();
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->results->offsetExists($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->results->offsetGet($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset) {
            $this->addRecord($value, fn () => $offset);
        } else {
            $this->addRecord($value);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->results->offsetUnset($offset);
    }

    public function count(): int
    {
        return $this->results->count();
    }

    /**
     * @param null $default
     */
    public function first(Closure $callback = null, $default = null): ?Record
    {
        return $this->results->first($callback, $default);
    }

    public function last(): ?Record
    {
        return $this->results->last();
    }

    public function isMaxRowsReached(): bool
    {
        return $this->maxrows_reached == true;
    }

    /**
     * @return $this
     */
    public function setMaxRowsReached(bool $boolean = true): static
    {
        $this->maxrows_reached = $boolean;

        return $this;
    }

    /**
     * Returns an array containing the values from the given field.
     *
     * @param $field
     */
    public function lists($field): array
    {
        $l = [];
        foreach ($this->results as $r) {
            $v = $r->get($field);
            if ($v && !$r->isRestricted($field)) {
                $l[] = $v;
            }
        }

        return $l;
    }

    /**
     * Return results as a large prepared CSV string.
     *
     * @throws CannotInsertRecord
     */
    public function toCSV(): string
    {
        // create a temporary file so we can write the CSV out
        $writer = Writer::createFromFileObject(new SplTempFileObject());

        // add the header line
        $writer->insertOne($this->getHeaders());

        // go through each record
        foreach ($this->results as $r) {
            $record = [];

            // go through each field and ensure that each record is prepared in an order consistent with the headers
            foreach ($this->getHeaders() as $h) {
                $record[] = $r->get($h);
            }
            $writer->insertOne($record);
        }

        // return as a string
        return (string) $writer;
    }

    /**
     * Return results as a JSON string.
     *
     * @throws \JsonException
     */
    public function toJSON(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Return results as a simple array.
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->results as $r) {
            $result[] = $r->toArray();
        }

        return $result;
    }
}
