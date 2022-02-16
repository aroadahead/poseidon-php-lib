<?php

namespace Poseidon\Data;

use ArrayAccess;
use ArrayIterator;
use AthenaBridge\http\Exception\BadMethodCallException;
use AthenaBridge\Laminas\Json\Encoder\Encoder;
use AthenaBridge\League\Csv\Writer\Writer;
use AthenaBridge\Spatie\ArrayToXml\ArrayToXml;
use AthenaException\Data\KeyNotExistsException;
use Countable;
use DOMException;
use IteratorAggregate;
use JetBrains\PhpStorm\Pure;
use Laminas\Filter\Word\CamelCaseToUnderscore;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use Traversable;
use function array_diff;
use function array_flip;
use function array_key_exists;
use function array_values;
use function array_walk;
use function array_walk_recursive;
use function is_array;
use function is_scalar;
use function spl_object_hash;
use function substr;
use function trim;

class DataObject implements ArrayAccess, Countable, IteratorAggregate
{
    private const CALL_GET = 'get';
    private const CALL_SET = 'set';
    private const CALL_HAS = 'has';
    private const CALL_UNSET = 'uns';
    private const CALL_REMOVE = 'rem';

    protected array $data = [];
    protected ?CamelCaseToUnderscore $camelCaseToUnderscore;
    protected array $underscoreCache = [];

    public function __construct(array $data = [])
    {
        $this -> camelCaseToUnderscore = new CamelCaseToUnderscore();
        if (count($data)) {
            $this -> add($data);
        }
    }

    public function toJson(array $keys = [], array $keysToIgnore = [], bool $removeKeys = false): string
    {
        $data = $this -> toArray($keys, $keysToIgnore, $removeKeys);
        return Encoder ::encode($data);
    }

    /**
     * @throws CannotInsertRecord
     * @throws Exception
     */
    public function toCsv(array $keys = [], array $keysToIgnore = []): string
    {
        $data = $this -> toArray($keys, $keysToIgnore);
        $csv = Writer ::createFromString();
        $csv -> insertOne(array_diff($this -> keys(), $keysToIgnore));
        $csv -> insertAll($data);
        return $csv -> toString();
    }

    /**
     * @throws DOMException
     */
    public function toXml(array $keys = [], array $keysToIgnore = [], bool $useXmlDeclaration = true): string
    {
        $data = $this -> toArray($keys, $keysToIgnore);
        $xml = new ArrayToXml($data);
        if ($useXmlDeclaration) {
            return $xml -> prettify() -> toXml();
        }
        return $xml -> dropXmlDeclaration() -> prettify() -> toXml();
    }

    public function toArray(array $keys = [], array $keysToIgnore = [], bool $removeKeys = false): array
    {
        if (empty($keys)) {
            return $this -> all($keysToIgnore);
        }
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this -> get($key);
        }
        if ($removeKeys) {
            return array_values($result);
        }
        return $result;
    }

    public function set(string $key, mixed $data): void
    {
        $this -> offsetSet($key, $data);
    }

    #[Pure] public function get(string $key): mixed
    {
        return $this -> offsetGet($key);
    }

    public function has(string $key): bool
    {
        return $this -> offsetExists($key);
    }

    public function flush(): void
    {
        array_walk_recursive($this -> data, function ($item, $key) {
            $this -> removeItem($key);
        });
    }

    public function exchangeArray(array $data): void
    {
        $this -> flush();
        array_walk($data, function ($item, $key) {
            $this -> set($key, $item);
        });
    }

    public function getOrFail(string $key): mixed
    {
        if (!$this -> hasItem($key)) {
            throw new KeyNotExistsException("{$key} does not exist in data object.");
        }
        return $this -> getItem($key);
    }

    public function reduce(array $data): void
    {
        array_walk($data, function ($item) {
            $this -> removeItem($item);
        });
    }

    public function add(mixed $data, mixed $value = null): void
    {
        if (is_array($data)) {
            $setData = function ($item, $key) {
                $this -> offsetSet($key, $item);
            };
            array_walk($data, $setData);
        }
        if (is_scalar($data)) {
            $this -> offsetSet($data, $value);
        }
    }

    public function keys(): array
    {
        return array_keys($this -> data);
    }

    public function all(array $minus = []): array
    {
        return array_diff_key($this -> data, array_flip($minus));
    }

    public function removeItem(string $name): void
    {
        $this -> offsetUnset($name);
    }

    #[Pure] public function getItem(string $name): mixed
    {
        return $this -> offsetGet($name);
    }

    public function setItem(string $name, mixed $value): void
    {
        $this -> offsetSet($name, $value);
    }

    #[Pure] public function hasItem(string $name): bool
    {
        return $this -> offsetExists($name);
    }

    public function hashCode(): string
    {
        return spl_object_hash($this);
    }

    public function __unset(string $name): void
    {
        $this -> offsetUnset($name);
    }

    public function __set(string $name, $value): void
    {
        $this -> offsetSet($name, $value);
    }

    #[Pure] public function __get(string $name)
    {
        return $this -> offsetGet($name);
    }

    #[Pure] public function __isset(string $name): bool
    {
        return $this -> offsetExists($name);
    }

    public function __call(string $name, array $arguments)
    {
        $method = trim($name);
        switch (substr($method, 0, 3)) {
            case self::CALL_GET:
                $key = $this -> underscore(substr($method, 3));
                return $this -> offsetGet($key);
            case self::CALL_SET:
                $key = $this -> underscore(substr($method, 3));
                $value = $arguments[0] ?? null;
                $this -> offsetSet($key, $value);
                break;
            case self::CALL_UNSET:
                $key = $this -> underscore(substr($method, 5));
                $this -> offsetUnset($key);
                break;
            case self::CALL_REMOVE:
                $key = $this -> underscore(substr($method, 6));
                $this -> offsetUnset($key);
                break;
            case self::CALL_HAS:
                $key = $this -> underscore(substr($method, 3));
                return $this -> offsetExists($key);
            default:
                throw new BadMethodCallException("$method is not available in DataObject.");
        }
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        if (array_key_exists($offset, $this -> data)) {
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    #[Pure] public function offsetGet($offset): mixed
    {
        if ($this -> offsetExists($offset)) {
            return $this -> data[$offset];
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value): void
    {
        $this -> data[$offset] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset)
    {
        if ($this -> offsetExists($offset)) {
            $data = $this -> offsetGet($offset);
            if (is_object($data)) {
                $data -> __destruct();
            }
            unset($this -> data[$offset]);
        }
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this -> data);
    }

    public function count(): int
    {
        return count($this -> data);
    }

    public function underscore($name): string
    {
        if (isset($this -> underscoreCache[$name])) {
            return $this -> underscoreCache[$name];
        }
        $result = strtolower($this -> camelCaseToUnderscore -> filter($name));
        $this -> underscoreCache[$name] = $result;
        return $result;
    }
}