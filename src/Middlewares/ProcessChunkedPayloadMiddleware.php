<?php

namespace CarroPublic\ChunkUpload\Middlewares;

use Closure;
use Illuminate\Http\Request;
use CarroPublic\ChunkUpload\ChunkPayloadProcessor;

class ProcessChunkedPayloadMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     * @throws \Exception
     */
    public function handle($request, Closure $next)
    {
        if (!$this->isBeingChunked($request)) {
            return $next($request);
        }

        /** @var ChunkPayloadProcessor $processor */
        $processor = app(ChunkPayloadProcessor::class);
        if (($chunks = $processor->process($request)) === true) {
            return $next($request);
        }

        # Stop the pipeline and return response, the pipeline will resume in another chunk request
        return response()->json([
            'completed' => $chunks,
        ]);
    }

    /**
     * Check whether the current Request is being chunked
     * @param Request $request
     * @return bool
     */
    protected function isBeingChunked(Request $request)
    {
        return $request->has([
            ChunkPayloadProcessor::CHUNK_DATA,
            ChunkPayloadProcessor::CHUNK_INDEX,
            ChunkPayloadProcessor::TOTAL_CHUNK,
            ChunkPayloadProcessor::PAYLOAD_HASHED,
        ]);
    }
}
