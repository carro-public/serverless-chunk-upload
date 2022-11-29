<?php

namespace CarroPublic\ChunkUpload\Cache;

class RedisTaggedCache extends \Illuminate\Cache\RedisTaggedCache
{
    /**
     * Return all FOREVER keys which are being tagged under current tag
     * @return array
     */
    public function foreverKeys()
    {
        return $this->getKeysByReference(self::REFERENCE_KEY_FOREVER);
    }

    /**
     * Return all standard keys which are being tagged under current tag
     * @return array
     */
    public function standardKeys()
    {
        return $this->getKeysByReference(self::REFERENCE_KEY_STANDARD);
    }

    /**
     * @param $reference
     * @return array
     */
    protected function getKeysByReference($reference)
    {
        $keys = [];
        foreach (explode('|', $this->tags->getNamespace()) as $segment) {
            $referenceKey = $this->referenceKey($segment, $reference);

            $cursor = $defaultCursorValue = '0';

            do {
                [$cursor, $valuesChunk] = $this->store->connection()->sscan(
                    $referenceKey,
                    $cursor,
                    ['match' => '*', 'count' => 1000]
                );

                // PhpRedis client returns false if set does not exist or empty. Array destruction
                // on false stores null in each variable. If valuesChunk is null, it means that
                // there were not results from the previously executed "sscan" Redis command.
                if (is_null($valuesChunk)) {
                    break;
                }

                $keys = array_merge($keys, array_unique($valuesChunk));
            } while (((string) $cursor) !== $defaultCursorValue);
        }

        $storePrefix = $this->store->getPrefix();
        return array_map(function ($key) use ($storePrefix) {
            return preg_replace("/{$storePrefix}/", "", $key);
        }, $keys);
    }

    /**
     * Remove a key from tag set
     * @param $reference
     * @param $keys
     * @return void
     */
    public function removeKeysFromTag($reference, $keys)
    {
        foreach (explode('|', $this->tags->getNamespace()) as $segment) {
            $referenceKey = $this->referenceKey($segment, $reference);

            $this->store->connection()->srem(
                $referenceKey,
                array_map(function ($key) {
                    return $this->getPrefix()."$key";
                }, $keys),
            );
        }
    }
}
