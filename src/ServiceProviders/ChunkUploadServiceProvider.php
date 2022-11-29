<?php

namespace CarroPublic\ChunkUpload\ServiceProviders;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use CarroPublic\ChunkUpload\Middlewares\ProcessChunkedPayloadMiddleware;

class ChunkUploadServiceProvider extends ServiceProvider
{
    /**
     * @param Kernel $kernel
     * @return void
     */
    public function boot(Kernel $kernel)
    {
        $kernel->pushMiddleware(ProcessChunkedPayloadMiddleware::class);
    }
}
