<?php

declare(strict_types=1);

namespace Drupal\unicorn_core\Support;

use Drupal\unicorn_core\Accessors\Data;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;

use function is_iterable;

/**
 * @template TKey of array-key
 * @template T
 *
 * @extends ArrayCollection<TKey, T>
 */
class Collection extends ArrayCollection implements JsonSerializable
{

  /**
   * Determine whether the collection contains at least one element.
   *
   * Convenience inverse of isEmpty().
   */
  public function isNotEmpty(): bool
  {
    return !$this->isEmpty();
  }

  /**
   * Transform each item using the given callback and return a new collection.
   *
   * Allows changing both value type and collection implementation.
   *
   * @template NewT
   * @template NewCollection of ArrayCollection<int, NewT>
   *
   * @param callable(T): NewT $callback
   * @param class-string<NewCollection> $collectionClass
   *
   * @return NewCollection
   */
  public function transform(callable $callback, string $collectionClass = self::class): mixed
  {
    if ($this->acceptableCollection($collectionClass)) {
      $transformed = array_map($callback, $this->toArray());

      return new $collectionClass($transformed);
    }

    throw new InvalidArgumentException("Target class: {$collectionClass} must extend ArrayCollection.");
  }

  /**
   * Reduces the collection to a single value using the callback.
   *
   * @template R
   *
   * @param Closure(R|null, T): R $func
   * @param R|null $initial
   *
   * @return R|null
   */
  #[\Override]
  public function reduce(Closure $func, mixed $initial = null): mixed
  {
    return array_reduce($this->getValues(), $func, $initial);
  }

  /**
   * Finds the first element that matches the predicate.
   *
   * @template TDefault
   *
   * @param Closure(T, TKey): bool $p
   * @param TDefault $default
   *
   * @return T|TDefault
   */
  #[\Override]
  public function findFirst(Closure $p, mixed $default = null): mixed
  {
    foreach ($this as $key => $item) {
      if ($p($item, $key)) {
        return $item;
      }
    }

    return $default;
  }

  /**
   * Check whether a class name is an accepted collection implementation.
   *
   * Supported:
   * - ArrayCollection
   * - subclasses of ArrayCollection
   * - BaseCollection subclasses
   */
  private function acceptableCollection(string $collectionClass): bool
  {
    return is_subclass_of($collectionClass, ArrayCollection::class)
      || ArrayCollection::class === $collectionClass;
  }

  /**
   *  Group items by a computed or extracted key.
   *
   * @template TGroupKey of array-key
   *
   * @param (Closure(T, TKey): TGroupKey)|string $arg
   *
   * @return self<($arg is string ? array-key : TGroupKey), static>
   */
  public function groupBy($arg): self
  {
    $accepted = is_string($arg) || $arg instanceof Closure;
    if (!$accepted) {
      throw new InvalidArgumentException('Argument must be a string or a Closure.');
    }
    $results = [];

    foreach ($this->toArray() as $key => $value) {
      $newKey = is_string($arg) ? $this->getFromEntry($value, $arg) : $arg($value, $key);

      ($results[(string)$newKey] ??= static::wrap())->add($value);
    }

    return new self($results);
  }

  /**
   * Key the collection by a computed or extracted key.
   *
   * When multiple items resolve to the same key, the last item wins.
   *
   * @template TKeyBy of array-key
   *
   * @param (Closure(T, TKey): TKeyBy)|string $arg
   *
   * @return static<TKeyBy, T>
   */
  public function keyBy(Closure|string $arg): static
  {

    $results = [];
    foreach ($this->toArray() as $key => $value) {
      $newKey = is_string($arg) ? Data::get($value, $arg) : $arg($value, $key);

      $results[$newKey] = $value; // overwrite on duplicate keys
    }


    return new static($results);
  }

  /**
   * Wrap any value into a CollectionV2 instance.
   *
   * @param iterable<array-key, T>|T|null $value
   */
  public static function wrap(mixed $value = null): static
  {
    if (null === $value) {
      $value = [];
    }

    if (is_array($value)) {
      return new static($value);
    }

    return new static(is_iterable($value) ? iterator_to_array($value) : [$value]);
  }

  /**
   *  Extract a single field value from each item.
   *
   *  Supports nested paths via dot notation.
   *
   * @template TNewKey of string
   *
   * @param TNewKey $key
   *
   * @return Collection<int, T[TNewKey]>
   */
  public function pluck(string $key): Collection
  {
    return new self(array_map(static fn ($item): mixed => Data::get($item, $key), $this->toArray()));
  }

  /**
   * Extract only the specified keys from each item in the collection.
   *
   * Returns a new collection where each item contains only the specified keys.
   * Supports dot notation for nested paths.
   *
   * @template TNewKey of string
   *
   * @param TNewKey ...$keys One or more keys to extract
   *
   * @return Collection<int, array<TNewKey, mixed>>
   */
  public function only(string ...$keys): Collection
  {
    return self::wrap($this->map(function ($item) use ($keys) {
      $result = [];
      foreach ($keys as $key) {
        $result[$key] = Data::get($item, $key);
      }
      return $result;
    }));

  }

  /**
   * Merge a collection into another Collection.
   *
   * @param iterable<array-key, mixed> $collection
   *
   * @return static
   */
  public function merge(iterable $collection): self
  {
    if (!$collection instanceof static) {
      throw new InvalidArgumentException('Collection must be instance of '.static::class);
    }
    return new static(array_merge($this->toArray(), [...$collection]));
  }

  /**
   *  Extract a value from an entry using a key path.
   *
   *  Delegates to Data::get helper.
   */
  protected function getFromEntry(mixed $item, string $key): mixed
  {
    return Data::get($item, $key);
  }

  /**
   * Sort using property path.
   */
  public function sortBy(string $key, bool $ascending = true): static
  {
    return $this->sortUsing(
      static function ($a, $b) use ($key, $ascending) {
        $result = Data::get($a, $key) <=> Data::get($b, $key);

        return $ascending ? $result : -$result;
      }
    );
  }

  /**
   * Sort using a callback function.
   *
   * @param Closure(T, T):int $callback
   */
  public function sortUsing(Closure $callback): static
  {
    $items = $this->toArray();

    uasort($items, $callback);

    return new static($items);
  }

  /**
   * Execute a callback on each of the collection's items and keys.
   *
   * The callback will receive the item value as its first argument
   * and the item key as its second argument.
   *
   * It should then return an associative array with a single key/value pair.
   *
   * @template TNewKey of array-key
   * @template TNew
   *
   * @param Closure(T, array-key): array<TNewKey, TNew> $callback
   *
   * @return static<TNewKey, TNew>
   */
  public function mapWithKeys(Closure $callback): self
  {
    $result = [];

    foreach ($this->toArray() as $key => $value) {
      $assoc = $callback($value, $key);
      //Do not use array_merge.
      //Values in the input arrays with numeric keys will be renumbered with incrementing keys starting from zero in the result array.
      foreach ($assoc as $mapKey => $mapValue) {
        $result[$mapKey] = $mapValue;
      }
    }

    return new static($result);
  }

  /**
   * @param Closure(T, TKey):bool $p
   */
  #[\Override]
  public function filter(Closure $p): static
  {
    return new static(array_filter($this->toArray(), $p, ARRAY_FILTER_USE_BOTH));
  }

  /**
   * @param Closure(T, TKey):bool $p
   */
  public function reject(Closure $p): static
  {
    return $this->filter(fn ($value, $key): bool => !$p($value, $key));
  }


  /**
   * Execute a callback on each of the collection's items.
   *
   * @param Closure(T):void $callback
   *
   * @return static
   */
  public function each(Closure $callback): self
  {
    foreach ($this->toArray() as $item) {
      $callback($item);
    }

    return $this;
  }

  /**
   * Filter and keep collection unique items using a callback.
   *
   * @param (Closure(T):bool)|null $callback
   */
  public function unique(?Closure $callback = null): static
  {
    if (!$callback) {
      return new static(array_unique($this->toArray()));
    }

    $unique = [];

    return $this->filter(function ($item) use (&$unique, $callback): bool {
      $comparison = $callback($item);
      if (in_array($comparison, $unique, true)) {
        return false;
      }
      $unique[] = $comparison;
      return true;
    });
  }

  /**
   *  Determine if a collection contains a value or matches a predicate.
   *
   *  If a Closure is given, it returns true if any item satisfies it.
   *
   * @param Closure(T):bool|T $value
   */
  #[\Override]
  public function contains(mixed $value): bool
  {
    if ($value instanceof Closure) {
      return $this->findFirst($value, false) !== false;
    }
    return parent::contains($value);
  }

  /**
   *  Get a value by key using dot-notation access.
   *
   *  Falls back to default if the key is not found.
   *
   *
   * @template TDefault
   *
   * @param TDefault $default
   *
   * @return T|TDefault
   */
  #[\Override]
  public function get(string|int $key, mixed $default = null): mixed
  {
    return Data::get($this->toArray(), $key, $default);
  }

  /**
   * Convert the collection to a plain PHP array.
   *
   * @return array<TKey, T>
   */
  #[\Override]
  public function toArray(): array
  {
    return parent::toArray(); // TODO: Change the autogenerated stub
  }

  /**
   *  Return the first element of the collection.
   *
   * @template TDefault
   *
   * @param TDefault $default
   *
   * @return T|TDefault
   */
  #[\Override]
  public function first(mixed $default = null): mixed
  {
    $items = $this->getValues();

    return $items[0] ?? $default;
  }


  /**
   * Search the collection for a given value and return the corresponding key if successful.
   *
   * @param T|(callable(T,TKey): bool) $value
   *
   * @return TKey|false
   */
  public function search(mixed $value, bool $strict = false): mixed
  {
    if (!$value instanceof Closure) {
      return array_search($value, $this->toArray(), $strict);
    }

    return array_find_key($this->toArray(), $value) ?? false;
  }

  /**
   * Chunk the collection into chunks of the given size.
   *
   * @return ($preserveKeys is true ? static<int, static> : static<int, static<int, T>>)
   */
  public function chunk(int $size, bool $preserveKeys = true)
  {
    if ($size <= 0) {
      return new static();
    }

    $chunks = [];

    foreach (array_chunk($this->toArray(), $size, $preserveKeys) as $chunk) {
      $chunks[] = new static($chunk);
    }

    return new static($chunks);
  }
  /**
   * Remove and return a slice of items, optionally replacing them.
   *
   * Mutates this collection in place (the spliced-out portion is removed,
   * any replacement is inserted in its place) and returns a new collection
   * of the removed items.
   *
   * @return static
   */
  public function splice(int $offset, ?int $length = null, mixed $replacement = []): static
  {
    $items = $this->toArray();
    $removed = array_splice($items, $offset, $length, $replacement);

    $this->clear();
    foreach ($items as $key => $value) {
      $this->set($key, $value);
    }

    return new static($removed);
  }

  /**
   * Convert collection to array for JSON serialization.
   *
   * @return array<array-key, mixed>
   */
  public function jsonSerialize(): array
  {
    return $this->toArray();
  }

  /**
   * Get the items in the collection that are not present in the given items.
   *
   * @param iterable<TKey, T> $items
   */
  public function diff(iterable $items): static
  {
    return new static(array_diff($this->toArray(), static::wrap($items)->toArray()));
  }

  /**
   * Get the items in the collection that are not present in the given items.
   *
   * @param iterable<TKey, T> $items
   */
  public function diffKeys(iterable $items): static
  {
    return new static(array_diff_key($this->toArray(), static::wrap($items)->toArray()));
  }

  /**
   * Concatenate values of a given key as a string.
   *
   * @param (Closure(T): string)|string|null $value
   */
  public function implode(callable|string|null $value = null, string $glue = ', '): string
  {
    if ($value instanceof Closure) {
      return implode($glue, $this->map($value)->toArray());
    }

    if (is_string($value)) {
      return implode($glue, $this->pluck($value)->toArray());
    }

    return implode($glue, $this->toArray());
  }

}
