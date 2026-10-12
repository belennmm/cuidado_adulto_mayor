<?php

namespace App\Support;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

final class BoundedResponseStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private StreamInterface $stream;

    private int $received = 0;

    public function __construct(private readonly int $maximum)
    {
        $this->stream = Utils::streamFor(fopen('php://temp/maxmemory:1048576', 'w+b'));
    }

    public function write($string): int
    {
        if (strlen($string) > $this->maximum - $this->received) {
            throw new OutboundRequestException;
        }
        $written = $this->stream->write($string);
        $this->received += $written;

        return $written;
    }
}
