<?php

namespace CarroPublic\ChunkUpload;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\RedisTagSet;
use Illuminate\Cache\RedisTaggedCache;
use Illuminate\Contracts\Cache\LockProvider;

class ChunkPayloadProcessor
{
    /**
     * The Tagged Redis Store
     * @var RedisTaggedCache
     */
    protected $store;

    /**
     * Current Chunk Index
     * @var int
     */
    protected $chunkIndex;

    /**
     * Total Chunks
     * @var int
     */
    protected $totalChunks;

    /**
     * Current Chunk Data
     * @var mixed
     */
    protected $chunkData;

    /**
     * The original payload's hashed
     * @var string
     */
    protected $payloadHashed;

    const CHUNK_INDEX = 'chunk_index';
    const TOTAL_CHUNK = 'total_chunks';
    const CHUNK_DATA = 'chunk_data';
    const PAYLOAD_HASHED = 'payload_hashed';

    public function __construct(RedisStore $store, Request $request)
    {
        $this->chunkData = $request->get(self::CHUNK_DATA);
        $this->payloadHashed = $request->get(self::PAYLOAD_HASHED);
        $this->chunkIndex = $request->get(self::CHUNK_INDEX);
        $this->totalChunks = $request->get(self::TOTAL_CHUNK);
        $this->store = new RedisTaggedCache(
            $store, new RedisTagSet($store, Arr::wrap($this->payloadHashed))
        );
    }

    /**
     * Process a chunk request
     * @param Request $request
     * @return boolean
     * @throws \Exception
     */
    public function process(Request $request)
    {
        // Preserve the current chunked data
        // TTL 5 mins
        $this->store->put($this->chunkIndex, $this->chunkData, 5 * 60);

        if ($this->hasCollectedAllChunks()) {
            /** @var LockProvider $redisStore */
            $redisStore = $this->store->getStore();

            // Ensure only one request can handle the original payload by using Redis Lock
            return $redisStore
                ->lock($this->payloadHashed, 0, microtime(true))
                ->get(function () use ($request) {
                    return $this->restorePayloadFromChunks($request);
                });
        }

        // Return how many chunks we collected
        return $this->store->getTags()->entries()->count();
    }

    /**
     * @param Request $request
     * @return boolean
     * @throws \Exception
     */
    protected function restorePayloadFromChunks(Request $request)
    {
        $originalPayload = '';

        for ($i = 0; $i < $this->totalChunks; $i++) {
            $originalPayload .= $this->store->get($i);
        }

        // Compare hashed to ensure the restore payload is correct
        if (!$this->validatePayload($originalPayload)) {
            throw new \Exception('Received Payload Corrupted');
        }

        $originalPayload = json_decode($originalPayload, true);

        # Attempt to remove the uniqueId
        $originalPayload = Arr::except($originalPayload, ['uniqueId']);

        if ($request->getMethod() === 'POST') {
            $request->request->replace($originalPayload);
        } else {
            $request->query->replace($originalPayload);
        }

        // Clear all cached chunks
        $this->store->flush();

        return true;
    }

    /**
     * Check whether we have already collected all chunks
     * @return bool
     */
    protected function hasCollectedAllChunks()
    {
        return $this->totalChunks === $this->store->getTags()->entries()->count();
    }

    /**
     * Validate the hashed value
     * @param $originalPayload
     * @return bool
     */
    protected function validatePayload($originalPayload)
    {
        return md5($originalPayload) === $this->payloadHashed;
    }
}
