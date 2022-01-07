<?php

namespace Poseidon\Data;

use ArrayIterator;
use Bridge\Laminas\Filter\Word\CamelCaseToUnderscore;
use http\Exception\InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use Traversable;
use ArrayAccess;
use Countable;
use IteratorAggregate;

use function array_flip;
use function substr;
use function trim;
use function array_key_exists;
use function array_walk;
use function is_array;
use function is_scalar;
use function spl_object_hash;

class DataObject implements ArrayAccess, Countable, IteratorAggregate
{
    private const CALL_GET    = 'get';
    private const CALL_SET    = 'set';
    private const CALL_HAS    = 'has';
    private const CALL_UNSET  = 'uns';
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

    public function keys(): array
    {
        return array_keys($this -> data);
    }

    public function all(array $minus = []): array
    {
        return array_diff_key($this -> data, array_flip($minus));
    }

    public function removeItem(string $name):void
    {
        $this->offsetUnset($name);
    }

    public function getItem(string $name):mixed
    {
        return $this->offsetGet($name);
    }

    public function setItem(string $name,mixed $value):void
    {
        $this->offsetSet($name,$value)
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

    public function hashCode(): string
    {
        return spl_object_hash($this);
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

    /**
     * @inheritDoc
     */
    public function offsetExists($offset)
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
                throw new InvalidArgumentException("$method is not available in DataObject.");
        }
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